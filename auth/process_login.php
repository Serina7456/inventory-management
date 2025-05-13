<?php
// Start session
session_start();

// Include database connection
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = "All fields are required.";
        header("Location: login.php");
        exit;
    }
    
    try {
        // Add debug logging
        error_log("Login attempt for username: $username");
        
        // Prepare and execute the query using the global connection
        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = oci_query($GLOBALS['db_conn'], $sql, ['username' => $username]);
        
        // Fetch the user
        $user = oci_fetch_assoc($stmt);
        
        // Debug output
        if ($user) {
            error_log("User found. Password field: " . substr($user['PASSWORD'], 0, 20) . "...");
        } else {
            error_log("No user found with username: $username");
        }
        
        // Check if user exists and password is correct
        if ($user && password_verify($password, $user['PASSWORD'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['USER_ID'];
            $_SESSION['username'] = $user['USERNAME'];
            $_SESSION['is_admin'] = (bool)$user['IS_ADMIN'];
            
            error_log("User authenticated successfully: $username (ID: {$user['USER_ID']})");
            $_SESSION['success_message'] = "You are now logged in.";
            header("Location: ../index.php");
            exit;
        } else {
            error_log("Authentication failed for username: $username");
            $_SESSION['error_message'] = "Invalid username or password.";
            header("Location: login.php");
            exit;
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $_SESSION['error_message'] = "Login failed: " . $e->getMessage();
        header("Location: login.php");
        exit;
    }
} else {
    // If not a POST request, redirect to login page
    header("Location: login.php");
    exit;
}