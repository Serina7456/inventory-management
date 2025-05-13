/**
 * Main JavaScript file for Inventory Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-close alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Live search functionality for inventory
    const searchInput = document.getElementById('live-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            const searchValue = this.value.trim();
            liveSearch(searchValue);
        }, 300));
    }
    
    // Handle category filter change
    const categoryFilter = document.getElementById('category-filter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            const category = this.value;
            const searchValue = searchInput ? searchInput.value.trim() : '';
            liveSearch(searchValue, category);
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Add quantity highlighting
    highlightQuantities();
    
    // Form validations
    validateForms();
    
    // Handle restock request approval/rejection via AJAX
    setupRestockActions();
});

/**
 * Live search function using AJAX
 * @param {string} query - Search query
 * @param {string} category - Category filter
 */
function liveSearch(query, category = '') {
    // Only proceed if the search container exists
    const searchResultsContainer = document.getElementById('search-results');
    if (!searchResultsContainer) return;
    
    // If both query and category are empty, don't perform search
    if (query === '' && category === '') {
        searchResultsContainer.innerHTML = '<div class="alert alert-info">Please enter search term or select category</div>';
        return;
    }
    
    // Show loading indicator
    searchResultsContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    // Prepare data for the AJAX request
    const data = new FormData();
    data.append('query', query);
    data.append('category', category);
    
    // Send AJAX request
    fetch('/search', {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(data => {
        if (data.items && data.items.length > 0) {
            displaySearchResults(data.items, searchResultsContainer);
        } else {
            searchResultsContainer.innerHTML = '<div class="alert alert-warning">No matching items found</div>';
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        searchResultsContainer.innerHTML = '<div class="alert alert-danger">Search error, please try again later</div>';
    });
}

/**
 * Display search results in the container
 * @param {Array} items - Array of item objects
 * @param {HTMLElement} container - Container element
 */
function displaySearchResults(items, container) {
    let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
    html += '<thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Quantity</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    
    items.forEach(item => {
        const quantityClass = item.quantity <= item.reorder_point ? 'text-danger' : 
                             (item.quantity <= item.reorder_point * 1.5 ? 'text-warning' : 'text-success');
        
        html += `<tr>
            <td>${item.id}</td>
            <td>${item.name}</td>
            <td>${item.category}</td>
            <td class="${quantityClass}">${item.quantity}</td>
            <td>${item.status}</td>
            <td>
                <a href="/item/${item.id}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Details">
                    <i class="bi bi-eye"></i>
                </a>
                <a href="/item/edit/${item.id}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit">
                    <i class="bi bi-pencil"></i>
                </a>
            </td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
    
    // Reinitialize tooltips for the new content
    const tooltips = [].slice.call(container.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.forEach(el => new bootstrap.Tooltip(el));
}

/**
 * Debounce function to limit how often a function can be called
 * @param {Function} func - The function to debounce
 * @param {number} wait - The debounce delay in milliseconds
 * @return {Function} - Debounced function
 */
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

/**
 * Highlight quantities based on reorder point
 */
function highlightQuantities() {
    const quantityElements = document.querySelectorAll('.item-quantity');
    quantityElements.forEach(element => {
        const quantity = parseInt(element.getAttribute('data-quantity'));
        const reorderPoint = parseInt(element.getAttribute('data-reorder-point'));
        
        if (quantity <= reorderPoint) {
            element.classList.add('text-danger', 'fw-bold');
        } else if (quantity <= reorderPoint * 1.5) {
            element.classList.add('text-warning');
        }
    });
}

/**
 * Form validation setup
 */
function validateForms() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Setup AJAX handling for restock request actions
 */
function setupRestockActions() {
    document.querySelectorAll('.approve-restock, .reject-restock').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const requestId = this.getAttribute('data-request-id');
            const action = this.classList.contains('approve-restock') ? 'approve' : 'reject';
            const confirmMessage = action === 'approve' ? 'Are you sure you want to approve this restock request?' : 'Are you sure you want to reject this restock request?';
            
            if (confirm(confirmMessage)) {
                const data = new FormData();
                data.append('request_id', requestId);
                data.append('action', action);
                
                fetch('/restock/process', {
                    method: 'POST',
                    body: data
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI or reload page
                        const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                        if (row) {
                            row.classList.add('bg-light');
                            const statusCell = row.querySelector('.status-cell');
                            if (statusCell) {
                                statusCell.textContent = action === 'approve' ? 'Approved' : 'Rejected';
                                statusCell.classList.remove('text-warning');
                                statusCell.classList.add(action === 'approve' ? 'text-success' : 'text-danger');
                            }
                            // Disable action buttons
                            row.querySelectorAll('.approve-restock, .reject-restock').forEach(btn => {
                                btn.disabled = true;
                            });
                        }
                    } else {
                        alert(data.message || 'Operation failed, please try again later');
                    }
                })
                .catch(error => {
                    console.error('Request processing error:', error);
                    alert('Operation failed, please try again later');
                });
            }
        });
    });
} 