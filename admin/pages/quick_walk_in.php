<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header("Location: index.php?page=login");
    exit();
}

// Handle quick walk-in submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $purpose = $conn->real_escape_string($_POST['purpose']);
    $payment_amount = (float)$_POST['payment_amount'];
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    
    $visit_date = date('Y-m-d');
    $time_in = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO walk_in (first_name, last_name, address, visit_date, time_in, purpose, payment_amount, payment_method) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssds", $first_name, $last_name, $address, $visit_date, $time_in, $purpose, $payment_amount, $payment_method);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Quick walk-in registered successfully!";
        header("Location: index.php?page=walk_in");
        exit();
    } else {
        $_SESSION['error'] = "Error registering walk-in: " . $conn->error;
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Walk-in Registration - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .quick-walk-in-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-right: 10px;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body style="background: #f8f9fa;">
    <div class="quick-walk-in-container">
        <div class="header">
            <h1><i class="fas fa-walking"></i> Quick Walk-in Registration</h1>
            <p>Fast registration for walk-in customers</p>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="quickWalkInForm">
            <div class="form-group">
                <label for="first_name">First Name *</label>
                <input type="text" id="first_name" name="first_name" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="last_name">Last Name *</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            
            <div class="form-group">
                <label for="address">Address/Location</label>
                <input type="text" id="address" name="address" placeholder="Enter customer's address or location">
            </div>
            
            <div class="form-group">
                <label for="purpose">Purpose *</label>
                <select id="purpose" name="purpose" required>
                    <option value="">Select Purpose</option>
                    <option value="gym_visit">Gym Visit</option>
                    <option value="consultation">Consultation</option>
                    <option value="trial">Trial</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="payment_amount">Payment Amount</label>
                <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method">
                    <option value="">Select Payment Method</option>
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.php?page=walk_in" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Walk-in Management
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Register Walk-in
                </button>
            </div>
        </form>
    </div>

    <script>
        // Auto-focus on first name field
        document.getElementById('first_name').focus();
        
        // Form validation
        document.getElementById('quickWalkInForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const purpose = document.getElementById('purpose').value;
            
            if (!firstName) {
                alert('First name is required');
                e.preventDefault();
                return;
            }
            
            if (!lastName) {
                alert('Last name is required');
                e.preventDefault();
                return;
            }
            
            if (!purpose) {
                alert('Purpose is required');
                e.preventDefault();
                return;
            }
        });
        
        // Address field validation (optional)
        document.getElementById('address').addEventListener('input', function(e) {
            // You can add address validation logic here if needed
        });
    </script>
</body>
</html>
