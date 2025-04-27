<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../task.php"); // Adjust the path if needed
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

// Function to calculate dues for an Expert
function calculateExpertDues($expert_id, $conn) {
    // Get initial dues
    $sql = "SELECT initial_dues FROM expert WHERE expert_id = $expert_id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $initial_dues = $row['initial_dues'];

    // Get total earnings from task table
    $sql = "SELECT 
                COALESCE(SUM(CASE WHEN expert_id_1 = $expert_id THEN expert_price1 ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN expert_id_2 = $expert_id THEN expert_price2 ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN expert_id_3 = $expert_id THEN expert_price3 ELSE 0 END), 0) AS total_earnings
            FROM task
            WHERE expert_id_1 = $expert_id OR expert_id_2 = $expert_id OR expert_id_3 = $expert_id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $total_earnings = $row['total_earnings'];

    // Get total payments from tlexpertpayment table
    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid 
            FROM tlexpertpayment 
            WHERE expert_id = $expert_id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $total_paid = $row['total_paid'];

    // Calculate dues
    $dues = ($initial_dues + $total_earnings) - $total_paid;

    // Update dues in expert table
    $sql = "UPDATE expert SET dues = $dues WHERE expert_id = $expert_id";
    $conn->query($sql);
}

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_payment'])) {
        $expert_id = $_POST['expert_id'];
        $payment_date = $_POST['payment_date'];
        $amount_paid = $_POST['amount_paid'];
        $team_lead_id = $_POST['team_lead_id'];
        $description = $_POST['description'];

        // Fetch Expert's name from expert table
        $sql = "SELECT expert_name FROM expert WHERE expert_id = $expert_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $expert_name = $row['expert_name'];

        // Fetch Team Lead's name from teamlead table
        $sql = "SELECT name FROM teamlead WHERE team_lead_id = $team_lead_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $team_lead_name = $row['name'];

        // Insert payment into tlexpertpayment table
        $sql = "INSERT INTO tlexpertpayment (payment_date, amount_paid, expert_id, expert_name, team_lead_id, team_lead_name, description) 
                VALUES ('$payment_date', '$amount_paid', '$expert_id', '$expert_name', '$team_lead_id', '$team_lead_name', '$description')";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the Expert
            calculateExpertDues($expert_id, $conn);

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
        $expert_id = $_POST['expert_id'];
        $team_lead_id = $_POST['team_lead_id'];
        $description = $_POST['description'];

        // Fetch Expert's name from expert table
        $sql = "SELECT expert_name FROM expert WHERE expert_id = $expert_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $expert_name = $row['expert_name'];

        // Fetch Team Lead's name from teamlead table
        $sql = "SELECT name FROM teamlead WHERE team_lead_id = $team_lead_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $team_lead_name = $row['name'];

        // Update payment in tlexpertpayment table
        $sql = "UPDATE tlexpertpayment 
                SET payment_date = '$payment_date', amount_paid = '$amount_paid', expert_id = '$expert_id', expert_name = '$expert_name', team_lead_id = '$team_lead_id', team_lead_name = '$team_lead_name', description = '$description' 
                WHERE transaction_id = $transaction_id";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the Expert
            calculateExpertDues($expert_id, $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete_payment'])) {
        $transaction_id = $_POST['transaction_id'];

        // Get expert_id and amount_paid before deleting the payment
        $sql = "SELECT expert_id, amount_paid FROM tlexpertpayment WHERE transaction_id = $transaction_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $expert_id = $row['expert_id'];
        $amount_paid = $row['amount_paid'];

        // Delete payment from tlexpertpayment table
        $sql = "DELETE FROM tlexpertpayment WHERE transaction_id = $transaction_id";
        if ($conn->query($sql) === TRUE) {
            // Add the deleted payment amount back to the expert's dues
            $sql = "UPDATE expert SET dues = dues + $amount_paid WHERE expert_id = $expert_id";
            if ($conn->query($sql) === TRUE) {
                // Recalculate dues for the Expert
                calculateExpertDues($expert_id, $conn);

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

// Fetch all Experts for the dropdown
$sql = "SELECT expert_id, expert_name FROM expert";
$experts_result = $conn->query($sql);
if (!$experts_result) {
    die("Query failed: " . $conn->error);
}

// Fetch all Team Leads for the dropdown
$sql = "SELECT team_lead_id, name FROM teamlead";
$tls_result = $conn->query($sql);
if (!$tls_result) {
    die("Query failed: " . $conn->error);
}

// Fetch all payments to Experts
$sql = "SELECT * FROM tlexpertpayment";
if (isset($_GET['filter_month'])) {
    $filter_month = $_GET['filter_month'];
    $sql .= " WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$filter_month'";
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
    <title>TL Expert Payment Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        .dropdown-container input[type="text"] {
            width: 100%; 
            padding: 0.75rem 1rem;
            margin-bottom: 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .dropdown-list {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            background-color: #fff;
            display: none;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .dropdown-list li { 
            padding: 0.75rem 1rem; 
            cursor: pointer; 
            transition: all 0.2s ease;
        }
        
        .dropdown-list li:hover { 
            background-color: rgba(67, 97, 238, 0.1);
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
                <h1 class="page-title"><i class="fas fa-money-bill-wave me-2"></i>TL-Expert Payments</h1>
            </div>
            <div class="user-menu">
                <span class="d-none d-md-inline">Welcome, Admin</span>
                <img src="bg.jpg" alt="User" class="user-avatar">
            </div>
        </div>
        
        <!-- Add Payment Card -->
        <div class="dashboard-card fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Payment</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Expert:</label>
                            <div class="dropdown-container">
                                <input type="text" id="expert-search" class="form-control" placeholder="Search expert...">
                                <ul class="dropdown-list" id="expert-list">
                                    <?php
                                    $experts_result->data_seek(0); // Reset pointer to beginning
                                    while ($row = $experts_result->fetch_assoc()) { ?>
                                        <li data-value="<?php echo $row['expert_id']; ?>"><?php echo $row['expert_name']; ?></li>
                                    <?php } ?>
                                </ul>
                                <input type="hidden" name="expert_id" id="expert-id">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Team Lead:</label>
                            <div class="dropdown-container">
                                <input type="text" id="tl-search" class="form-control" placeholder="Search team lead...">
                                <ul class="dropdown-list" id="tl-list">
                                    <?php
                                    $tls_result->data_seek(0); // Reset pointer to beginning
                                    while ($row = $tls_result->fetch_assoc()) { ?>
                                        <li data-value="<?php echo $row['team_lead_id']; ?>"><?php echo $row['name']; ?></li>
                                    <?php } ?>
                                </ul>
                                <input type="hidden" name="team_lead_id" id="tl-id">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Payment Date:</label>
                            <input type="date" name="payment_date" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Amount Paid:</label>
                            <input type="number" name="amount_paid" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Description:</label>
                            <input type="text" name="description" class="form-control">
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
                            <label class="form-label">Select Month:</label>
                            <input type="month" name="filter_month" class="form-control" value="<?php echo isset($_GET['filter_month']) ? $_GET['filter_month'] : ''; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="tl_expert_payment.php" class="btn btn-secondary w-100">
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
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Payments to Experts</h5>
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
                                <th>Expert Name</th>
                                <th>Team Lead Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial_no = 1;
                            while ($row = $payments_result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo $serial_no++; ?></td>
                                    <td><?php echo $row['transaction_id']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['payment_date'])); ?></td>
                                    <td>₹<?php echo number_format($row['amount_paid'], 2); ?></td>
                                    <td><?php echo $row['expert_name']; ?> (ID: <?php echo $row['expert_id']; ?>)</td>
                                    <td><?php echo $row['team_lead_name']; ?> (ID: <?php echo $row['team_lead_id']; ?>)</td>
                                    <td><?php echo $row['description']; ?></td>
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
                                                    <div class="col-md-3">
                                                        <label class="form-label">Payment Date:</label>
                                                        <input type="date" name="payment_date" class="form-control" value="<?php echo $row['payment_date']; ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Amount Paid:</label>
                                                        <input type="number" name="amount_paid" class="form-control" value="<?php echo $row['amount_paid']; ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Expert:</label>
                                                        <div class="dropdown-container">
                                                            <input type="text" id="expert-search-edit-<?php echo $row['transaction_id']; ?>" class="form-control" placeholder="Search expert..." value="<?php echo $row['expert_name']; ?>">
                                                            <ul class="dropdown-list" id="expert-list-edit-<?php echo $row['transaction_id']; ?>">
                                                                <?php
                                                                $experts_result->data_seek(0); // Reset pointer to the beginning
                                                                while ($expert = $experts_result->fetch_assoc()) {
                                                                    echo "<li data-value='{$expert['expert_id']}'>{$expert['expert_name']}</li>";
                                                                }
                                                                ?>
                                                            </ul>
                                                            <input type="hidden" name="expert_id" id="expert-id-edit-<?php echo $row['transaction_id']; ?>" value="<?php echo $row['expert_id']; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Team Lead:</label>
                                                        <div class="dropdown-container">
                                                            <input type="text" id="tl-search-edit-<?php echo $row['transaction_id']; ?>" class="form-control" placeholder="Search team lead..." value="<?php echo $row['team_lead_name']; ?>">
                                                            <ul class="dropdown-list" id="tl-list-edit-<?php echo $row['transaction_id']; ?>">
                                                                <?php
                                                                $tls_result->data_seek(0); // Reset pointer to the beginning
                                                                while ($tl = $tls_result->fetch_assoc()) {
                                                                    echo "<li data-value='{$tl['team_lead_id']}'>{$tl['name']}</li>";
                                                                }
                                                                ?>
                                                            </ul>
                                                            <input type="hidden" name="team_lead_id" id="tl-id-edit-<?php echo $row['transaction_id']; ?>" value="<?php echo $row['team_lead_id']; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Description:</label>
                                                        <input type="text" name="description" class="form-control" value="<?php echo $row['description']; ?>">
                                                    </div>
                                                    <div class="col-md-1 d-flex align-items-end">
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
    
    <!-- Custom Script -->
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Toggle edit form
        function toggleEditForm(transactionId) {
            const editForm = document.getElementById(`edit-form-${transactionId}`);
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
            } else {
                editForm.style.display = 'none';
            }
        }

        // Initialize all dropdowns
        function initDropdowns() {
            document.querySelectorAll('.dropdown-container').forEach(dropdown => {
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
        }

        // Initialize dropdowns on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDropdowns();
            
            // Add animation class to elements as they scroll into view
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