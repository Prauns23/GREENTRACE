<?php
// cron_activity_reminders.php – Run daily
require_once 'init_session.php';
require_once 'config.php';
require_once 'notifications_helper.php'; // Contains createNotification()

// Get activities starting in exactly 7 days
$targetDate = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("SELECT id, title, date, location FROM activities WHERE date = ? AND archived = 0");
$stmt->bind_param("s", $targetDate);
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If no activities, exit early
if (empty($activities)) {
    echo "No activities found for " . $targetDate . ".\n";
    exit;
}

// Fetch all active users (excluding admins)
$userStmt = $conn->prepare("SELECT id FROM users_tbl WHERE archived = 0 AND role != 'admin'");
$userStmt->execute();
$users = $userStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$userStmt->close();

if (empty($users)) {
    echo "No active users found.\n";
    exit;
}

// Loop through each activity and send notifications
foreach ($activities as $act) {
    $notifTitle = "Activity Reminder";
    $notifMessage = "The activity \"<strong>{$act['title']}</strong>\" is starting in 1 week on {$act['date']} at {$act['location']}. Don't forget to join!";
    $link = "pages/activity_details.php?id={$act['id']}";

    foreach ($users as $user) {
        createNotification($user['id'], 'activity', $notifTitle, $notifMessage, $link);
    }
}

echo "Reminders sent for " . count($activities) . " activities to " . count($users) . " users.\n";
?>