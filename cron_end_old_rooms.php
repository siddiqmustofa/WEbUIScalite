<?php
$scalelite_url = "https://scalelite.box.web.id/bigbluebutton/api";
$secret = "a629fd7b283c0cd734a5ea00b522ddcd53f798fa57f07954";

$meeting_id = $_POST['meetingID'] ?? ($_GET['meetingID'] ?? null);
if (!$meeting_id) { http_response_code(400); exit("meetingID missing"); }

$query = "meetingID=$meeting_id&password=end";
$checksum = sha1("end$query$secret");
$url = "$scalelite_url/end?$query&checksum=$checksum";
$response = file_get_contents($url);

if (php_sapi_name() !== 'cli') {
    header('Location: dashboard.php');
}
echo "Room $meeting_id ended.
";
