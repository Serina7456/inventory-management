<?php
// Database configuration
$dbUsername = 'MASY_QZ921'; // Replace with your Oracle database username
$dbPassword = 'MASY_QZ921'; // Replace with your Oracle database password
$connectionString = 'localhost/app12cXDB'; // Replace with the school's Oracle connection string
$charset = 'AL32UTF8';

// Create connection
try {
    // Create Oracle connection using OCI
    $conn = oci_connect($dbUsername, $dbPassword, $connectionString, $charset);
    
    // Check if connection was successful
    if (!$conn) {
        $e = oci_error();
        throw new Exception($e['message']);
    }
    
    // Function to check if a table exists
    function table_exists($conn, $table_name) {
        $sql = "SELECT COUNT(*) AS count FROM user_tables WHERE table_name = UPPER(:table_name)";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":table_name", $table_name);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        return $row['COUNT'] > 0;
    }
    
    // Create tables if they don't exist
    $tables_created = false;
    
    // Check and create users table if it doesn't exist
    if (!table_exists($conn, 'users')) {
        $tables_created = true;
        $sql = "CREATE TABLE users (
            user_id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            username VARCHAR2(50) NOT NULL UNIQUE,
            password VARCHAR2(255) NOT NULL,
            email VARCHAR2(100) NOT NULL UNIQUE,
            is_admin NUMBER(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);
    }
    
    // Check and create inventory_items table if it doesn't exist
    if (!table_exists($conn, 'inventory_items')) {
        $tables_created = true;
        $sql = "CREATE TABLE inventory_items (
            item_id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            item_name VARCHAR2(100) NOT NULL,
            category VARCHAR2(50) NOT NULL,
            quantity NUMBER DEFAULT 0 NOT NULL,
            description CLOB,
            unit_price NUMBER(10, 2),
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);
    }
    
    // Check and create restock_requests table if it doesn't exist
    if (!table_exists($conn, 'restock_requests')) {
        $tables_created = true;
        $sql = "CREATE TABLE restock_requests (
            request_id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            item_id NUMBER NOT NULL,
            user_id NUMBER NOT NULL,
            quantity_requested NUMBER NOT NULL,
            reason CLOB NOT NULL,
            status VARCHAR2(20) DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
            request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_by NUMBER,
            processed_date TIMESTAMP,
            CONSTRAINT fk_restock_item FOREIGN KEY (item_id) REFERENCES inventory_items(item_id) ON DELETE CASCADE,
            CONSTRAINT fk_restock_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )";
        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);
    }
    
    // Insert admin user if users table was just created
    if ($tables_created) {
        // Check if admin user already exists to avoid duplicates
        $check_sql = "SELECT COUNT(*) AS count FROM users WHERE username = 'admin'";
        $check_stmt = oci_parse($conn, $check_sql);
        oci_execute($check_stmt);
        $row = oci_fetch_assoc($check_stmt);
        
        if ($row['COUNT'] == 0) {
            // Admin user doesn't exist, create it
            // Generate new password hash for admin123
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (username, password, email, is_admin) 
                    VALUES (:username, :password, :email, :is_admin)";
            $stmt = oci_parse($conn, $sql);
            
            $username = 'admin';
            $email = 'admin@example.com';
            $is_admin = 1;
            
            oci_bind_by_name($stmt, ":username", $username);
            oci_bind_by_name($stmt, ":password", $admin_password);
            oci_bind_by_name($stmt, ":email", $email);
            oci_bind_by_name($stmt, ":is_admin", $is_admin);
            
            oci_execute($stmt);
            
            // Commit changes to create the admin user
            oci_commit($conn);
            
            // Insert sample inventory items
            $sample_items = [
                ['Laptop Dell XPS 13', 'Electronics', 15, 'High-performance laptop for professional use', 1299.99],
                ['USB Flash Drive 64GB', 'Electronics', 50, 'High-speed USB 3.0 flash drive', 19.99],
                ['Office Chair', 'Furniture', 25, 'Ergonomic office chair with lumbar support', 149.99],
                ['Whiteboard Marker', 'Office Supplies', 100, 'Dry-erase markers in various colors', 2.49],
                ['Printer Paper A4', 'Office Supplies', 30, 'Package of 500 sheets of A4 printer paper', 5.99]
            ];
            
            $sql = "INSERT INTO inventory_items (item_name, category, quantity, description, unit_price) 
                    VALUES (:item_name, :category, :quantity, :description, :unit_price)";
            
            foreach ($sample_items as $item) {
                $stmt = oci_parse($conn, $sql);
                
                oci_bind_by_name($stmt, ":item_name", $item[0]);
                oci_bind_by_name($stmt, ":category", $item[1]);
                oci_bind_by_name($stmt, ":quantity", $item[2]);
                oci_bind_by_name($stmt, ":description", $item[3]);
                oci_bind_by_name($stmt, ":unit_price", $item[4]);
                
                oci_execute($stmt);
            }
            
            // Commit all changes
            oci_commit($conn);
        }
    }
    
    // Simple helper functions to make working with Oracle easier
    
    // Function to prepare and execute a query
    function oci_query($conn, $sql, $params = []) {
        $stmt = oci_parse($conn, $sql);
        if (!$stmt) {
            $e = oci_error($conn);
            throw new Exception("Query preparation failed: " . $e['message']);
        }
        
        // Bind parameters if any
        foreach ($params as $key => $value) {
            $bindVar = $value;
            if (is_int($key)) {
                // For positional parameters (numeric keys)
                $bindName = ':p' . ($key + 1);
            } else {
                // For named parameters
                $bindName = ':' . $key;
            }
            oci_bind_by_name($stmt, $bindName, $bindVar);
        }
        
        $result = oci_execute($stmt, OCI_DEFAULT);
        if (!$result) {
            $e = oci_error($stmt);
            throw new Exception("Query execution failed: " . $e['message'] . " SQL: " . $sql);
        }
        
        return $stmt;
    }
    
    // Function to fetch all rows as associative array
    function oci_fetch_all_assoc($stmt) {
        $rows = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    // For compatibility with existing code, make these functions available globally
    $GLOBALS['db_conn'] = $conn;
    
} catch(Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}