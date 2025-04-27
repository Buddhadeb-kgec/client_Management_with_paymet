<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../index.php"); // Adjust the path if needed
    exit(); // Stop further execution of the script
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";    // XAMPP default username
$password = "";        // XAMPP default (blank password)
$dbname = "u522875338_PACE_DB";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate dues for a client
function calculateDues($client_id, $conn) {
    // Get initial dues
    $sql = "SELECT due_payment FROM client WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $initial_dues = $row['due_payment'];

    // Get total price from task table
    $sql = "SELECT COALESCE(SUM(price), 0) AS total_price FROM task WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_price = $row['total_price'];

    // Get total amount_paid from adminclientpayment table
    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM adminclientpayment WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_paid = $row['total_paid'];

    // Calculate dues
    $dues = ($initial_dues + $total_price) - $total_paid;

    // Update dues in client table
    $sql = "UPDATE client SET due_payment = $dues WHERE client_id = $client_id";
    if (!$conn->query($sql)) {
        die("Update failed: " . $conn->error);
    }
}

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_payment'])) {
        $client_id = $_POST['client_id'];
        $payment_date = $_POST['payment_date'];
        $amount_paid = $_POST['amount_paid'];
        $payment_in_inr = $_POST['payment_in_inr'];
        $description = $_POST['description'];
        $payment_done = isset($_POST['payment_done']) ? 1 : 0; // Checkbox value

        // Fetch client name from the client table
        $sql = "SELECT client_name FROM client WHERE client_id = $client_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $client_name = $row['client_name'];

        // Insert payment into adminclientpayment table, including client_name, description, and payment_done
        $sql = "INSERT INTO adminclientpayment (payment_date, amount_paid, payment_in_inr, client_id, client_name, description, payment_done) 
                VALUES ('$payment_date', '$amount_paid', '$payment_in_inr', '$client_id', '$client_name', '$description', '$payment_done')";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the client
            calculateDues($client_id, $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['edit_payment'])) {
        $transaction_id = $_POST['transaction_id'];
        $payment_date = $_POST['payment_date'];
        $amount_paid = $_POST['amount_paid'];
        $payment_in_inr = $_POST['payment_in_inr'];
        $description = $_POST['description'];
        $client_id = $_POST['client_id'];
        $payment_done = isset($_POST['payment_done']) ? 1 : 0; // Checkbox value

        // Fetch client name from the client table
        $sql = "SELECT client_name FROM client WHERE client_id = $client_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $client_name = $row['client_name'];

        // Update payment in adminclientpayment table, including client_name, description, and payment_done
        $sql = "UPDATE adminclientpayment 
                SET payment_date = '$payment_date', amount_paid = '$amount_paid', payment_in_inr = '$payment_in_inr', client_id = '$client_id', client_name = '$client_name', description = '$description', payment_done = '$payment_done'
                WHERE transaction_id = $transaction_id";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the client
            calculateDues($client_id, $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete_payment'])) {
        $transaction_id = $_POST['transaction_id'];

        // Get client_id and amount_paid before deleting the payment
        $sql = "SELECT client_id, amount_paid FROM adminclientpayment WHERE transaction_id = $transaction_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $client_id = $row['client_id'];
        $amount_paid = $row['amount_paid'];

        // Delete payment from adminclientpayment table
        $sql = "DELETE FROM adminclientpayment WHERE transaction_id = $transaction_id";
        if ($conn->query($sql) === TRUE) {
            // Add the deleted payment amount back to the client's dues
            $sql = "UPDATE client SET due_payment = due_payment + $amount_paid WHERE client_id = $client_id";
            if ($conn->query($sql) === TRUE) {
                // Recalculate dues for the client
                calculateDues($client_id, $conn);

                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Fetch all clients for the dropdown
$sql = "SELECT client_id, client_name FROM client";
$clients_result = $conn->query($sql);
if (!$clients_result) {
    die("Query failed: " . $conn->error);
}

// Handle filtering by payment month and payment done status
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';
$filter_payment_done = isset($_GET['filter_payment_done']) ? $_GET['filter_payment_done'] : '';

// Fetch all payments with client names
$sql = "SELECT a.transaction_id, a.payment_date, a.amount_paid, a.payment_in_inr, a.client_id, c.client_name, a.description, a.payment_done 
        FROM adminclientpayment a 
        JOIN client c ON a.client_id = c.client_id";

// Add filtering by payment month if selected
if (!empty($filter_month)) {
    $sql .= " WHERE DATE_FORMAT(a.payment_date, '%Y-%m') = '$filter_month'";
}

// Add filtering by payment done status if selected
if ($filter_payment_done !== '') {
    $sql .= (strpos($sql, 'WHERE') === false) ? " WHERE " : " AND ";
    $sql .= "a.payment_done = $filter_payment_done";
}

$payments_result = $conn->query($sql);
if (!$payments_result) {
    die("Query failed: " . $conn->error);
}

// Navigation items
$navItems = [
    'Tasks' => 'task.php',
    'Clients' => 'client.php',
    'Generate Sheets' => 'visualization.php',
    'A-Client Payments' => 'admin_client_payment.php',
    'Team Leads' => 'teamlead.php',
    'A-TL Payments' => 'admin_tl_payment.php',
    'Experts' => 'expert.php',
    'TL-Expert Payments' => 'tl_expert_payment.php',
    'Colleges' => 'colleges.php'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Client Payment Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --light-bg: #f8f9ff;
            --dark-text: #2b2d42;
            --light-text: #f8f9fa;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --sidebar-width: 280px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--secondary-color), var(--primary-color));
            color: var(--light-text);
            padding: 1.5rem 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 1.5rem 2rem;
            text-decoration: none;
        }
        
        .sidebar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        .sidebar-brand-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
        }
        
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding-bottom: 120px; /* Space for footer */
        }
        
        .nav-item {
            margin: 0.5rem 1rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        
        .sidebar-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: var(--sidebar-width);
            padding: 1rem 1.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1001;
        }
        
        .sidebar-footer .btn {
            margin-bottom: 0.5rem;
            width: 100%;
        }
        
        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
            padding: 2rem;
        }
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background-color: white;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border-radius: 12px;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-text);
            margin: 0;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 1rem;
            object-fit: cover;
        }
        
        /* Dashboard Cards */
        .dashboard-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            overflow: hidden;
            background-color: white;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        /* Form Styles */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        /* Button Styles */
        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #3a56e8;
            border-color: #3a56e8;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        /* Table Styles */
        .table {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            border: none;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
            transform: translateX(5px);
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        
        /* Edit Form Styles */
        .edit-form {
            display: none;
            background-color: #f9f9ff;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        /* Dropdown Styles */
        .dropdown-container {
            position: relative;
        }
        
        .dropdown-list {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: none;
        }
        
        .dropdown-list li {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .dropdown-list li:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        /* Checkbox Styles */
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.2em;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block !important;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <a href="index.php" class="sidebar-brand">
            <img src="bg.jpg" alt="Logo" class="img-fluid">
            <span class="sidebar-brand-text">Admin Portal</span>
        </a>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column mt-4">
                <?php foreach ($navItems as $label => $link): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === $link ? 'active' : '' ?>" href="<?= $link ?>">
                            <i class="fas fa-<?= 
                                strtolower($label) == 'tasks' ? 'tasks' : 
                                (strtolower($label) == 'clients' ? 'users' : 
                                (strtolower($label) == 'generate sheets' ? 'file-excel' : 
                                (str_contains(strtolower($label), 'payment') ? 'money-bill-wave' : 
                                (strtolower($label) == 'team leads' ? 'user-tie' : 
                                (strtolower($label) == 'experts' ? 'user-graduate' : 
                                (strtolower($label) == 'colleges' ? 'university' : 'cog')))))) ?>"></i>
                            <span><?= $label ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <a href="export_db.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-download me-2"></i> Export Database
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div>
                <button class="btn btn-link sidebar-toggle d-lg-none me-3" style="display: none;">
                    <i class="fas fa-bars fa-lg"></i>
                </button>
                <h1 class="page-title"><i class="fas fa-money-bill-wave me-2"></i>Client Payments Management</h1>
            </div>
            <div class="user-menu">
                <span class="d-none d-md-inline">Welcome, Admin</span>
                <img src="bg.jpg" alt="User" class="user-avatar">
            </div>
        </div>
        
        <!-- Add Payment Card -->
        <div class="dashboard-card fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Client Payment</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="client_id" class="form-label">Client:</label>
                            <div class="dropdown-container">
                                <input type="text" id="client-search" class="form-control" placeholder="Search client...">
                                <ul class="dropdown-list" id="client-list">
                                    <?php while ($row = $clients_result->fetch_assoc()) { ?>
                                        <li data-value="<?php echo $row['client_id']; ?>"><?php echo $row['client_name']; ?></li>
                                    <?php } ?>
                                </ul>
                                <input type="hidden" name="client_id" id="client-id">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="payment_date" class="form-label">Payment Date:</label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label for="amount_paid" class="form-label">Amount Paid:</label>
                            <input type="number" name="amount_paid" id="amount_paid" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label for="payment_in_inr" class="form-label">Payment in INR:</label>
                            <input type="number" step="0.01" name="payment_in_inr" id="payment_in_inr" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="description" class="form-label">Description:</label>
                            <input type="text" name="description" id="description" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="payment_done" id="payment_done">
                                <label class="form-check-label" for="payment_done">Payment Done</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="add_payment" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add Payment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Filter Payments Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Payments</h5>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="filter_month" class="form-label">Select Month:</label>
                            <input type="month" name="filter_month" id="filter_month" class="form-control" value="<?php echo $filter_month; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="filter_payment_done" class="form-label">Payment Status:</label>
                            <select name="filter_payment_done" id="filter_payment_done" class="form-control">
                                <option value="">All Payments</option>
                                <option value="1" <?php echo $filter_payment_done === '1' ? 'selected' : ''; ?>>Completed</option>
                                <option value="0" <?php echo $filter_payment_done === '0' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filter
                            </button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="admin_client_payment.php" class="btn btn-warning w-100">
                                <i class="fas fa-times me-2"></i>Clear Filter
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Payments List Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.2s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Payments List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Transaction ID</th>
                                <th>Payment Date</th>
                                <th>Amount Paid</th>
                                <th>Payment in INR</th>
                                <th>Client Name</th>
                                <th>Client ID</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $serial_no = 1; // Initialize serial number counter
                            while ($row = $payments_result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo $serial_no++; ?></td>
                                    <td><?php echo $row['transaction_id']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['payment_date'])); ?></td>
                                    <td><?php echo $row['amount_paid']; ?></td>
                                    <td><?php echo number_format($row['payment_in_inr'], 2); ?></td>
                                    <td><?php echo $row['client_name']; ?></td>
                                    <td><?php echo $row['client_id']; ?></td>
                                    <td><?php echo $row['description']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['payment_done'] ? 'success' : 'warning'; ?>">
                                            <?php echo $row['payment_done'] ? 'Completed' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <!-- Edit Button -->
                                            <button onclick="toggleEditForm(<?php echo $row['transaction_id']; ?>)" class="btn btn-warning btn-sm me-2">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <!-- Delete Form -->
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this payment?');">
                                                <input type="hidden" name="transaction_id" value="<?php echo $row['transaction_id']; ?>">
                                                <button type="submit" name="delete_payment" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                        <!-- Edit Form -->
                                        <div id="edit-form-<?php echo $row['transaction_id']; ?>" class="edit-form mt-3">
                                            <form method="POST">
                                                <input type="hidden" name="transaction_id" value="<?php echo $row['transaction_id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-2">
                                                        <label class="form-label">Payment Date:</label>
                                                        <input type="date" name="payment_date" class="form-control" value="<?php echo $row['payment_date']; ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Amount Paid:</label>
                                                        <input type="number" name="amount_paid" class="form-control" value="<?php echo $row['amount_paid']; ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Payment in INR:</label>
                                                        <input type="number" step="0.01" name="payment_in_inr" class="form-control" value="<?php echo $row['payment_in_inr']; ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Client:</label>
                                                        <div class="dropdown-container">
                                                            <input type="text" id="client-search-edit-<?php echo $row['transaction_id']; ?>" class="form-control" placeholder="Search client..." value="<?php echo $row['client_name']; ?>">
                                                            <ul class="dropdown-list" id="client-list-edit-<?php echo $row['transaction_id']; ?>">
                                                                <?php
                                                                $clients_result->data_seek(0); // Reset pointer to the beginning
                                                                while ($client = $clients_result->fetch_assoc()) {
                                                                    echo "<li data-value='{$client['client_id']}'>{$client['client_name']}</li>";
                                                                }
                                                                ?>
                                                            </ul>
                                                            <input type="hidden" name="client_id" id="client-id-edit-<?php echo $row['transaction_id']; ?>" value="<?php echo $row['client_id']; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Description:</label>
                                                        <input type="text" name="description" class="form-control" value="<?php echo $row['description']; ?>">
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-end">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="payment_done" <?php echo $row['payment_done'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label">Done</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-end">
                                                        <button type="submit" name="edit_payment" class="btn btn-success w-100">
                                                            <i class="fas fa-save me-1"></i>Save
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Custom Script -->
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Search functionality for dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.dropdown-container');
            dropdowns.forEach(dropdown => {
                const input = dropdown.querySelector('input[type="text"]');
                const list = dropdown.querySelector('.dropdown-list');
                const hiddenInput = dropdown.querySelector('input[type="hidden"]');

                // Show dropdown list when input is focused
                input.addEventListener('focus', function() {
                    list.style.display = 'block';
                });

                input.addEventListener('input', function() {
                    const searchTerm = input.value.toLowerCase();
                    const items = list.querySelectorAll('li');
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });

                list.addEventListener('click', function(e) {
                    if (e.target.tagName === 'LI') {
                        input.value = e.target.textContent;
                        hiddenInput.value = e.target.getAttribute('data-value');
                        list.style.display = 'none';
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!dropdown.contains(e.target)) {
                        list.style.display = 'none';
                    }
                });
            });
        });

        function toggleEditForm(transactionId) {
            const editForm = document.getElementById(`edit-form-${transactionId}`);
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
            } else {
                editForm.style.display = 'none';
            }
        }
        
        // Add animation class to elements as they scroll into view
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            
            const fadeInOnScroll = function() {
                fadeElements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const windowHeight = window.innerHeight;
                    
                    if (elementTop < windowHeight - 100) {
                        element.style.opacity = 1;
                        element.style.transform = 'translateY(0)';
                    }
                });
            };
            
            // Initial check
            fadeInOnScroll();
            
            // Check on scroll
            window.addEventListener('scroll', fadeInOnScroll);
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>