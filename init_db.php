<?php
$db = new PDO('sqlite:db.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id TEXT,
    room_name TEXT,
    moderator_pw TEXT,
    attendee_pw TEXT,
    created_at TEXT
)");
echo "Database initialized.";
