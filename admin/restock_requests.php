<?php
// Include admin authentication check
require_once '../includes/admin_check.php';

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

// Process request action if any
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $request_id = $_GET['id'];
    $action = $_GET['action'];
    
    // Debug information
    error_log("Processing restock request - Request ID: $request_id, Action: $action, Admin ID: " . $_SESSION['user_id']);
    
    try {
        // Check if request exists
        $sql = "SELECT r.*, i.item_name, i.quantity as current_quantity 
                FROM restock_requests r 
                JOIN inventory_items i ON r.item_id = i.item_id 
                WHERE r.request_id = :request_id";
        
        // Use string form of request_id
        $request_id_str = (string)$request_id;
        $stmt = oci_parse($GLOBALS['db_conn'], $sql);
        oci_bind_by_name($stmt, ":request_id", $request_id_str);
        oci_execute($stmt);
        $request = oci_fetch_assoc($stmt);
        
        if (!$request) {
            $_SESSION['error_message'] = "Restock request not found.";
            header("Location: restock_requests.php");
            exit;
        }
        
        // If request is already processed, don't allow re-processing
        if ($request['STATUS'] !== 'pending') {
            $_SESSION['error_message'] = "This request has already been " . $request['STATUS'] . ".";
            header("Location: restock_requests.php");
            exit;
        }
        
        // Convert user ID to string
        $user_id_str = (string)$_SESSION['user_id'];
        
        // Process the action
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
            
            // Commit the transaction
            $commit_result = oci_commit($GLOBALS['db_conn']);
            if (!$commit_result) {
                $error = oci_error($GLOBALS['db_conn']);
                error_log("Transaction commit error: " . print_r($error, true));
                throw new Exception("Transaction commit error: " . $error['message']);
            }
            
            error_log("Restock request approved successfully: Request ID $request_id");
            $_SESSION['success_message'] = "Restock request approved and inventory updated.";
        } elseif ($action === 'reject') {
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
            
            // Commit the transaction
            $commit_result = oci_commit($GLOBALS['db_conn']);
            if (!$commit_result) {
                $error = oci_error($GLOBALS['db_conn']);
                error_log("Transaction commit error: " . print_r($error, true));
                throw new Exception("Transaction commit error: " . $error['message']);
            }
            
            error_log("Restock request rejected successfully: Request ID $request_id");
            $_SESSION['success_message'] = "Restock request rejected.";
        }
        
        header("Location: restock_requests.php");
        exit;
    } catch (Exception $e) {
        // Rollback in case of error
        oci_rollback($GLOBALS['db_conn']);
        error_log("Failed to process restock request: " . $e->getMessage());
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    }
}

// Show request details if ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $request_id = $_GET['id'];
    
    try {
        // Get request details
        $sql = "SELECT r.*, 
                    i.item_name, 
                    i.category, 
                    i.quantity as current_quantity, 
                    u.username, 
                    TO_CHAR(r.request_date, 'YYYY-MM-DD HH24:MI:SS') as formatted_date,
                    TO_CHAR(r.processed_date, 'YYYY-MM-DD HH24:MI:SS') as formatted_processed_date
                FROM restock_requests r
                JOIN inventory_items i ON r.item_id = i.item_id
                JOIN users u ON r.user_id = u.user_id
                WHERE r.request_id = :request_id";
        $stmt = oci_query($GLOBALS['db_conn'], $sql, ['request_id' => $request_id]);
        $request = oci_fetch_assoc($stmt);
        
        if (!$request) {
            $_SESSION['error_message'] = "Restock request not found.";
            header("Location: restock_requests.php");
            exit;
        }
        
        // If request was processed, get processor's name
        $processor_name = null;
        if (!empty($request['PROCESSED_BY'])) {
            $sql = "SELECT username FROM users WHERE user_id = :user_id";
            $stmt = oci_query($GLOBALS['db_conn'], $sql, ['user_id' => $request['PROCESSED_BY']]);
            $processor = oci_fetch_assoc($stmt);
            if ($processor) {
                $processor_name = $processor['USERNAME'];
            }
        }
?>

<h2 class="mb-4">Restock Request Details</h2>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card mb-4">
            <div class="card-header <?php echo $request['STATUS'] === 'pending' ? 'bg-warning' : ($request['STATUS'] === 'approved' ? 'bg-success' : 'bg-danger'); ?> text-white">
                <h4 class="mb-0">Request #<?php echo $request_id; ?> - <?php echo ucfirst($request['STATUS']); ?></h4>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Item Name:</div>
                    <div class="col-md-8"><?php echo safe_display($request['ITEM_NAME']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Category:</div>
                    <div class="col-md-8"><?php echo safe_display($request['CATEGORY']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Current Quantity:</div>
                    <div class="col-md-8"><?php echo safe_display($request['CURRENT_QUANTITY']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Requested Quantity:</div>
                    <div class="col-md-8"><?php echo safe_display($request['QUANTITY_REQUESTED']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Requested By:</div>
                    <div class="col-md-8"><?php echo safe_display($request['USERNAME']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Request Date:</div>
                    <div class="col-md-8"><?php 
                        echo safe_display($request['FORMATTED_DATE']); 
                    ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Reason:</div>
                    <div class="col-md-8"><?php echo safe_display($request['REASON']); ?></div>
                </div>
                
                <?php if ($request['STATUS'] !== 'pending'): ?>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Processed By:</div>
                    <div class="col-md-8"><?php echo safe_display($processor_name ?? 'Unknown'); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Processed Date:</div>
                    <div class="col-md-8"><?php echo safe_display($request['FORMATTED_PROCESSED_DATE']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="restock_requests.php" class="btn btn-secondary">Back to All Requests</a>
                
                <?php if ($request['STATUS'] === 'pending'): ?>
                <div>
                    <a href="restock_requests.php?id=<?php echo $request_id; ?>&action=reject" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this request?')">Reject</a>
                    <a href="restock_requests.php?id=<?php echo $request_id; ?>&action=approve" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this request?')">Approve</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error retrieving request details: ' . $e->getMessage() . '</div>';
    }
} else {
    // Show all restock requests
    try {
        // Get filter values
        $status_filter = $_GET['status'] ?? 'all';
        
        // Build query based on filters
        $query = "
            SELECT r.*, 
                i.item_name, 
                u.username,
                TO_CHAR(r.request_date, 'YYYY-MM-DD') as formatted_date
            FROM restock_requests r
            JOIN inventory_items i ON r.item_id = i.item_id
            JOIN users u ON r.user_id = u.user_id
        ";
        
        $params = [];
        
        if ($status_filter !== 'all') {
            $query .= " WHERE r.status = :status";
            $params['status'] = $status_filter;
        }
        
        $query .= " ORDER BY r.request_date DESC";
        
        $stmt = oci_query($GLOBALS['db_conn'], $query, $params);
        $requests = oci_fetch_all_assoc($stmt);
?>

<h2 class="mb-4">Restock Requests</h2>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">All Requests</h5>
            <form action="" method="GET" class="d-flex">
                <select class="form-select me-2" name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-light">Filter</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <div class="alert alert-info">No restock requests found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>User</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo safe_display($request['REQUEST_ID']); ?></td>
                                <td><?php echo safe_display($request['FORMATTED_DATE']); ?></td>
                                <td><?php echo safe_display($request['USERNAME']); ?></td>
                                <td><?php echo safe_display($request['ITEM_NAME']); ?></td>
                                <td><?php echo safe_display($request['QUANTITY_REQUESTED']); ?></td>
                                <td>
                                    <?php if ($request['STATUS'] == 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php elseif ($request['STATUS'] == 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="restock_requests.php?id=<?php echo $request['REQUEST_ID']; ?>" class="btn btn-sm btn-info">Details</a>
                                    
                                    <?php if ($request['STATUS'] == 'pending'): ?>
                                        <a href="restock_requests.php?id=<?php echo $request['REQUEST_ID']; ?>&action=approve" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this request?')">Approve</a>
                                        <a href="restock_requests.php?id=<?php echo $request['REQUEST_ID']; ?>&action=reject" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this request?')">Reject</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error retrieving restock requests: ' . $e->getMessage() . '</div>';
    }
}

// Include footer
include_once '../includes/footer.php';
?>