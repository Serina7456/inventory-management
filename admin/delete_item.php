<?php
// Include admin authentication check
require_once '../includes/admin_check.php';

// Include database connection
require_once '../config/database.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid item ID.";
    header("Location: manage_items.php");
    exit;
}

$item_id = $_GET['id'];

// Delete the item if confirmed
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    try {
        // Check if there are any pending restock requests for this item
        $sql = "SELECT COUNT(*) AS pending_count FROM restock_requests WHERE item_id = :item_id AND status = 'pending'";
        $stmt = oci_query($GLOBALS['db_conn'], $sql, ['item_id' => $item_id]);
        $row = oci_fetch_assoc($stmt);
        $pending_requests = $row['PENDING_COUNT'];
        
        if ($pending_requests > 0) {
            $_SESSION['error_message'] = "Cannot delete item with pending restock requests. Please process those requests first.";
            header("Location: manage_items.php");
            exit;
        }
        
        // Delete the item
        $sql = "DELETE FROM inventory_items WHERE item_id = :item_id";
        $stmt = oci_query($GLOBALS['db_conn'], $sql, ['item_id' => $item_id]);
        
        // Commit the transaction
        oci_commit($GLOBALS['db_conn']);
        
        $_SESSION['success_message'] = "Item deleted successfully.";
        header("Location: manage_items.php");
        exit;
    } catch (Exception $e) {
        // Rollback in case of error
        oci_rollback($GLOBALS['db_conn']);
        $_SESSION['error_message'] = "Error deleting item: " . $e->getMessage();
        header("Location: manage_items.php");
        exit;
    }
} else {
    // Get item details for confirmation
    try {
        $sql = "SELECT item_name FROM inventory_items WHERE item_id = :item_id";
        $stmt = oci_query($GLOBALS['db_conn'], $sql, ['item_id' => $item_id]);
        $item = oci_fetch_assoc($stmt);
        
        if (!$item) {
            $_SESSION['error_message'] = "Item not found.";
            header("Location: manage_items.php");
            exit;
        }
        
        // Include header
        include_once '../includes/header.php';
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error retrieving item: " . $e->getMessage();
        header("Location: manage_items.php");
        exit;
    }
}
?>

<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">Delete Confirmation</h4>
            </div>
            <div class="card-body">
                <p class="mb-3">Are you sure you want to delete the item: <strong><?php echo htmlspecialchars($item['ITEM_NAME']); ?></strong>?</p>
                <p class="text-danger mb-3">This action cannot be undone.</p>
                
                <div class="d-flex justify-content-between">
                    <a href="manage_items.php" class="btn btn-secondary">Cancel</a>
                    <a href="delete_item.php?id=<?php echo $item_id; ?>&confirm=yes" class="btn btn-danger">Yes, Delete Item</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>