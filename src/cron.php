
<?php
require_once 'functions.php';

// Send task reminders to all subscribers
$email_count = sendTaskReminders();

if ($email_count === 0) {
    error_log("Cron job completed: No emails sent (no subscribers or tasks)");
    exit(0);
}

error_log("Cron job completed: Sent pending tasks to $email_count subscribers");
exit(0);
?>