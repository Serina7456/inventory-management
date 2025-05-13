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

// Get search query if any
$search = $_GET['search'] ?? '';

// Prepare query for inventory items
if (!empty($search)) {
    // Split search terms by spaces for more flexible searching
    $search_terms = explode(' ', $search);
    $search_conditions = [];
    $params = [];
    
    foreach ($search_terms as $index => $term) {
        if (strlen($term) > 0) {
            $term_param = 'term_' . $index;
            $cat_param = 'cat_' . $index;
            $desc_param = 'desc_' . $index;
            
            $search_conditions[] = "(UPPER(item_name) LIKE UPPER(:{$term_param}) OR UPPER(category) LIKE UPPER(:{$cat_param}) OR UPPER(description) LIKE UPPER(:{$desc_param}))";
            $params[$term_param] = "%{$term}%";
            $params[$cat_param] = "%{$term}%";
            $params[$desc_param] = "%{$term}%";
        }
    }
    
    if (!empty($search_conditions)) {
        $sql = "SELECT * FROM inventory_items WHERE " . implode(' OR ', $search_conditions) . " ORDER BY category, item_name";
        $stmt = oci_query($GLOBALS['db_conn'], $sql, $params);
    } else {
        $sql = "SELECT * FROM inventory_items ORDER BY category, item_name";
        $stmt = oci_query($GLOBALS['db_conn'], $sql);
    }
} else {
    $sql = "SELECT * FROM inventory_items ORDER BY category, item_name";
    $stmt = oci_query($GLOBALS['db_conn'], $sql);
}

$items = oci_fetch_all_assoc($stmt);
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Inventory Items</h2>
    </div>
    <div class="col-md-6">
        <form class="d-flex" action="" method="GET">
            <input class="form-control me-2" type="search" name="search" placeholder="Search items or categories" value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-outline-primary" type="submit">Search</button>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="alert alert-info">
        <?php if (!empty($search)): ?>
            No items found matching your search criteria.
        <?php else: ?>
            No inventory items available.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-primary">
                <tr>
                    <th>ID</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Description</th>
                    <th>Unit Price</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo safe_display($item['ITEM_ID']); ?></td>
                        <td><?php echo safe_display($item['ITEM_NAME']); ?></td>
                        <td><?php echo safe_display($item['CATEGORY']); ?></td>
                        <td><?php echo safe_display($item['QUANTITY']); ?></td>
                        <td><?php echo safe_display($item['DESCRIPTION']); ?></td>
                        <td>$<?php echo number_format($item['UNIT_PRICE'], 2); ?></td>
                        <td><?php echo safe_display($item['LAST_UPDATED']); ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $item['ITEM_ID']; ?>" class="btn btn-sm btn-info">View</a>
                            <a href="request_restock.php?id=<?php echo $item['ITEM_ID']; ?>" class="btn btn-sm btn-warning">Request Restock</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
// Include footer
include_once '../includes/footer.php';
?>