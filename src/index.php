<?php
session_start();
require_once 'functions.php';

// Log session data for debugging
error_log("Session ID: " . session_id() . ", Data: " . print_r($_SESSION, true));

// Initialize error and success messages
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error']);
unset($_SESSION['success']);

// Handle task addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task-name'])) {
    $task_name = trim($_POST['task-name']);
    if (!empty($task_name)) {
        if (!addTask($task_name)) {
            $_SESSION['error'] = 'Task already exists!';
        } else {
            $_SESSION['success'] = 'Task added successfully!';
        }
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['error'] = 'Task name cannot be empty!';
        header('Location: index.php');
        exit;
    }
}

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task-id']) && isset($_POST['completed'])) {
    $task_id = $_POST['task-id'];
    $is_completed = $_POST['completed'] === '1';
    if (markTaskAsCompleted($task_id, $is_completed)) {
        $_SESSION['success'] = 'Task status updated!';
    } else {
        $_SESSION['error'] = 'Failed to update task status!';
    }
    header('Location: index.php');
    exit;
}

// Handle task deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete-task-id'])) {
    $task_id = $_POST['delete-task-id'];
    if (deleteTask($task_id)) {
        $_SESSION['success'] = 'Task deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete task!';
    }
    header('Location: index.php');
    exit;
}

// Handle email subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $code = generateVerificationCode();
        error_log("Generated code for $email: $code");
        $addResult = subscribeEmail($email, $code);
        error_log("subscribeEmail result for $email: " . ($addResult ? 'true' : 'false'));
        if ($addResult) {
            $sendResult = sendVerificationEmail($email, $code);
            error_log("sendVerificationEmail result for $email: " . ($sendResult ? 'true' : 'false'));
            if ($sendResult) {
                $_SESSION['success'] = 'Verification code sent to your email!';
                $_SESSION['verified_email'] = $email; // Store email for status check
                error_log("Redirecting to verify.php for email: $email");
                header('Location: verify.php?email=' . urlencode($email));
                exit;
            } else {
                $_SESSION['error'] = 'Failed to send verification email!';
            }
        } else {
            $_SESSION['error'] = 'Email already subscribed or pending verification!';
        }
    } else {
        $_SESSION['error'] = 'Invalid email address!';
    }
    error_log("Redirecting to index.php with error: " . ($_SESSION['error'] ?? 'None'));
    header('Location: index.php');
    exit;
}

// Get all tasks
$tasks = getAllTasks();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Scheduler</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .task-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .task-item:last-child {
            border-bottom: none;
        }
        .task-name {
            flex-grow: 1;
        }
        .task-item.completed .task-name {
            text-decoration: line-through;
            color: #666;
        }
        form {
            margin: 20px 0;
        }
        input[type="text"], input[type="email"] {
            padding: 8px;
            margin-right: 10px;
            width: 200px;
        }
        button {
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .delete-task {
            background-color: #dc3545;
        }
        .delete-task:hover {
            background-color: #c82333;
        }
        .unsubscribe-button {
            background-color: #dc3545;
            margin-top: 10px;
        }
        .unsubscribe-button:hover {
            background-color: #c82333;
        }
        .error {
            color: red;
            margin: 10px 0;
        }
        .success {
            color: green;
            margin: 10px 0;
        }
        .pending {
            color: orange;
            margin: 10px 0;
        }
        .pending a {
            color: #007bff;
            text-decoration: none;
        }
        .pending a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Add Task Form -->
    <h1>To Do List</h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?php echoterrit: echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>
    <form method="POST" action="">
        <input type="text" name="task-name" id="task-name" placeholder="Enter new task" required>
        <button type="submit" id="add-task">Add Task</button>
    </form>

    <!-- Tasks List -->
    <h2>Task List</h2>
    <ul class="task-list">
        <?php foreach ($tasks as $task): ?>
            <li class="task-item <?php echo $task['completed'] ? 'completed' : ''; ?>">
                <input type="checkbox" class="task-status" 
                    data-task-id="<?php echo htmlspecialchars($task['id']); ?>"
                    <?php echo $task['completed'] ? 'checked' : ''; ?>
                    aria-label="Mark task as completed">
                <span class="task-name"><?php echo htmlspecialchars($task['name']); ?></span>
                <form method="POST" action="" style="display:inline;">
                    <input type="hidden" name="delete-task-id" value="<?php echo htmlspecialchars($task['id']); ?>">
                    <button type="submit" class="delete-task" title="Delete task">Delete</button>
                </form>
            </li>
        <?php endforeach; ?>
        <?php if (empty($tasks)): ?>
            <li>No tasks available</li>
        <?php endif; ?>
    </ul>

    <!-- Subscription Form -->
    <h2>Subscribe for Cron Activities</h2>
    <form method="POST" action="">
        <input type="email" name="email" id="email" placeholder="Enter your email" required>
        <button type="submit" id="submit-email">Subscribe</button>
    </form>
    
    <?php
    // Display verification status if email is in session
    if (isset($_SESSION['verified_email'])) {
        $email = $_SESSION['verified_email'];
        if (isVerified($email)) {
            echo '<p class="success verification-status">You are verified!</p>';
            echo '<form method="GET" action="unsubscribe.php">';
            echo '<input type="hidden" name="email" value="' . htmlspecialchars($email) . '">';
            echo '<button type="submit" class="unsubscribe-button">Unsubscribe</button>';
            echo '</form>';
        } elseif (isPending($email)) {
            echo '<p class="pending verification-status">Your email is pending verification. <a href="unsubscribe.php?email=' . urlencode($email) . '">Click here to verify.</a></p>';
        } else {
            echo '<p class="error verification-status">Your email is not subscribed!</p>';
        }
    }
    ?>

    <script>
        // Handle checkbox changes
        document.querySelectorAll('.task-status').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskId = this.getAttribute('data-task-id');
                const isCompleted = this.checked ? '1' : '0';
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `task-id=${taskId}&completed=${isCompleted}`
                }).then(response => {
                    if (!response.ok) {
                        console.error('Failed to update task status');
                        alert('Failed to update task status. Please try again.');
                    }
                    const taskItem = this.closest('.task-item');
                    taskItem.classList.toggle('completed', this.checked);
                }).catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
    </script>
</body>
</html>