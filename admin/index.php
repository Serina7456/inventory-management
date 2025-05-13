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

// Get dashboard statistics
try {
    // Count total inventory items
    $sql = "SELECT COUNT(*) AS total FROM inventory_items";
    $stmt = oci_query($GLOBALS['db_conn'], $sql);
    $row = oci_fetch_assoc($stmt);
    $total_items = $row['TOTAL'];
    
    // Count items with low stock (less than 10)
    $sql = "SELECT COUNT(*) AS low_count FROM inventory_items WHERE quantity < 10";
    $stmt = oci_query($GLOBALS['db_conn'], $sql);
    $row = oci_fetch_assoc($stmt);
    $low_stock_items = $row['LOW_COUNT'];
    
    // Count pending restock requests
    $sql = "SELECT COUNT(*) AS pending_count FROM restock_requests WHERE status = 'pending'";
    $stmt = oci_query($GLOBALS['db_conn'], $sql);
    $row = oci_fetch_assoc($stmt);
    $pending_requests = $row['PENDING_COUNT'];
    
    // Get recent restock requests
    $sql = "SELECT r.*, i.item_name, u.username, 
            TO_CHAR(r.request_date, 'YYYY-MM-DD') as formatted_date
            FROM (SELECT * FROM restock_requests ORDER BY request_date DESC) r
            JOIN inventory_items i ON r.item_id = i.item_id
            JOIN users u ON r.user_id = u.user_id
            WHERE ROWNUM <= 5";
    $stmt = oci_query($GLOBALS['db_conn'], $sql);
    $recent_requests = oci_fetch_all_assoc($stmt);
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error fetching dashboard data: " . $e->getMessage();
}
?>

<h2 class="mb-4">Admin Dashboard</h2>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Total Inventory Items</h5>
                <p class="card-text display-4"><?php echo $total_items; ?></p>
                <a href="manage_items.php" class="btn btn-light">Manage Items</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Low Stock Items</h5>
                <p class="card-text display-4"><?php echo $low_stock_items; ?></p>
                <a href="#low-stock" class="btn btn-light" data-bs-toggle="collapse">View Details</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">Pending Restock Requests</h5>
                <p class="card-text display-4"><?php echo $pending_requests; ?></p>
                <a href="restock_requests.php" class="btn btn-light">View All</a>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Items Section -->
<div id="low-stock" class="collapse mb-4">
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h5 class="mb-0">Low Stock Items (Less than 10)</h5>
        </div>
        <div class="card-body">
            <?php
            try {
                $sql = "SELECT * FROM inventory_items WHERE quantity < 10 ORDER BY quantity ASC";
                $stmt = oci_query($GLOBALS['db_conn'], $sql);
                $low_items = oci_fetch_all_assoc($stmt);
                
                if (count($low_items) > 0):
            ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_items as $item): ?>
                                <tr>
                                    <td><?php echo safe_display($item['ITEM_NAME']); ?></td>
                                    <td><?php echo safe_display($item['CATEGORY']); ?></td>
                                    <td><?php echo safe_display($item['QUANTITY']); ?></td>
                                    <td>
                                        <a href="edit_item.php?id=<?php echo $item['ITEM_ID']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="mb-0">No low stock items found.</p>
            <?php 
                endif;
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Error fetching low stock items: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Recent Restock Requests Section -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Recent Restock Requests</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($recent_requests)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $request): ?>
                            <tr>
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="mb-0">No recent restock requests found.</p>
        <?php endif; ?>
    </div>
    <div class="card-footer">
        <a href="restock_requests.php" class="btn btn-info">View All Requests</a>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Admin Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="manage_items.php" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Manage All Items
                    </a>
                    <a href="../inventory/index.php" class="btn btn-success">
                        <i class="fas fa-list me-2"></i>View Inventory
                    </a>
                    <a href="restock_requests.php" class="btn btn-info">
                        <i class="fas fa-clipboard me-2"></i>Manage Restock Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>