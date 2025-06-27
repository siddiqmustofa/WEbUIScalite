<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}
include 'assets/qr_lib.php'; // pastikan pakai QR dari api.qrserver.com

$scalelite_url = "https://scalelite.box.web.id/bigbluebutton/api";
$secret = "a629fd7b283c0cd734a5ea00b522ddcd53f798fa57f07954";
$db = new PDO('sqlite:db.sqlite');

$success = false;
$link_mod = $link_att = $qr_mod = $qr_att = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room = $_POST['room'] ?? 'Room';
    $meeting_id = preg_replace('/\\s+/', '_', strtolower($room));
    $mod_pw = $_POST['mod'] ?? 'mod123';
    $att_pw = $_POST['att'] ?? 'att123';

    $query = "name=$room&meetingID=$meeting_id&moderatorPW=$mod_pw&attendeePW=$att_pw";
    $checksum = sha1("create$query$secret");
    $url = "$scalelite_url/create?$query&checksum=$checksum";
    $xml = simplexml_load_string(file_get_contents($url));

    if ($xml && $xml->returncode == 'SUCCESS') {
        $success = true;

        $join_mod = "fullName=Admin&meetingID=$meeting_id&password=$mod_pw";
        $cs_mod = sha1("join$join_mod$secret");
        $link_mod = "$scalelite_url/join?$join_mod&checksum=$cs_mod";
        $qr_mod = generateQRCodeURL($link_mod);

        $join_att = "fullName=Peserta&meetingID=$meeting_id&password=$att_pw";
        $cs_att = sha1("join$join_att$secret");
        $link_att = "$scalelite_url/join?$join_att&checksum=$cs_att";
        $qr_att = generateQRCodeURL($link_att);

        $stmt = $db->prepare("INSERT INTO rooms (meeting_id, room_name, moderator_pw, attendee_pw, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
        $stmt->execute([$meeting_id, $room, $mod_pw, $att_pw]);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buat Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-800 text-white flex flex-col p-6">
        <h1 class="text-2xl font-bold mb-6">Loadbalancer</h1>
        <nav class="space-y-2">
            <a href="dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a>
            <a href="create_room.php" class="block py-2 px-4 rounded bg-gray-700">Buat Room</a>
            <a href="logout.php" class="block py-2 px-4 rounded hover:bg-gray-700">Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-10">
        <h2 class="text-3xl font-bold mb-8">Buat Room BigBlueButton</h2>

        <div class="bg-white p-6 rounded-2xl shadow max-w-xl">
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Nama Room</label>
                    <input type="text" name="room" placeholder="Contoh: Rapat Bulanan" required
                           class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:border-blue-400">
                </div>
                <div>
                    <label class="block text-sm font-medium">Password Moderator</label>
                    <input type="text" name="mod" value="mod123" required
                           class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:border-blue-400">
                </div>
                <div>
                    <label class="block text-sm font-medium">Password Peserta</label>
                    <input type="text" name="att" value="att123" required
                           class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:border-blue-400">
                </div>
                <div class="text-right">
                    <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Buat Room
                    </button>
                </div>
            </form>
        </div>

        <?php if ($success): ?>
        <div class="mt-10 bg-white p-6 rounded-2xl shadow max-w-xl">
            <h3 class="text-xl font-semibold mb-4 text-green-600">Room berhasil dibuat!</h3>

            <div class="mb-6">
                <label class="font-medium">Link Moderator:</label>
                <div class="flex space-x-2 mt-1">
                    <input type="text" id="modLink" value="<?= htmlspecialchars($link_mod) ?>" readonly onclick="this.select();" class="flex-1 border p-2 rounded bg-gray-100">
                    <button type="button" onclick="copyToClipboard('modLink')" class="bg-blue-600 text-white px-4 py-1 rounded">Copy</button>
                </div>
                <img src="<?= htmlspecialchars($qr_mod) ?>" alt="QR Moderator" class="mt-4 w-32 h-32">
            </div>

            <div>
                <label class="font-medium">Link Peserta:</label>
                <div class="flex space-x-2 mt-1">
                    <input type="text" id="attLink" value="<?= htmlspecialchars($link_att) ?>" readonly onclick="this.select();" class="flex-1 border p-2 rounded bg-gray-100">
                    <button type="button" onclick="copyToClipboard('attLink')" class="bg-blue-600 text-white px-4 py-1 rounded">Copy</button>
                </div>
                <img src="<?= htmlspecialchars($qr_att) ?>" alt="QR Peserta" class="mt-4 w-32 h-32">
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
function copyToClipboard(elementId) {
    const input = document.getElementById(elementId);
    if (!input) return alert("Elemen tidak ditemukan!");

    input.select();
    input.setSelectionRange(0, 99999); // Mobile support

    navigator.clipboard.writeText(input.value)
        .then(() => alert("Link berhasil disalin!"))
        .catch(() => alert("Gagal menyalin. Silakan salin manual."));
}
</script>

</body>
</html>
