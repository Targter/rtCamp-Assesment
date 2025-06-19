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

// Get email from URL
$email = isset($_GET['email']) ? trim(urldecode($_GET['email'])) : '';

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Invalid or missing email address!';
    error_log("Invalid or missing email in unsubscribe.php: $email");
    header('Location: index.php');
    exit;
}

// Handle unsubscribe request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsubscribe'])) {
    if (unsubscribeEmail($email)) {
        $_SESSION['success'] = 'You have unsubscribed successfully!';
        unset($_SESSION['verified_email']); // Clear session
        error_log("Unsubscribed email: $email");
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['error'] = 'Failed to unsubscribe!';
        error_log("Failed to unsubscribe email: $email");
        header('Location: unsubscribe.php?email=' . urlencode($email));
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Unsubscribe</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        form {
            margin: 20px 0;
        }
        button {
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
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
        .back-link {
            display: inline-block;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Do not modify the ID of the heading -->
	<h2 id="unsubscription-heading">Unsubscribe from Task Updates</h2>
	<!-- Implemention body -->
    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>
    
    <?php if (!empty($email)): ?>
        <p>Are you sure you want to unsubscribe <strong><?php echo htmlspecialchars($email); ?></strong> from Task Scheduler notifications?</p>
        <form method="POST" action="unsubscribe.php?email=<?php echo urlencode($email); ?>" aria-label="Unsubscribe form">
            <input type="hidden" name="unsubscribe" value="1">
            <button type="submit" title="Confirm unsubscribe">Confirm Unsubscribe</button>
        </form>
        <a href="index.php" class="back-link" title="Return to Task Scheduler">Back to Task Scheduler</a>
    <?php else: ?>
        <p>Invalid unsubscribe request.</p>
        <a href="index.php" class="back-link" title="Return to Task Scheduler">Back to Task Scheduler</a>
    <?php endif; ?>
</body>
</html>