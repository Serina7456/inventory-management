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

// Get search query if any
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

// Get all categories for filter
try {
    $sql = "SELECT DISTINCT category FROM inventory_items ORDER BY category";
    $stmt = oci_query($GLOBALS['db_conn'], $sql);
    $categories = oci_fetch_all_assoc($stmt);
} catch (Exception $e) {
    $categories = [];
    $_SESSION['error_message'] = "Error fetching categories: " . $e->getMessage();
}

// Prepare query for inventory items
try {
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        // Split search terms by spaces for more flexible searching
        $search_terms = explode(' ', $search);
        $search_conditions = [];
        
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
            $conditions[] = "(" . implode(' OR ', $search_conditions) . ")";
        }
    }
    
    if (!empty($category_filter)) {
        $conditions[] = "category = :category_filter";
        $params['category_filter'] = $category_filter;
    }
    
    $sql = "SELECT * FROM inventory_items";
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY category, item_name";
    
    $stmt = oci_query($GLOBALS['db_conn'], $sql, $params);
    $items = oci_fetch_all_assoc($stmt);
} catch (Exception $e) {
    $items = [];
    $_SESSION['error_message'] = "Error retrieving inventory items: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Manage Inventory Items</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="add_item.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-2"></i>Add New Item
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Search and Filter</h5>
    </div>
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, category or description" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['CATEGORY']; ?>" <?php echo $category_filter === $cat['CATEGORY'] ? 'selected' : ''; ?>>
                            <?php echo $cat['CATEGORY']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="alert alert-info">
        <?php if (!empty($search) || !empty($category_filter)): ?>
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
                            <a href="edit_item.php?id=<?php echo $item['ITEM_ID']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <a href="delete_item.php?id=<?php echo $item['ITEM_ID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
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