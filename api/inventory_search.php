<?php
// Start session
session_start();

// Check for authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/database.php';

// Set header to JSON
header('Content-Type: application/json');

// Get search query
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

try {
    // Build the query
    $query = "SELECT * FROM inventory_items WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        // Split search terms by spaces to allow searching for multiple keywords
        $search_terms = explode(' ', $search);
        $search_conditions = [];
        
        foreach ($search_terms as $index => $term) {
            if (strlen($term) > 0) {
                $term_param = 'term_' . $index;
                $desc_param = 'desc_' . $index;
                
                $search_conditions[] = "(UPPER(item_name) LIKE UPPER(:{$term_param}) OR UPPER(description) LIKE UPPER(:{$desc_param}))";
                $params[$term_param] = "%{$term}%";
                $params[$desc_param] = "%{$term}%";
            }
        }
        
        if (!empty($search_conditions)) {
            // Join with AND to ensure all search terms are found
            $query .= " AND (" . implode(' OR ', $search_conditions) . ")";
        }
    }
    
    if (!empty($category)) {
        $query .= " AND category = :category";
        $params['category'] = $category;
    }
    
    $query .= " ORDER BY category, item_name";
    
    // Execute the query
    $stmt = oci_query($GLOBALS['db_conn'], $query, $params);
    
    // Fetch all results
    $results = oci_fetch_all_assoc($stmt);
    
    // Return the data
    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>