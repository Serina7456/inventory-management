# Inventory Management System

A comprehensive inventory management system based on PHP and Oracle database, providing user authentication, inventory tracking, and restock request management functionalities.

## System Overview

This system is an inventory management solution designed for small to medium-sized enterprises, using PHP as the backend language and Oracle database for storage. The system provides an intuitive web interface that allows users to manage inventory items, process restock requests, and maintain inventory records.

## Main Features

### User Management
- User registration and login
- Role-based access control (administrators and regular users)
- Secure password storage (using password hashing)

### Inventory Management
- View, add, edit, and delete inventory items
- Search inventory by category and keywords
- Detailed item information (name, category, description, quantity, unit price)

### Restock Requests
- Create restock requests
- Administrator approval workflow
- Automatic inventory updates (when requests are approved)
- Request status tracking (pending, approved, rejected)

## Technology Stack

### Frontend
- HTML/CSS
- Bootstrap framework (responsive design)
- JavaScript (client-side validation and interaction)

### Middleware
- PHP Session (user authentication)
- Custom middleware (permission checks)
- Error handling and logging

### Backend
- PHP 7+
- Oracle database
- OCI8 extension (PHP-Oracle connectivity)

## System Requirements

- Web server (Apache/Nginx)
- PHP 7.0 or higher
- Oracle database 12c or higher
- PHP OCI8 extension

## Installation Steps

1. **Clone the repository**
   ```
   git clone <repository-url>
   ```

2. **Configure database connection**
   - Edit the `config/database.php` file
   - Update Oracle database connection parameters (username, password, connection string)

3. **Set up Web server**
   - Configure the web server to point to the project root directory
   - Ensure `/path/to/project` has appropriate read/write permissions for the web server

4. **Run the installation script**
   - Access `http://your-domain.com/install.php` to create necessary database tables and default admin account

5. **Configure file permissions**
   - Ensure the `error_log` file and other directories that need write access have appropriate permissions

## Usage Guide

### Administrator Account
- Default admin username: `admin`
- Default password: `admin123`
- Change the default password immediately after first login

### Basic Workflow
1. **Login to the system**
   - Use administrator or regular user credentials to log in

2. **Manage inventory (Administrators)**
   - Add new items: Navigate to the "Manage Items" page, click "Add New Item"
   - Edit items: Find the target item in the item list, click "Edit"
   - Delete items: Use the "Delete" button in the item list or edit page

3. **Request restocks (All users)**
   - Browse the inventory list
   - Find the item that needs restocking, click "Request Restock"
   - Fill in the request quantity and reason
   - Submit the request and wait for approval

4. **Process restock requests (Administrators)**
   - Navigate to the "Restock Requests" page
   - View pending requests
   - Approve or reject requests
   - Approved requests will automatically update inventory quantities

## Database Structure

The system uses three main tables:

1. **users** - Stores user information
   - user_id (primary key)
   - username
   - password (hashed)
   - email
   - is_admin
   - created_at

2. **inventory_items** - Stores inventory items
   - item_id (primary key)
   - item_name
   - category
   - quantity
   - description (CLOB)
   - unit_price
   - last_updated

3. **restock_requests** - Stores restock requests
   - request_id (primary key)
   - item_id (foreign key)
   - user_id (foreign key)
   - quantity_requested
   - reason (CLOB)
   - status
   - request_date
   - processed_by
   - processed_date

## Troubleshooting

### Common Issues

1. **Database connection failure**
   - Check connection parameters in `config/database.php`
   - Confirm Oracle service is running
   - Verify user permissions are correct

2. **Login issues**
   - Check username and password are correct
   - View error_log for detailed error information
   - Ensure PHP OCI8 extension is installed and enabled

3. **Restock request processing failures**
   - Check database transaction logs
   - Confirm CLOB data is processed correctly

## Development Notes

- All date fields use Oracle's TO_CHAR and TO_TIMESTAMP functions for formatting
- CLOB fields require special handling, see the safe_display function in the code
- Numeric types need to be converted to strings when binding parameters

## Security Considerations

- All user inputs are processed through parameterized queries to prevent SQL injection
- Passwords are securely stored using PHP's password_hash and password_verify functions
- Output data is escaped using htmlspecialchars to prevent XSS attacks

---

© 2025 Inventory Management System | A PHP and Oracle-based Solution 