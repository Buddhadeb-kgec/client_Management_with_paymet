<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";    // XAMPP default username
$password = "";        // XAMPP default (blank password)
$dbname = "u522875338_PACE_DB";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all clients for the dropdown
$clients_dropdown_query = "SELECT DISTINCT client_id, client_name FROM client";
$clients_dropdown_result = $conn->query($clients_dropdown_query);

if (!$clients_dropdown_result) {
    die("Error fetching clients: " . $conn->error);
}

// Fetch all colleges for the dropdown
$colleges_dropdown_query = "SELECT DISTINCT college_name, country FROM colleges";
$colleges_dropdown_result = $conn->query($colleges_dropdown_query);

if (!$colleges_dropdown_result) {
    die("Error fetching colleges: " . $conn->error);
}

// Function to calculate dues for a client
function calculateDues($client_id, $conn) {
    $sql = "SELECT initial_dues FROM client WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching initial dues: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $initial_dues = $row['initial_dues'];

    $sql = "SELECT COALESCE(SUM(price), 0) AS total_price FROM task WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching total price: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_price = $row['total_price'];

    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM adminclientpayment WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching total paid: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_paid = $row['total_paid'];

    $due_payment = ($initial_dues + $total_price) - $total_paid;

    $sql = "UPDATE client SET due_payment = $due_payment WHERE client_id = $client_id";
    if (!$conn->query($sql)) {
        die("Error updating due payment: " . $conn->error);
    }
}

// Recalculate dues for all clients
$recalculate_dues_query = "SELECT DISTINCT client_id FROM client";
$recalculate_dues_result = $conn->query($recalculate_dues_query);

if (!$recalculate_dues_result) {
    die("Error fetching client IDs: " . $conn->error);
}

while ($row = $recalculate_dues_result->fetch_assoc()) {
    calculateDues($row['client_id'], $conn);
}

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $client_name = $_POST['client_name'];
        $college_name = $_POST['college_name'];
        $reffered_by = $_POST['reffered_by'];
        $reffered_by_client_id = !empty($_POST['reffered_by_client_id']) ? $_POST['reffered_by_client_id'] : NULL;
        $initial_dues = $_POST['initial_dues'];
        $login_id = $_POST['login_id'];
        $login_password = $_POST['login_password'];
        $label = isset($_POST['label']) ? implode(",", $_POST['label']) : NULL;

        $check_sql = "SELECT client_id FROM client WHERE client_name = '$client_name'";
        $check_result = $conn->query($check_sql);
        if ($check_result->num_rows > 0) {
            echo "<script>alert('Client name already exists. Please choose a unique name.');</script>";
        } else {
            $sql = "INSERT INTO client (client_name, college_name, reffered_by, reffered_by_client_id, initial_dues, login_id, login_password, label) 
                    VALUES ('$client_name', '$college_name', '$reffered_by', " . ($reffered_by_client_id === NULL ? "NULL" : "'$reffered_by_client_id'") . ", '$initial_dues', '$login_id', '$login_password', '$label')";
            if ($conn->query($sql) === TRUE) {
                $client_id = $conn->insert_id;
                calculateDues($client_id, $conn);
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        }
    }

    if (isset($_POST['edit'])) {
        $client_id = $_POST['client_id'];
        $client_name = $_POST['client_name'];
        $college_name = $_POST['college_name'];
        $reffered_by = $_POST['reffered_by'];
        $reffered_by_client_id = !empty($_POST['reffered_by_client_id']) ? $_POST['reffered_by_client_id'] : NULL;
        $initial_dues = $_POST['initial_dues'];
        $login_id = $_POST['login_id'];
        $login_password = $_POST['login_password'];
        $label = isset($_POST['label']) ? implode(",", $_POST['label']) : NULL;

        $sql = "UPDATE client SET client_name='$client_name', college_name='$college_name', reffered_by='$reffered_by', 
                reffered_by_client_id=" . ($reffered_by_client_id === NULL ? "NULL" : "'$reffered_by_client_id'") . ", 
                initial_dues='$initial_dues', login_id='$login_id', login_password='$login_password', label='$label' 
                WHERE client_id='$client_id'";
        if ($conn->query($sql) === TRUE) {
            calculateDues($client_id, $conn);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete'])) {
        $client_id = $_POST['client_id'];
        $sql = "DELETE FROM client WHERE client_id='$client_id'";
        if ($conn->query($sql) === TRUE) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Handle filtering
$filter_column = isset($_POST['filter_column']) ? $_POST['filter_column'] : '';
$filter_value = isset($_POST['filter_value']) ? $_POST['filter_value'] : '';

$sql = "SELECT DISTINCT c.*, cl.country 
        FROM client c
        LEFT JOIN colleges cl ON c.college_name = cl.college_name";

if (!empty($filter_column) && !empty($filter_value)) {
    if ($filter_column === "college_name") {
        $sql .= " WHERE c.college_name LIKE '%$filter_value%'";
    } else {
        $sql .= " WHERE $filter_column LIKE '%$filter_value%'";
    }
}

$sort_order = isset($_GET['sort']) ? $_GET['sort'] : '';
if ($sort_order === 'asc') {
    $sql .= " ORDER BY due_payment ASC";
} elseif ($sort_order === 'desc') {
    $sql .= " ORDER BY due_payment DESC";
}

$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
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
    <title>Client Management</title>
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
        
        .dropdown-container ul {
            max-height: 200px; 
            overflow-y: auto; 
            border: 1px solid #e0e0e0;
            margin: 0; 
            padding: 0; 
            list-style: none; 
            position: absolute;
            width: 100%; 
            background: white; 
            z-index: 1000; 
            display: none;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .dropdown-container ul li { 
            padding: 0.75rem 1rem; 
            cursor: pointer; 
            transition: all 0.2s ease;
        }
        
        .dropdown-container ul li:hover { 
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        /* Checkbox Styles */
        .form-check-input {
            margin-right: 0.5rem;
        }
        
        .form-check-label {
            margin-right: 1rem;
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
                <h1 class="page-title"><i class="fas fa-users me-2"></i>Client Management</h1>
            </div>
            <div class="user-menu">
                <span class="d-none d-md-inline">Welcome, Admin</span>
                <img src="bg.jpg" alt="User" class="user-avatar">
            </div>
        </div>
        
        <!-- Add Client Card -->
        <div class="dashboard-card fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Client</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="client_name" class="form-control" placeholder="Client Name" required>
                        </div>
                        <div class="col-md-3">
                            <div class="dropdown-container">
                                <input type="text" name="college_name" placeholder="Search college..." class="search-input form-control" onfocus="toggleDropdown(this)" required>
                                <ul>
                                    <?php $colleges_dropdown_result->data_seek(0);
                                    while ($row = $colleges_dropdown_result->fetch_assoc()) { ?>
                                        <li><?php echo $row['college_name']; ?></li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="dropdown-container">
                                <input type="text" name="reffered_by" placeholder="Search referred by..." class="search-input form-control" onfocus="toggleDropdown(this)">
                                <ul>
                                    <?php $clients_dropdown_result->data_seek(0);
                                    while ($row = $clients_dropdown_result->fetch_assoc()) { ?>
                                        <li><?php echo $row['client_name']; ?></li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="dropdown-container">
                                <input type="text" name="reffered_by_client_id" placeholder="Search referred by client ID..." class="search-input form-control" onfocus="toggleDropdown(this)">
                                <ul>
                                    <?php $clients_dropdown_result->data_seek(0);
                                    while ($row = $clients_dropdown_result->fetch_assoc()) { ?>
                                        <li><?php echo $row['client_name']; ?></li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="initial_dues" class="form-control" placeholder="Initial Dues" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="login_id" class="form-control" placeholder="Login ID" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="login_password" class="form-control" placeholder="Login Password" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Label:</label>
                            <div class="form-check">
                                <input type="checkbox" name="label[]" value="Dormant client" class="form-check-input"> Dormant client
                                <input type="checkbox" name="label[]" value="Potential client" class="form-check-input"> Potential client
                                <input type="checkbox" name="label[]" value="Edits" class="form-check-input"> Edits
                                <input type="checkbox" name="label[]" value="Level 1 dues" class="form-check-input"> Level 1 dues
                                <input type="checkbox" name="label[]" value="Level 2 dues" class="form-check-input"> Level 2 dues
                                <input type="checkbox" name="label[]" value="Level 3 dues" class="form-check-input"> Level 3 dues
                                <input type="checkbox" name="label[]" value="Red dues" class="form-check-input"> Red dues
                                <input type="checkbox" name="label[]" value="Lost clients" class="form-check-input"> Lost clients
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="add" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add Client
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Filter Clients Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Clients</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select name="filter_column" id="filter_column" class="form-control" required onchange="updateFilterInput()">
                                <option value="client_name">Client Name</option>
                                <option value="college_name">College Name</option>
                                <option value="reffered_by">Referred By</option>
                                <option value="due_payment">Due Payment</option>
                                <option value="initial_dues">Initial Dues</option>
                                <option value="login_id">Login ID</option>
                                <option value="label">Label</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="filter_value_container">
                            <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Sorting Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.2s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sort me-2"></i>Sort Clients</h5>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <button type="submit" name="sort" value="asc" class="btn btn-secondary w-100">
                                <i class="fas fa-sort-amount-up me-2"></i>Dues (Low to High)
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="sort" value="desc" class="btn btn-secondary w-100">
                                <i class="fas fa-sort-amount-down me-2"></i>Dues (High to Low)
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="sort" value="" class="btn btn-secondary w-100">
                                <i class="fas fa-bars me-2"></i>Default Order
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Clients List Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.3s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Clients List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>College</th>
                                <th>Country</th>
                                <th>Referred By</th>
                                <th>Initial Dues</th>
                                <th>Due</th>
                                <th>Login ID</th>
                                <th>Label</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $serial_no = 1;
                            while ($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo $serial_no++; ?></td>
                                    <td><?php echo $row['client_name']; ?></td>
                                    <td><?php echo $row['college_name']; ?></td>
                                    <td><?php echo $row['country']; ?></td>
                                    <td><?php echo $row['reffered_by']; ?></td>
                                    <td>₹<?php echo number_format($row['initial_dues'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($row['due_payment'], 2); ?></strong></td>
                                    <td><?php echo $row['login_id']; ?></td>
                                    <td><?php echo $row['label']; ?></td>
                                    <td>
                                        <div class="d-flex">
                                            <!-- Edit Button -->
                                            <button onclick="toggleEditForm(<?php echo $row['client_id']; ?>)" class="btn btn-warning btn-sm me-2">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <!-- Delete Form -->
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this client?');">
                                                <input type="hidden" name="client_id" value="<?php echo $row['client_id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                        <!-- Edit Form -->
                                        <div id="edit-form-<?php echo $row['client_id']; ?>" class="edit-form mt-3">
                                            <form method="POST">
                                                <input type="hidden" name="client_id" value="<?php echo $row['client_id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-3">
                                                        <label class="form-label">Client Name</label>
                                                        <input type="text" name="client_name" class="form-control" value="<?php echo $row['client_name']; ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">College Name</label>
                                                        <div class="dropdown-container">
                                                            <input type="text" name="college_name" placeholder="Search college..." class="search-input form-control" 
                                                                value="<?php echo $row['college_name']; ?>" 
                                                                onfocus="toggleDropdown(this)" required>
                                                            <ul>
                                                                <?php $colleges_dropdown_result->data_seek(0);
                                                                while ($college_row = $colleges_dropdown_result->fetch_assoc()) { ?>
                                                                    <li><?php echo $college_row['college_name']; ?></li>
                                                                <?php } ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Referred By</label>
                                                        <div class="dropdown-container">
                                                            <input type="text" name="reffered_by" placeholder="Search referred by..." class="search-input form-control" 
                                                                value="<?php echo $row['reffered_by']; ?>" 
                                                                onfocus="toggleDropdown(this)">
                                                            <ul>
                                                                <?php $clients_dropdown_result->data_seek(0);
                                                                while ($client_row = $clients_dropdown_result->fetch_assoc()) { ?>
                                                                    <li><?php echo $client_row['client_name']; ?></li>
                                                                <?php } ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Referred by client id</label>
                                                        <div class="dropdown-container">
                                                            <input type="text" name="reffered_by_client_id" placeholder="Search referred by client ID..." 
                                                                class="search-input form-control" value="<?php echo $row['reffered_by_client_id']; ?>" 
                                                                onfocus="toggleDropdown(this)">
                                                            <ul>
                                                                <?php $clients_dropdown_result->data_seek(0);
                                                                while ($client_row = $clients_dropdown_result->fetch_assoc()) { ?>
                                                                    <li><?php echo $client_row['client_name']; ?></li>
                                                                <?php } ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Initial Dues</label>
                                                        <input type="number" name="initial_dues" class="form-control" value="<?php echo $row['initial_dues']; ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Login ID</label>
                                                        <input type="text" name="login_id" class="form-control" value="<?php echo $row['login_id']; ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Login Password</label>
                                                        <input type="text" name="login_password" class="form-control" value="<?php echo $row['login_password']; ?>" required>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label class="form-label">Label:</label>
                                                        <div class="form-check">
                                                            <?php $label = $row['label'] ?? ''; ?>
                                                            <input type="checkbox" name="label[]" value="Dormant client" class="form-check-input" <?php echo strpos($label, 'Dormant client') !== false ? 'checked' : ''; ?>> Dormant client
                                                            <input type="checkbox" name="label[]" value="Potential client" class="form-check-input" <?php echo strpos($label, 'Potential client') !== false ? 'checked' : ''; ?>> Potential client
                                                            <input type="checkbox" name="label[]" value="Edits" class="form-check-input" <?php echo strpos($label, 'Edits') !== false ? 'checked' : ''; ?>> Edits
                                                            <input type="checkbox" name="label[]" value="Level 1 dues" class="form-check-input" <?php echo strpos($label, 'Level 1 dues') !== false ? 'checked' : ''; ?>> Level 1 dues
                                                            <input type="checkbox" name="label[]" value="Level 2 dues" class="form-check-input" <?php echo strpos($label, 'Level 2 dues') !== false ? 'checked' : ''; ?>> Level 2 dues
                                                            <input type="checkbox" name="label[]" value="Level 3 dues" class="form-check-input" <?php echo strpos($label, 'Level 3 dues') !== false ? 'checked' : ''; ?>> Level 3 dues
                                                            <input type="checkbox" name="label[]" value="Red dues" class="form-check-input" <?php echo strpos($label, 'Red dues') !== false ? 'checked' : ''; ?>> Red dues
                                                            <input type="checkbox" name="label[]" value="Lost clients" class="form-check-input" <?php echo strpos($label, 'Lost clients') !== false ? 'checked' : ''; ?>> Lost clients
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-end">
                                                        <button type="submit" name="edit" class="btn btn-success w-100">
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
        function toggleEditForm(clientId) {
            const editForm = document.getElementById(`edit-form-${clientId}`);
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
            } else {
                editForm.style.display = 'none';
            }
        }
        
        // Toggle dropdown
        function toggleDropdown(input) {
            const dropdown = input.nextElementSibling;
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        // Filter dropdown options
        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('search-input')) {
                const input = event.target;
                const dropdown = input.nextElementSibling;
                const options = dropdown.querySelectorAll('li');
                const filter = input.value.toLowerCase();
                options.forEach(option => {
                    option.style.display = option.textContent.toLowerCase().includes(filter) ? '' : 'none';
                });
            }
        });

        // Hide dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.classList.contains('search-input')) {
                document.querySelectorAll('.dropdown-container ul').forEach(dropdown => {
                    dropdown.style.display = 'none';
                });
            }
            if (event.target.tagName === 'LI') {
                const input = event.target.closest('.dropdown-container').querySelector('.search-input');
                input.value = event.target.textContent;
                event.target.closest('ul').style.display = 'none';
            }
        });
        
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