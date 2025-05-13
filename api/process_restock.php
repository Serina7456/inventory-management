<?php
// Start session
session_start();

// Check for admin authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/database.php';

// Set header to JSON
header('Content-Type: application/json');

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get POST data
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Debug information
error_log("Processing restock request - Request ID: $request_id, Action: $action, Admin ID: " . $_SESSION['user_id']);

// Validate inputs
if ($request_id <= 0 || ($action !== 'approve' && $action !== 'reject')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request parameters']);
    exit;
}

try {
    // Get request details
    $sql = "SELECT r.*, i.quantity as current_quantity 
            FROM restock_requests r 
            JOIN inventory_items i ON r.item_id = i.item_id 
            WHERE r.request_id = :request_id";
    
    $request_id_str = (string)$request_id;
    $stmt = oci_parse($GLOBALS['db_conn'], $sql);
    oci_bind_by_name($stmt, ":request_id", $request_id_str);
    oci_execute($stmt);
    $request = oci_fetch_assoc($stmt);
    
    if (!$request) {
        oci_rollback($GLOBALS['db_conn']);
        http_response_code(404);
        echo json_encode(['error' => 'Restock request not found']);
        exit;
    }
    
    // Check if request is already processed
    if ($request['STATUS'] !== 'pending') {
        oci_rollback($GLOBALS['db_conn']);
        http_response_code(400);
        echo json_encode(['error' => 'This request has already been ' . $request['STATUS']]);
        exit;
    }
    
    // Convert user ID to string
    $user_id_str = (string)$_SESSION['user_id'];
    
    // Process based on action
    if ($action === 'approve') {
        // Update request status
        $sql = "UPDATE restock_requests SET status = 'approved', processed_by = :user_id, processed_date = SYSDATE WHERE request_id = :request_id";
        $stmt = oci_parse($GLOBALS['db_conn'], $sql);
        oci_bind_by_name($stmt, ":user_id", $user_id_str);
        oci_bind_by_name($stmt, ":request_id", $request_id_str);
        $execute_result = oci_execute($stmt);
        
        if (!$execute_result) {
            $error = oci_error($stmt);
            error_log("Error updating request status: " . print_r($error, true));
            throw new Exception("Error updating request status: " . $error['message']);
        }
        
        // Update inventory quantity - Calculate new quantity and convert to string
        $current_quantity = (int)$request['CURRENT_QUANTITY'];
        $quantity_requested = (int)$request['QUANTITY_REQUESTED'];
        $new_quantity = $current_quantity + $quantity_requested;
        $new_quantity_str = (string)$new_quantity;
        $item_id_str = (string)$request['ITEM_ID'];
        
        error_log("Updating inventory - Item ID: {$request['ITEM_ID']}, Current quantity: $current_quantity, Requested quantity: $quantity_requested, New quantity: $new_quantity");
        
        $sql = "UPDATE inventory_items SET quantity = :quantity WHERE item_id = :item_id";
        $stmt = oci_parse($GLOBALS['db_conn'], $sql);
        oci_bind_by_name($stmt, ":quantity", $new_quantity_str);
        oci_bind_by_name($stmt, ":item_id", $item_id_str);
        $execute_result = oci_execute($stmt);
        
        if (!$execute_result) {
            $error = oci_error($stmt);
            error_log("Error updating inventory quantity: " . print_r($error, true));
            throw new Exception("Error updating inventory quantity: " . $error['message']);
        }
        
        $result = ['success' => true, 'message' => 'Restock request approved and inventory updated'];
    } else {
        // Reject the request
        $sql = "UPDATE restock_requests SET status = 'rejected', processed_by = :user_id, processed_date = SYSDATE WHERE request_id = :request_id";
        $stmt = oci_parse($GLOBALS['db_conn'], $sql);
        oci_bind_by_name($stmt, ":user_id", $user_id_str);
        oci_bind_by_name($stmt, ":request_id", $request_id_str);
        $execute_result = oci_execute($stmt);
        
        if (!$execute_result) {
            $error = oci_error($stmt);
            error_log("Error rejecting request: " . print_r($error, true));
            throw new Exception("Error rejecting request: " . $error['message']);
        }
        
        $result = ['success' => true, 'message' => 'Restock request rejected'];
    }
    
    // Commit transaction
    $commit_result = oci_commit($GLOBALS['db_conn']);
    if (!$commit_result) {
        $error = oci_error($GLOBALS['db_conn']);
        error_log("Error committing transaction: " . print_r($error, true));
        throw new Exception("Error committing transaction: " . $error['message']);
    }
    
    error_log("Restock request processed successfully: " . ($action === 'approve' ? 'Approved' : 'Rejected'));
    
    // Return success response
    echo json_encode($result);
    
} catch (Exception $e) {
    // Rollback the transaction on error
    oci_rollback($GLOBALS['db_conn']);
    
    error_log("Failed to process restock request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>