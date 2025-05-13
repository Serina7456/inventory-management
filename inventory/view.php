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
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Item Details</h4>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Item Name:</div>
                    <div class="col-md-8"><?php echo safe_display($item['ITEM_NAME']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Category:</div>
                    <div class="col-md-8"><?php echo safe_display($item['CATEGORY']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Quantity:</div>
                    <div class="col-md-8"><?php echo safe_display($item['QUANTITY']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Description:</div>
                    <div class="col-md-8"><?php echo safe_display($item['DESCRIPTION']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Unit Price:</div>
                    <div class="col-md-8">$<?php echo number_format($item['UNIT_PRICE'], 2); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Last Updated:</div>
                    <div class="col-md-8"><?php echo safe_display($item['LAST_UPDATED']); ?></div>
                </div>
            </div>
            <div class="card-footer">
                <a href="index.php" class="btn btn-secondary">Back to Inventory</a>
                <a href="request_restock.php?id=<?php echo $item_id; ?>" class="btn btn-warning">Request Restock</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>