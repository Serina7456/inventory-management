<?php
// Start session
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Include header
include_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Register</h4>
            </div>
            <div class="card-body">
                <form action="process_register.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="form-text text-muted">Password must be at least 8 characters and contain both letters and numbers.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <small class="form-text text-muted">Please enter the same password again.</small>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    form.addEventListener('submit', function(event) {
        // Reset any previous error messages
        const errorElements = document.querySelectorAll('.text-danger');
        errorElements.forEach(element => element.remove());
        
        let hasError = false;
        
        // Check password length
        if (password.value.length < 8) {
            displayError(password, 'Password must be at least 8 characters');
            hasError = true;
        }
        
        // Check if password contains letters
        if (!/[a-zA-Z]/.test(password.value)) {
            displayError(password, 'Password must contain at least one letter');
            hasError = true;
        }
        
        // Check if password contains numbers
        if (!/[0-9]/.test(password.value)) {
            displayError(password, 'Password must contain at least one number');
            hasError = true;
        }
        
        // Check if passwords match
        if (password.value !== confirmPassword.value) {
            displayError(confirmPassword, 'Passwords do not match');
            hasError = true;
        }
        
        if (hasError) {
            event.preventDefault();
        }
    });
    
    function displayError(inputElement, message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'text-danger mt-1';
        errorElement.textContent = message;
        inputElement.parentNode.appendChild(errorElement);
    }
});
</script>