<?php
// Start session
session_start();

// Include header
include_once 'includes/header.php';
?>

<div class="jumbotron text-center bg-light p-5 rounded-3 mb-4">
    <h1 class="display-4">Inventory Management System</h1>
    <p class="lead">A simple and efficient way to manage your inventory</p>
    <?php if(!isset($_SESSION['user_id'])): ?>
        <p class="mt-4">
            <a href="auth/login.php" class="btn btn-primary btn-lg me-2">Login</a>
            <a href="auth/register.php" class="btn btn-outline-secondary btn-lg">Register</a>
        </p>
    <?php else: ?>
        <p class="mt-4">
            <a href="inventory/index.php" class="btn btn-primary btn-lg">View Inventory</a>
        </p>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-boxes fa-3x mb-3 text-primary"></i>
                <h5 class="card-title">Track Inventory</h5>
                <p class="card-text">Keep track of all your items in one place with detailed information and categories.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-search fa-3x mb-3 text-primary"></i>
                <h5 class="card-title">Quick Search</h5>
                <p class="card-text">Find items quickly with our powerful search functionality.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-list fa-3x mb-3 text-primary"></i>
                <h5 class="card-title">Restock Requests</h5>
                <p class="card-text">Submit and track restock requests when inventory runs low.</p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>