<?php
// Basit banka form sayfası
require_once('../../../wp-load.php');

// Güvenlik kontrolü
if (!is_user_logged_in()) {
    wp_die('Please log in to access this page.');
}

$user_id = get_current_user_id();
$registration_id = isset($_GET['registration_id']) ? intval($_GET['registration_id']) : 0;

if (!$registration_id) {
    wp_die('You need to log in to access this page.');
}

// Basit form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bank Account Details</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .submit-btn { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Bank Account Details</h1>
    <p>Registration ID: <?php echo $registration_id; ?></p>
    <p>User ID: <?php echo $user_id; ?></p>
    
    <form method="post">
        <div class="form-group">
            <label>Account Holder Name:</label>
            <input type="text" name="account_holder_name" required>
        </div>
        
        <div class="form-group">
            <label>Bank Name:</label>
            <input type="text" name="bank_name" required>
        </div>
        
        <div class="form-group">
            <label>Account Number:</label>
            <input type="text" name="account_number" required>
        </div>
        
        <button type="submit" class="submit-btn">Submit</button>
    </form>
</body>
</html> 