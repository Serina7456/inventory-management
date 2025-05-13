<?php
// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page with success message
session_start();
$_SESSION['success_message'] = "You have been logged out successfully.";
header("Location: login.php");
exit;