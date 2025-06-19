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

// Get email and code from URL
$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';
$code = isset($_GET['code']) ? trim($_GET['code']) : '';

// Handle manual verification via form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification-code']) && !empty($email)) {
    $code = trim($_POST['verification-code']);
    if (verifySubscription($email, $code)) {
        $_SESSION['success'] = 'Email verified successfully!';
        $_SESSION['verified_email'] = $email;
        error_log("Email verified: $email, redirecting to index.php");
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['error'] = 'Invalid verification code!';
        error_log("Invalid code for email: $email, code: $code");
        header('Location: verify.php?email=' . urlencode($email));
        exit;
    }
}

// Handle automatic verification if email and code are provided
if (!empty($email) && !empty($code)) {
    if (verifySubscription($email, $code)) {
        $_SESSION['success'] = 'Email verified successfully!';
        $_SESSION['verified_email'] = $email;
        error_log("Email verified: $email, redirecting to index.php");
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['error'] = 'Invalid verification code!';
        unset($_SESSION['verified_email']); // Clear stale email data
        error_log("Invalid code for email: $email, code: $code");
        header('Location: verify.php?email=' . urlencode($email));
        exit;
    }
}

// Handle resend request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend']) && !empty($email)) {
    if (resendVerificationCode($email)) {
        $_SESSION['success'] = 'A new verification code has been sent to your email!';
        error_log("Resent verification code to: $email");
    } else {
        $_SESSION['error'] = 'Failed to resend verification code!';
        error_log("Failed to resend verification code to: $email");
    }
    header('Location: verify.php?email=' . urlencode($email));
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
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
        input[type="text"] {
            padding: 8px;
            margin-right: 10px;
            width: 200px;
        }
        button {
            padding: 8px 16px;
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
        .error {
            color: red;
            margin: 10px 0;
        }
        .success {
            color: green;
            margin: 10px 0;
        }
    </style>
</head>
<body>
   <h2 id="verification-heading">Subscription Verification</h2>
    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>
    
    <?php if (!empty($email)): ?>
        <p>Please enter the verification code sent to <?php echo htmlspecialchars($email); ?>:</p>
        <form method="POST" action="verify.php?email=<?php echo urlencode($email); ?>">
            <input type="text" name="verification-code" id="verification-code" placeholder="Enter verification code" required>
            <button type="submit" title="Submit verification code">Submit Code</button>
        </form>
        <p>Didn't receive the code?</p>
        <form method="POST" action="verify.php?email=<?php echo urlencode($email); ?>">
            <input type="hidden" name="resend" value="1">
            <button type="submit" title="Resend verification code">Resend Verification Code</button>
        </form>
    <?php else: ?>
        <p>Invalid verification request. Please use the link provided in the verification email.</p>
    <?php endif; ?>
</body>
</html>