<?php
// Start session
session_start();

// Include database connection
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Add debug logging
    error_log("Registration attempt - Username: $username, Email: $email");
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error_message'] = "All fields are required.";
        header("Location: register.php");
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Invalid email format.";
        header("Location: register.php");
        exit;
    }
    
    // Validate password match
    if ($password !== $confirm_password) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: register.php");
        exit;
    }
    
    // Validate password strength (minimum 8 characters, must contain at least one letter and one number)
    if (strlen($password) < 8) {
        $_SESSION['error_message'] = "Password must be at least 8 characters.";
        header("Location: register.php");
        exit;
    }
    
    // Check if password contains at least one letter
    if (!preg_match('/[a-zA-Z]/', $password)) {
        $_SESSION['error_message'] = "Password must contain at least one letter.";
        header("Location: register.php");
        exit;
    }
    
    // Check if password contains at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $_SESSION['error_message'] = "Password must contain at least one number.";
        header("Location: register.php");
        exit;
    }
    
    try {
        // Check if username or email already exists
        $sql = "SELECT COUNT(*) AS count_users FROM users WHERE username = :username OR email = :email";
        $stmt = oci_parse($GLOBALS['db_conn'], $sql);
        
        if (!$stmt) {
            $error = oci_error($GLOBALS['db_conn']);
            throw new Exception("Error preparing SQL: " . $error['message']);
        }
        
        oci_bind_by_name($stmt, ":username", $username);
        oci_bind_by_name($stmt, ":email", $email);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $error = oci_error($stmt);
            throw new Exception("Error executing SQL: " . $error['message']);
        }
        
        $row = oci_fetch_assoc($stmt);
        $count = $row['COUNT_USERS'];
        
        error_log("Existing user check - Count: $count");
        
        if ($count > 0) {
            $_SESSION['error_message'] = "Username or email already exists.";
            header("Location: register.php");
            exit;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        error_log("Password hash generated: " . substr($hashed_password, 0, 20) . "...");
        
        // Insert new user using direct bind method for Oracle
        $sql = "INSERT INTO users (username, email, password, is_admin) VALUES (:username, :email, :password, :is_admin)";
        $stmt = oci_parse($GLOBALS['db_conn'], $sql);
        
        if (!$stmt) {
            $error = oci_error($GLOBALS['db_conn']);
            throw new Exception("Error preparing insert SQL: " . $error['message']);
        }
        
        // Set is_admin to 0 for regular users
        $is_admin = 0;
        
        // Bind parameters
        oci_bind_by_name($stmt, ":username", $username);
        oci_bind_by_name($stmt, ":email", $email);
        oci_bind_by_name($stmt, ":password", $hashed_password);
        oci_bind_by_name($stmt, ":is_admin", $is_admin);
        
        $result = oci_execute($stmt, OCI_DEFAULT);
        if (!$result) {
            $error = oci_error($stmt);
            throw new Exception("Error inserting user: " . $error['message']);
        }
        
        // Commit the transaction
        $commit_result = oci_commit($GLOBALS['db_conn']);
        if (!$commit_result) {
            $error = oci_error($GLOBALS['db_conn']);
            throw new Exception("Error committing transaction: " . $error['message']);
        }
        
        error_log("User registered successfully - Username: $username, Email: $email");
        $_SESSION['success_message'] = "Registration successful. Please log in.";
        header("Location: login.php");
        exit;
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        oci_rollback($GLOBALS['db_conn']);
        $_SESSION['error_message'] = "Registration failed: " . $e->getMessage();
        header("Location: register.php");
        exit;
    }
} else {
    // If not a POST request, redirect to register page
    header("Location: register.php");
    exit;
}