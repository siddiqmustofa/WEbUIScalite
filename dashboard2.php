<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$scalelite_url = "https://scalelite.box.web.id/bigbluebutton/api";
$secret = "a629fd7b283c0cd734a5ea00b522ddcd53f798fa57f07954";
$db = new PDO('sqlite:db.sqlite');

$bbb_servers = [
    'telco1' => [
        'url' => 'https://telco.satkomlek.id/bigbluebutton/api',
        'secret' => 'wR5H1O5Z4iQsqAC6Yb7enmzu5EUGIEWPNmI68Qckk',
    ],
    'telco2' => [
        'url' => 'https://telco2.satkomlek.id/bigbluebutton/api',
        'secret' => 'IaYIvfQqnlt3ODGukuvTySqkRI2AHiJe31aKqHRok',
    ],
    'bbb' => [
        'url' => 'https://bbb.box.web.id/bigbluebutton/api/',
        'secret' => 'aHW8UfUOCTdGaNepH0RL3PT6PiOWMVjl2RKBm7CR80M',
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteRoom'])) {
    $meetingID = $_POST['meetingID'];
    $stmt = $db->prepare("DELETE FROM rooms WHERE meeting_id = ?");
    $stmt->execute([$meetingID]);
    header("Location: dashboard.php?deleted=1");
    exit;
}

$rooms = $db->query("SELECT * FROM rooms ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

function cekStatusRoom($meeting_id, $secret, $base_url) {
    $query = "meetingID=$meeting_id";
    $checksum = sha1("isMeetingRunning" . $query . $secret);
    $url = "$base_url/isMeetingRunning?$query&checksum=$checksum";

    $opts = [
        "http" => ["method" => "GET", "header" => "User-Agent: PHP\r\n"]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $xml = $response ? @simplexml_load_string($response) : false;

    if ($xml && $xml->returncode == 'SUCCESS') {
        return ($xml->running == 'true') ? 'Online' : 'Offline';
    }
    return 'Tidak diketahui';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Room</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-800 text-white flex flex-col p-6">
        <h1 class="text-2xl font-bold mb-6">Loadbalancer</h1>
        <nav class="space-y-2">
            <a href="dashboard.php" class="block py-2 px-4 rounded bg-gray-700">Dashboard</a>
            <a href="create_room.php" class="block py-2 px-4 rounded hover:bg-gray-700">Buat Room</a>
            <a href="logout.php" class="block py-2 px-4 rounded hover:bg-gray-700">Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-10">
        <h2 class="text-3xl font-bold mb-6">Status Server</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <?php foreach ($bbb_servers as $name => $server): ?>
                <?php
                    $checksum = sha1("getMeetings" . $server['secret']);
                    $check_url = $server['url'] . "/getMeetings?checksum=$checksum";

                    $opts = ["http" => ["method" => "GET", "header" => "User-Agent: PHP\r\n"]];
                    $context = stream_context_create($opts);
                    $response = @file_get_contents($check_url, false, $context);
                    $xml = $response ? @simplexml_load_string($response) : false;

                    $online = ($xml && $xml->returncode == 'SUCCESS');
                    $load = 0;

                    if ($online && isset($xml->meetings)) {
                        $meetings = $xml->meetings->meeting ?? [];
                        if (is_array($meetings)) {
                            $load = count($meetings);
                        } elseif (!empty($meetings)) {
                            $load = 1;
                        }
                    }
                ?>
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-lg font-semibold mb-2"><?= strtoupper($name) ?></div>
                    <div class="<?= $online ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $online ? '🟢 Online' : '🔴 Offline' ?>
                    </div>
                    <?php if ($online): ?>
                        <div class="text-sm text-gray-700 mt-1">👥 Load: <?= $load ?> meeting aktif</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 class="text-3xl font-bold mb-4">Riwayat Room</h2>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4">
                ✅ Room berhasil dihapus.
            </div>
        <?php endif; ?>

        <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
            <table class="w-full table-auto">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Nama Room</th>
                        <th class="px-4 py-2 text-left">Meeting ID</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Tanggal</th>
                        <th class="px-4 py-2 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room):
                        $status = cekStatusRoom($room['meeting_id'], $secret, $scalelite_url);
                        $class = strtolower($status) === 'online' ? 'text-green-600' : 'text-red-600';

                        $mod_link = "$scalelite_url/join?fullName=Admin&meetingID={$room['meeting_id']}&password={$room['moderator_pw']}";
                        $att_link = "$scalelite_url/join?fullName=Peserta&meetingID={$room['meeting_id']}&password={$room['attendee_pw']}";
                        $qr_mod = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($mod_link);
                        $qr_att = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($att_link);
                    ?>
                    <tr class="border-t">
                        <td class="px-4 py-2"><?= htmlspecialchars($room['room_name']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($room['meeting_id']) ?></td>
                        <td class="px-4 py-2 <?= $class ?>"><?= $status ?></td>
                        <td class="px-4 py-2"><?= $room['created_at'] ?></td>
                        <td class="px-4 py-2 space-y-2">
                            <details>
                                <summary class="cursor-pointer text-blue-600">Lihat Detail</summary>
                                <div class="text-sm mt-2">
                                    <strong>Moderator:</strong><br>
                                    <input type="text" value="<?= htmlspecialchars($mod_link) ?>" readonly onclick="this.select();" class="w-full border px-2 py-1 rounded bg-gray-100"><br>
                                    Password: <?= htmlspecialchars($room['moderator_pw']) ?><br>
                                    <img src="<?= $qr_mod ?>" class="mt-2 w-24 h-24" alt="QR Mod"><br><br>

                                    <strong>Peserta:</strong><br>
                                    <input type="text" value="<?= htmlspecialchars($att_link) ?>" readonly onclick="this.select();" class="w-full border px-2 py-1 rounded bg-gray-100"><br>
                                    Password: <?= htmlspecialchars($room['attendee_pw']) ?><br>
                                    <img src="<?= $qr_att ?>" class="mt-2 w-24 h-24" alt="QR Att">
                                </div>
                            </details>
                            <form method="POST" action="cron_end_old_rooms.php" class="inline">
                                <input type="hidden" name="meetingID" value="<?= $room['meeting_id'] ?>">
                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700" onclick="return confirm('Akhiri room ini?')">End</button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="meetingID" value="<?= $room['meeting_id'] ?>">
                                <input type="hidden" name="deleteRoom" value="1">
                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700" onclick="return confirm('Hapus riwayat ini?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
