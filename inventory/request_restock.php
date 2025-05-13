<?php
// Include authentication check
require_once '../includes/auth_check.php';

// Include database connection
require_once '../config/database.php';

// Include header
include_once '../includes/header.php';

// Helper function to safely display CLOB or string content
function safe_display($value) {
    if (is_object($value) && method_exists($value, 'load')) {
        // Handle CLOB objects
        $content = $value->load();
        return htmlspecialchars($content);
    } else {
        // Handle regular strings
        return htmlspecialchars($value);
    }
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid item ID.";
    header("Location: index.php");
    exit;
}

$item_id = $_GET['id'];

// Get item details
try {
    $sql = "SELECT * FROM inventory_items WHERE item_id = :item_id";
    $stmt = oci_query($GLOBALS['db_conn'], $sql, ['item_id' => $item_id]);
    $item = oci_fetch_assoc($stmt);
    
    if (!$item) {
        $_SESSION['error_message'] = "Item not found.";
        header("Location: index.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error retrieving item: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $reason = trim($_POST['reason'] ?? '');
    
    // Debug information
    error_log("Submitting restock request - Item ID: $item_id, Quantity: $quantity, User ID: " . $_SESSION['user_id']);
    
    // Validate input
    if ($quantity <= 0) {
        $_SESSION['error_message'] = "Please enter a valid quantity.";
    } elseif (empty($reason)) {
        $_SESSION['error_message'] = "Please provide a reason for the restock request.";
    } else {
        try {
            // Get formatted current time string
            $now = date('Y-m-d H:i:s');
            error_log("Current time: $now");
            
            // Use simpler method - first insert data without CLOB field
            $sql = "INSERT INTO restock_requests (item_id, user_id, quantity_requested, reason, request_date) 
                    VALUES (:item_id, :user_id, :quantity, EMPTY_CLOB(), TO_TIMESTAMP(:req_date, 'YYYY-MM-DD HH24:MI:SS'))
                    RETURNING request_id INTO :request_id";
            
            $stmt = oci_parse($GLOBALS['db_conn'], $sql);
            if (!$stmt) {
                $error = oci_error($GLOBALS['db_conn']);
                error_log("SQL parsing error: " . print_r($error, true));
                throw new Exception("SQL parsing error: " . $error['message']);
            }
            
            // Convert values to strings to avoid type issues
            $item_id_str = (string)$item_id;
            $user_id_str = (string)$_SESSION['user_id'];
            $quantity_str = (string)$quantity;
            $request_id = 0;
            
            // Bind parameters
            oci_bind_by_name($stmt, ":item_id", $item_id_str);
            oci_bind_by_name($stmt, ":user_id", $user_id_str);
            oci_bind_by_name($stmt, ":quantity", $quantity_str);
            oci_bind_by_name($stmt, ":req_date", $now);
            oci_bind_by_name($stmt, ":request_id", $request_id, 32, SQLT_INT);
            
            // Execute statement
            $execute_result = oci_execute($stmt, OCI_DEFAULT);
            if (!$execute_result) {
                $error = oci_error($stmt);
                error_log("SQL execution error: " . print_r($error, true));
                throw new Exception("SQL execution error: " . $error['message'] . ", SQL: " . $sql);
            }
            
            // Then update CLOB field using obtained request ID
            $sql = "SELECT reason FROM restock_requests WHERE request_id = :request_id FOR UPDATE";
            $stmt = oci_parse($GLOBALS['db_conn'], $sql);
            oci_bind_by_name($stmt, ":request_id", $request_id);
            $execute_result = oci_execute($stmt, OCI_DEFAULT);
            
            if (!$execute_result) {
                $error = oci_error($stmt);
                error_log("CLOB query error: " . print_r($error, true));
                throw new Exception("CLOB query error: " . $error['message']);
            }
            
            // Get CLOB field
            $row = oci_fetch_assoc($stmt);
            $clob = $row['REASON'];
            
            // Write CLOB data
            $sql = "UPDATE restock_requests SET reason = :reason WHERE request_id = :request_id";
            $stmt = oci_parse($GLOBALS['db_conn'], $sql);
            oci_bind_by_name($stmt, ":reason", $reason);
            oci_bind_by_name($stmt, ":request_id", $request_id);
            $execute_result = oci_execute($stmt, OCI_DEFAULT);
            
            if (!$execute_result) {
                $error = oci_error($stmt);
                error_log("CLOB update error: " . print_r($error, true));
                throw new Exception("CLOB update error: " . $error['message']);
            }
            
            // Commit transaction
            $commit_result = oci_commit($GLOBALS['db_conn']);
            if (!$commit_result) {
                $error = oci_error($GLOBALS['db_conn']);
                error_log("Transaction commit error: " . print_r($error, true));
                throw new Exception("Transaction commit error: " . $error['message']);
            }
            
            error_log("Restock request submitted successfully: Item ID $item_id, Quantity $quantity, Request ID: $request_id");
            $_SESSION['success_message'] = "Restock request submitted successfully.";
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            // Rollback in case of error
            oci_rollback($GLOBALS['db_conn']);
            error_log("Restock request submission failed: " . $e->getMessage());
            $_SESSION['error_message'] = "Error submitting request: " . $e->getMessage();
        }
    }
}
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Request Restock</h4>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Item Name:</div>
                    <div class="col-md-8"><?php echo safe_display($item['ITEM_NAME']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Current Quantity:</div>
                    <div class="col-md-8"><?php echo safe_display($item['QUANTITY']); ?></div>
                </div>
                
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity to Request</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Request</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>