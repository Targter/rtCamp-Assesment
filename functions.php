<?php
// Backend functions for Task Scheduler

/**
 * Adds a new task to the task list
 * @param string $task_name The name of the task to add.
 * @return bool True on success, false on failure.
 */
function addTask(string $task_name): bool {
    $file = __DIR__ . '/tasks.txt';
    $tasks = getAllTasks();
    
    foreach ($tasks as $task) {
        if (strtolower($task['name']) === strtolower($task_name)) {
            error_log("Duplicate task: $task_name");
            return false;
        }
    }
    
    $max_id = 0;
    foreach ($tasks as $task) {
        $max_id = max($max_id, (int)$task['id']);
    }
    $new_id = $max_id + 1;
    
    $tasks[] = [
        'id' => (string)$new_id,
        'name' => $task_name,
        'completed' => false
    ];
    
    $json = json_encode($tasks, JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log("JSON encoding failed for task: $task_name");
        return false;
    }
    
    $result = file_put_contents($file, $json, LOCK_EX);
    if ($result === false) {
        error_log("Failed to write to tasks.txt for task: $task_name");
        return false;
    }
    error_log("Added task: $task_name");
    return true;
}

/**
 * Retrieves all tasks from the tasks.txt file
 * @return array Array of tasks. -- Format [ id, name, completed ]
 */
function getAllTasks(): array {
    $file = __DIR__ . '/tasks.txt';
    
    if (!file_exists($file)) {
        error_log("tasks.txt does not exist");
        return [];
    }
    
    $content = file_get_contents($file);
    if ($content === false) {
        error_log("Failed to read tasks.txt");
        return [];
    }
   
    $tasks = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in tasks.txt: " . json_last_error_msg());
        return [];
    }
    
    return $tasks;
}

/**
 * Marks a task as completed or uncompleted
 * @param string $task_id The ID of the task to mark.
 * @param bool $is_completed True to mark as completed, false to mark as uncompleted.
 * @return bool True on success, false on failure
 */
function markTaskAsCompleted(string $task_id, bool $is_completed): bool {
    $file = __DIR__ . '/tasks.txt';
    $tasks = getAllTasks();
    
    foreach ($tasks as &$task) {
        if ($task['id'] === $task_id) {
            $task['completed'] = $is_completed;
            $json = json_encode($tasks, JSON_PRETTY_PRINT);
            if ($json === false) {
                error_log("JSON encoding failed for task ID: $task_id");
                return false;
            }
            $result = file_put_contents($file, $json, LOCK_EX);
            if ($result === false) {
                error_log("Failed to write to tasks.txt for task ID: $task_id");
                return false;
            }
            error_log("Updated task ID: $task_id, completed: $is_completed");
            return true;
        }
    }
    
    error_log("Task ID not found: $task_id");
    return false;
}

/**
 * Deletes a task from the task list
 * @param string $task_id The ID of the task to delete.
 * @return bool True on success, false on failure.
 */
function deleteTask(string $task_id): bool {
    $file = __DIR__ . '/tasks.txt';
    $tasks = getAllTasks();
    
    $filtered_tasks = array_filter($tasks, function($task) use ($task_id) {
        return $task['id'] !== $task_id;
    });
    
    $filtered_tasks = array_values($filtered_tasks);
    
    $json = json_encode($filtered_tasks, JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log("JSON encoding failed for delete task ID: $task_id");
        return false;
    }
    
    $result = file_put_contents($file, $json, LOCK_EX);
    if ($result === false) {
        error_log("Failed to write to tasks.txt for delete task ID: $task_id");
        return false;
    }
    error_log("Deleted task ID: $task_id");
    return true;
}

/**
 * Generates a verification code
 * @return string 6-character code
 */
function generateVerificationCode(): string {
    $code = bin2hex(random_bytes(3));
    error_log("Generated verification code: $code");
    return $code;
}

/**
 * Adds a subscriber to pending_subscriptions.txt
 * @param string $email The subscriber's email
 * @param string $code The verification code
 * @return bool True on success, false on failure
 */
function subscribeEmail(string $email, string $code): bool {
    $subscribers_file = __DIR__ . '/subscribers.txt';
    $pending_file = __DIR__ . '/pending_subscriptions.txt';
    
    // Check if email is already verified
    $subscribers = [];
    if (file_exists($subscribers_file)) {
        $subscribers_content = @file_get_contents($subscribers_file);
        if ($subscribers_content === false) {
            error_log("Failed to read subscribers.txt");
            return false;
        }
        $subscribers = json_decode($subscribers_content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($subscribers)) {
            error_log("JSON decode error in subscribers.txt: " . json_last_error_msg());
            $subscribers = [];
        }
        if (in_array($email, $subscribers)) {
            error_log("Email already verified: $email");
            return false;
        }
    }
    
    // Check if email is already pending
    $pending = [];
    if (file_exists($pending_file)) {
        $pending_content = @file_get_contents($pending_file);
        if ($pending_content === false) {
            error_log("Failed to read pending_subscriptions.txt");
            return false;
        }
        $pending = json_decode($pending_content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($pending)) {
            error_log("JSON decode error in pending_subscriptions.txt: " . json_last_error_msg());
            $pending = [];
        }
        if (isset($pending[$email])) {
            error_log("Email already pending: $email");
            return false;
        }
    }
    
    // Add to pending subscribers
    $pending[$email] = [
        'code' => $code,
        'timestamp' => time()
    ];
    
    $json = json_encode($pending, JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log("JSON encoding failed for pending subscriber: $email");
        return false;
    }
    
    $result = file_put_contents($pending_file, $json, LOCK_EX);
    if ($result === false) {
        error_log("Failed to write to pending_subscriptions.txt for email: $email");
        return false;
    }
    error_log("Added pending subscriber: $email with code: $code");
    return true;
}

/**
 * Verifies a subscriber's email and code
 * @param string $email The subscriber's email
 * @param string $code The verification code
 * @return bool True if verified, false otherwise
 */
function verifySubscription(string $email, string $code): bool {
    $subscribers_file = __DIR__ . '/subscribers.txt';
    $pending_file = __DIR__ . '/pending_subscriptions.txt';
    
    // Read pending subscribers
    if (!file_exists($pending_file)) {
        error_log("pending_subscriptions.txt does not exist");
        return false;
    }
    
    $pending_content = @file_get_contents($pending_file);
    if ($pending_content === false) {
        error_log("Failed to read pending_subscriptions.txt");
        return false;
    }
    
    $pending = json_decode($pending_content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($pending)) {
        error_log("JSON decode error in pending_subscriptions.txt: " . json_last_error_msg());
        return false;
    }
    
    // Check if email and code match
    if (!isset($pending[$email]) || $pending[$email]['code'] !== $code) {
        error_log("Invalid code for email: $email, code: $code");
        return false;
    }
    
    // Read subscribers
    $subscribers = [];
    if (file_exists($subscribers_file)) {
        $subscribers_content = @file_get_contents($subscribers_file);
        if ($subscribers_content === false) {
            error_log("Failed to read subscribers.txt");
            return false;
        }
        $subscribers = json_decode($subscribers_content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($subscribers)) {
            error_log("JSON decode error in subscribers.txt: " . json_last_error_msg());
            $subscribers = [];
        }
    }
    
    // Add to subscribers
    $subscribers[] = $email;
    $subscribers = array_unique($subscribers); // Prevent duplicates
    
    $json = json_encode($subscribers, JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log("JSON encoding failed for subscriber: $email");
        return false;
    }
    
    $result = file_put_contents($subscribers_file, $json, LOCK_EX);
    if ($result === false) {
        error_log("Failed to write to subscribers.txt for email: $email");
        return false;
    }
    
    // Remove from pending subscribers
    unset($pending[$email]);
    $json = json_encode($pending, JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log("JSON encoding failed for pending subscribers after verifying $email");
        return false;
    }
    
    $result = file_put_contents($pending_file, $json, LOCK_EX);
    if ($result === false) {
        error_log("Failed to update pending_subscriptions.txt after verifying $email");
        return false;
    }
    
    error_log("Verified subscriber: $email");
    return true;
}


/**
 * Remove email from subscribers list
 * @param string $email The email to unsubscribe
 * @return bool True on success, false on failure
 */
function unsubscribeEmail($email) {
    $subscribers_file = __DIR__ . '/subscribers.txt';
    $pending_file = __DIR__ . '/pending_subscriptions.txt';
    $success = true;
    
    // Remove from subscribers.txt
    $subscribers = [];
    if (file_exists($subscribers_file)) {
        $subscribers_content = @file_get_contents($subscribers_file);
        if ($subscribers_content === false) {
            error_log("Failed to read subscribers.txt");
            $success = false;
        } else {
            $subscribers = json_decode($subscribers_content, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($subscribers)) {
                error_log("JSON decode error in subscribers.txt: " . json_last_error_msg());
                $success = false;
            } else {
                $subscribers = array_filter($subscribers, function($sub_email) use ($email) {
                    return $sub_email !== $email;
                });
                $subscribers = array_values($subscribers);
                
                $json = json_encode($subscribers, JSON_PRETTY_PRINT);
                if ($json === false) {
                    error_log("JSON encoding failed for unsubscribe: $email");
                    $success = false;
                } else {
                    $result = file_put_contents($subscribers_file, $json, LOCK_EX);
                    if ($result === false) {
                        error_log("Failed to update subscribers.txt for unsubscribe: $email");
                        $success = false;
                    } else {
                        error_log("Removed $email from subscribers.txt");
                    }
                }
            }
        }
    }
    
    // Remove from pending_subscriptions.txt
    $pending = [];
    if (file_exists($pending_file)) {
        $pending_content = @file_get_contents($pending_file);
        if ($pending_content === false) {
            error_log("Failed to read pending_subscriptions.txt");
            $success = false;
        } else {
            $pending = json_decode($pending_content, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($pending)) {
                error_log("JSON decode error in pending_subscriptions.txt: " . json_last_error_msg());
                $success = false;
            } else {
                if (isset($pending[$email])) {
                    unset($pending[$email]);
                    $json = json_encode($pending, JSON_PRETTY_PRINT);
                    if ($json === false) {
                        error_log("JSON encoding failed for pending unsubscribe: $email");
                        $success = false;
                    } else {
                        $result = file_put_contents($pending_file, $json, LOCK_EX);
                        if ($result === false) {
                            error_log("Failed to update pending_subscriptions.txt for unsubscribe: $email");
                            $success = false;
                        } else {
                            error_log("Removed $email from pending_subscriptions.txt");
                        }
                    }
                }
            }
        }
    }
    
    if ($success) {
        error_log("Unsubscribed email: $email");
    }
    return $success;
}

/**
 * Sends task reminders to all subscribers
 * @return int Number of emails successfully sent
 */
function sendTaskReminders() {
    // Read subscribers
    $subscribers_file = __DIR__ . '/subscribers.txt';
    $subscribers = [];
    if (file_exists($subscribers_file)) {
        $subscribers_content = @file_get_contents($subscribers_file);
        if ($subscribers_content === false) {
            error_log("Failed to read subscribers.txt");
            return 0;
        }
        $subscribers = json_decode($subscribers_content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($subscribers)) {
            error_log("Failed to parse subscribers.txt: " . json_last_error_msg());
            return 0;
        }
    } else {
        error_log("subscribers.txt does not exist");
        return 0;
    }

    if (empty($subscribers)) {
        error_log("No valid subscribers found in subscribers.txt");
        return 0;
    }

    // Get pending tasks
    $tasks = getAllTasks();
    if (!is_array($tasks)) {
        error_log("Failed to fetch tasks from tasks.txt");
        return 0;
    }
    $pending_tasks = array_filter($tasks, function($task) {
        return !$task['completed'];
    });

    // Send emails to subscribers
    $email_count = 0;
    foreach ($subscribers as $email) {
        if (sendTaskEmail($email, $pending_tasks)) {
            $email_count++;
        }
    }

    error_log("Sent pending tasks to $email_count of " . count($subscribers) . " subscribers");
    return $email_count;
}

/**
 * Sends a task reminder email to a subscriber with pending tasks
 * @param string $email The subscriber's email
 * @param array $pending_tasks Array of pending tasks
 * @return bool True on success, false on failure
 */
function sendTaskEmail($email, $pending_tasks) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email format: $email");
        return false;
    }

    // Prepare task list for HTML email
    $task_list = "<li>No pending tasks.</li>";
    if (!empty($pending_tasks)) {
        $task_list = "";
        foreach ($pending_tasks as $task) {
            $task_list .= "<li>" . htmlspecialchars($task['name']) . "</li>\n";
        }
    }

    // Build email
    $subject = 'Task Planner - Pending Tasks Reminder';
    $unsubscribe_link = "http://localhost:8000/unsubscribe.php?email=" . urlencode($email);
    $message = "<html>\n<body>\n";
    $message .= "<h2>Pending Tasks Reminder</h2>\n";
    $message .= "<p>Here are the current pending tasks:</p>\n";
    $message .= "<ul>\n{$task_list}</ul>\n";
    $message .= "<p><a id=\"unsubscribe-link\" href=\"{$unsubscribe_link}\">Unsubscribe from notifications</a></p>\n";
    $message .= "</body>\n</html>";

    // Multipart email with plain text fallback
    $boundary = md5(time());
    $headers = "From: Task Scheduler <no-reply@example.com>\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= "Pending Tasks Reminder\n\nHere are the current pending tasks:\n\n";
    $body .= strip_tags(str_replace(['<li>', '</li>'], ['- ', "\n"], $task_list)) . "\n";
    $body .= "To unsubscribe, visit: {$unsubscribe_link}\n\nBest regards,\nTask Scheduler Team\r\n";
    
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $message . "\r\n";
    $body .= "--{$boundary}--\r\n";

    error_log("Attempting to send pending tasks email to $email");
    if (mail($email, $subject, $body, $headers)) {
        error_log("Pending tasks email sent to $email");
        return true;
    } else {
        error_log("Failed to send pending tasks email to $email: " . print_r(error_get_last(), true));
        return false;
    }
}

// Extra
/**
 * Checks if an email is verified
 * @param string $email The email to check
 * @return bool True if verified, false otherwise
 */
function isVerified(string $email): bool {
    $subscribers_file = __DIR__ . '/subscribers.txt';
    $subscribers = [];
    
    if (file_exists($subscribers_file)) {
        $subscribers_content = @file_get_contents($subscribers_file);
        if ($subscribers_content === false) {
            error_log("Failed to read subscribers.txt");
            return false;
        }
        $subscribers = json_decode($subscribers_content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($subscribers)) {
            error_log("JSON decode error in subscribers.txt: " . json_last_error_msg());
            return false;
        }
    }
    
    $is_verified = in_array($email, $subscribers);
    error_log("Checked verification for $email: " . ($is_verified ? 'verified' : 'not verified'));
    return $is_verified;
}

/**
 * Checks if an email is pending verification
 * @param string $email The email to check
 * @return bool True if pending, false otherwise
 */
function isPending(string $email): bool {
    $pending_file = __DIR__ . '/pending_subscriptions.txt';
    $pending = [];
    
    if (file_exists($pending_file)) {
        $pending_content = @file_get_contents($pending_file);
        if ($pending_content === false) {
            error_log("Failed to read pending_subscriptions.txt");
            return false;
        }
        $pending = json_decode($pending_content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($pending)) {
            error_log("JSON decode error in pending_subscriptions.txt: " . json_last_error_msg());
            return false;
        }
    }
    
    $is_pending = isset($pending[$email]);
    error_log("Email is " . ($is_pending ? 'pending' : 'not pending') . ": $email");
    return $is_pending;
}

/**
 * Sends a verification email using PHP mail()
 * @param string $email The recipient's email
 * @param string $code The verification code
 * @return bool True on success, false on failure
 */
function sendVerificationEmail(string $email, string $code): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email format in sendVerificationEmail: $email");
        return false;
    }

     $subject = 'Verify subscription to Task Planner';
    $verification_link = "http://localhost:8000/verify.php?email=" . urlencode($email) . "&code=" . urlencode($code);
    $message = "<p>Click the link below to verify your subscription to Task Planner:</p>\n";
    $message .= "<p><a id=\"verification-link\" href=\"{$verification_link}\">Verify Subscription</a></p>\n";

    // Plain text fallback
    $plain_text = "To verify your subscription to Task Planner, visit: {$verification_link}";

    // Multipart email
    $boundary = md5(time());
    $headers = "From: My Task Planner <no-reply@example.com>\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $plain_text . "\r\n";
    
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $message . "\r\n";
    $body .= "--{$boundary}--\r\n";

    error_log("Attempting to send email to $email with headers: $headers");
    if (mail($email, $subject, $body, $headers)) {
        error_log("Verification email sent to $email");
        return true;
    } else {
        error_log("Failed to send email to $email: " . print_r(error_get_last(), true));
        return false;
    }
}

/**
 * Resends a verification code for a pending subscriber
 * @param string $email The subscriber's email
 * @return bool True on success, false on failure
 */
function resendVerificationCode(string $email): bool {
    $pending_file = __DIR__ . '/pending_subscriptions.txt';
    
    if (!file_exists($pending_file)) {
        error_log("pending_subscriptions.txt does not exist");
        return false;
    }
    
    $pending_content = @file_get_contents($pending_file);
    if ($pending_content === false) {
        error_log("Failed to read pending_subscriptions.txt");
        return false;
    }
    
    $pending = json_decode($pending_content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($pending)) {
        error_log("JSON decode error in pending_subscriptions.txt: " . json_last_error_msg());
        return false;
    }
    
    if (!isset($pending[$email])) {
        error_log("Email not found in pending subscribers for resend: $email");
        return false;
    }
    
    $new_code = generateVerificationCode();
    $pending[$email] = [
        'code' => $new_code,
        'timestamp' => time()
    ];
    
    $json = json_encode($pending, JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log("JSON encoding failed for resend to $email");
        return false;
    }
    
    $result = file_put_contents($pending_file, $json, LOCK_EX);
    if ($result === false) {
        error_log("Failed to update pending_subscriptions.txt for resend to $email");
        return false;
    }
    
    $result = sendVerificationEmail($email, $new_code);
    if (!$result) {
        error_log("Failed to resend verification email to $email");
        return false;
    }
    
    error_log("Resent verification code to $email");
    return true;
}

?>



