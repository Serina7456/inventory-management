<?php
// Include admin authentication check
require_once '../includes/admin_check.php';

// Include database connection
require_once '../config/database.php';

// Include header
include_once '../includes/header.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid item ID.";
    header("Location: manage_items.php");
    exit;
}

$item_id = $_GET['id'];

// Initialize variables
$item = null;
$categories = [];

// Get existing categories
try {
    $sql = "SELECT DISTINCT category FROM inventory_items ORDER BY category";
    $stmt = oci_query($GLOBALS['db_conn'], $sql);
    $results = oci_fetch_all_assoc($stmt);
    
    foreach ($results as $row) {
        $categories[] = $row['CATEGORY'];
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error fetching categories: " . $e->getMessage();
}

// Get item details
try {
    $sql = "SELECT * FROM inventory_items WHERE item_id = :item_id";
    $stmt = oci_query($GLOBALS['db_conn'], $sql, ['item_id' => $item_id]);
    $item = oci_fetch_assoc($stmt);
    
    if (!$item) {
        $_SESSION['error_message'] = "Item not found.";
        header("Location: manage_items.php");
        exit;
    }
    
    // Properly handle CLOB type description
    if (is_object($item['DESCRIPTION']) && method_exists($item['DESCRIPTION'], 'load')) {
        $item['DESCRIPTION'] = $item['DESCRIPTION']->load();
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error retrieving item: " . $e->getMessage();
    header("Location: manage_items.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $description = trim($_POST['description'] ?? '');
    $unit_price = isset($_POST['unit_price']) ? (float)$_POST['unit_price'] : 0.00;
    
    // Debug information
    error_log("Editing item ID: $item_id - Name: $item_name, Category: $category, Quantity: $quantity, Price: $unit_price");
    
    // Validate input
    if (empty($item_name)) {
        $_SESSION['error_message'] = "Item name is required.";
    } elseif (empty($category)) {
        $_SESSION['error_message'] = "Category is required.";
    } elseif (!is_numeric($quantity) || $quantity < 0) {
        $_SESSION['error_message'] = "Quantity must be a non-negative number.";
    } elseif (!is_numeric($unit_price) || $unit_price < 0) {
        $_SESSION['error_message'] = "Unit price must be a non-negative number.";
    } else {
        try {
            // Use simplest method to update item without description
            $sql = "UPDATE inventory_items 
                    SET item_name = :item_name, 
                        category = :category, 
                        quantity = :quantity, 
                        unit_price = :unit_price 
                    WHERE item_id = :item_id";
            
            $stmt = oci_parse($GLOBALS['db_conn'], $sql);
            if (!$stmt) {
                $error = oci_error($GLOBALS['db_conn']);
                error_log("SQL parsing error: " . print_r($error, true));
                throw new Exception("SQL parsing error: " . $error['message']);
            }
            
            // Convert values to strings to avoid type issues
            $quantity_str = (string)$quantity;
            $unit_price_str = number_format($unit_price, 2, '.', '');
            $item_id_str = (string)$item_id;
            
            // Bind parameters
            oci_bind_by_name($stmt, ":item_name", $item_name);
            oci_bind_by_name($stmt, ":category", $category);
            oci_bind_by_name($stmt, ":quantity", $quantity_str);
            oci_bind_by_name($stmt, ":unit_price", $unit_price_str);
            oci_bind_by_name($stmt, ":item_id", $item_id_str);
            
            // Execute statement
            $execute_result = oci_execute($stmt);
            if (!$execute_result) {
                $error = oci_error($stmt);
                error_log("SQL execution error: " . print_r($error, true));
                throw new Exception("SQL execution error: " . $error['message'] . ", SQL: " . $sql);
            }
            
            // If description exists, update separately
            if (isset($description)) {
                $sql = "UPDATE inventory_items SET description = :description WHERE item_id = :item_id";
                
                $stmt = oci_parse($GLOBALS['db_conn'], $sql);
                
                // Bind parameters
                oci_bind_by_name($stmt, ":description", $description);
                oci_bind_by_name($stmt, ":item_id", $item_id_str);
                
                $execute_result = oci_execute($stmt);
                if (!$execute_result) {
                    $error = oci_error($stmt);
                    error_log("Description update error: " . print_r($error, true));
                    throw new Exception("Description update error: " . $error['message']);
                }
            }
            
            // Commit transaction
            $commit_result = oci_commit($GLOBALS['db_conn']);
            if (!$commit_result) {
                $error = oci_error($GLOBALS['db_conn']);
                error_log("Transaction commit error: " . print_r($error, true));
                throw new Exception("Transaction commit error: " . $error['message']);
            }
            
            error_log("Item edited successfully: $item_name (ID: $item_id)");
            $_SESSION['success_message'] = "Item updated successfully.";
            header("Location: manage_items.php");
            exit;
        } catch (Exception $e) {
            // Rollback in case of error
            oci_rollback($GLOBALS['db_conn']);
            error_log("Failed to edit item: " . $e->getMessage());
            $_SESSION['error_message'] = "Error updating item: " . $e->getMessage();
        }
    }
}
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Edit Inventory Item</h4>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="item_name" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="item_name" name="item_name" value="<?php echo htmlspecialchars($item['ITEM_NAME']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="category" name="category" list="category-list" value="<?php echo htmlspecialchars($item['CATEGORY']); ?>" required>
                            <datalist id="category-list">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <small class="text-muted">Choose an existing category or enter a new one</small>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="<?php echo htmlspecialchars($item['QUANTITY']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($item['DESCRIPTION']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="unit_price" class="form-label">Unit Price ($)</label>
                        <input type="number" class="form-control" id="unit_price" name="unit_price" min="0" step="0.01" value="<?php echo htmlspecialchars($item['UNIT_PRICE']); ?>" required>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="manage_items.php" class="btn btn-secondary">Cancel</a>
                        <div>
                            <a href="delete_item.php?id=<?php echo $item_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                            <button type="submit" class="btn btn-primary">Update Item</button>
                        </div>
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