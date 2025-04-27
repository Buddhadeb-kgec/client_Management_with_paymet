<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../index.php"); // Adjust the path if needed
    exit(); // Stop further execution of the script
}

$servername = "localhost";
$username = "root";    // XAMPP default username
$password = "";        // XAMPP default (blank password)
$dbname = "u522875338_PACE_DB";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Team Leads for dropdown
$team_leads = $conn->query("SELECT team_lead_id, name FROM teamlead");
// Fetch Experts for dropdown
$experts = $conn->query("SELECT expert_id, expert_name FROM expert");
// Fetch Clients for dropdown
$clients = $conn->query("SELECT client_id, client_name FROM client");

// Handle form submission for adding a task
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $task_name = $_POST['task_name'];
        $description = !empty($_POST['description']) ? $_POST['description'] : NULL; // Allow NULL
        $team_lead_id = $_POST['team_lead_id'];
        $expert_id_1 = !empty($_POST['expert_id_1']) ? $_POST['expert_id_1'] : NULL; // Allow NULL
        $expert_id_2 = !empty($_POST['expert_id_2']) ? $_POST['expert_id_2'] : NULL; // Allow NULL
        $expert_id_3 = !empty($_POST['expert_id_3']) ? $_POST['expert_id_3'] : NULL; // Allow NULL
        $client_id = $_POST['client_id'];
        $price = $_POST['price'];
        $tl_price = $_POST['tl_price'];
        $expert_price1 = !empty($_POST['expert_price1']) ? $_POST['expert_price1'] : 0; // Default to 0 if no expert
        $expert_price2 = !empty($_POST['expert_price2']) ? $_POST['expert_price2'] : 0; // Default to 0 if no expert
        $expert_price3 = !empty($_POST['expert_price3']) ? $_POST['expert_price3'] : 0; // Default to 0 if no expert
        $task_date = $_POST['task_date'];
        $due_date = $_POST['due_date'];
        $status = $_POST['status'];
        $word_count = $_POST['word_count'];
        $issue = isset($_POST['issue']) ? implode(",", $_POST['issue']) : NULL; // Handle multiple issues
        $incomplete_information = isset($_POST['incomplete_information']) ? 1 : 0; // Checkbox value

        // Calculate total cost
        $total_cost = $tl_price + $expert_price1 + $expert_price2 + $expert_price3;

        // Fetch team lead name
        $team_lead_name = $conn->query("SELECT name FROM teamlead WHERE team_lead_id = $team_lead_id")->fetch_assoc()['name'];

        // Fetch expert names
        $expert_name_1 = $expert_id_1 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_1")->fetch_assoc()['expert_name'] : NULL;
        $expert_name_2 = $expert_id_2 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_2")->fetch_assoc()['expert_name'] : NULL;
        $expert_name_3 = $expert_id_3 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_3")->fetch_assoc()['expert_name'] : NULL;

        // Insert task into the database
        $stmt = $conn->prepare("INSERT INTO task (task_name, description, team_lead_id, assigned_team_lead_name, expert_id_1, assigned_expert_1, expert_id_2, assigned_expert_2, expert_id_3, assigned_expert_3, client_id, price, tl_price, expert_price1, expert_price2, expert_price3, task_date, due_date, status, word_count, issue, total_cost, incomplete_information) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisssssssiiiiiisssisii", $task_name, $description, $team_lead_id, $team_lead_name, $expert_id_1, $expert_name_1, $expert_id_2, $expert_name_2, $expert_id_3, $expert_name_3, $client_id, $price, $tl_price, $expert_price1, $expert_price2, $expert_price3, $task_date, $due_date, $status, $word_count, $issue, $total_cost, $incomplete_information);
        $stmt->execute();

        // Update dues for the client
        $conn->query("UPDATE client SET due_payment = due_payment + $price WHERE client_id = $client_id");

        // Update dues for the team lead
        $conn->query("UPDATE teamlead SET dues = dues + $tl_price WHERE team_lead_id = $team_lead_id");

        // Update dues for experts (only if expert is assigned)
        if ($expert_id_1) {
            $conn->query("UPDATE expert SET dues = dues + $expert_price1 WHERE expert_id = $expert_id_1");
        }
        if ($expert_id_2) {
            $conn->query("UPDATE expert SET dues = dues + $expert_price2 WHERE expert_id = $expert_id_2");
        }
        if ($expert_id_3) {
            $conn->query("UPDATE expert SET dues = dues + $expert_price3 WHERE expert_id = $expert_id_3");
        }

        header("Location: task.php");
        exit();
    }

    // Handle editing a task
    if (isset($_POST['edit'])) {
        $task_id = $_POST['task_id'];
        $task_name = $_POST['task_name'];
        $description = !empty($_POST['description']) ? $_POST['description'] : NULL; // Allow NULL
        $team_lead_id = $_POST['team_lead_id'];
        $expert_id_1 = !empty($_POST['expert_id_1']) ? $_POST['expert_id_1'] : NULL; // Allow NULL
        $expert_id_2 = !empty($_POST['expert_id_2']) ? $_POST['expert_id_2'] : NULL; // Allow NULL
        $expert_id_3 = !empty($_POST['expert_id_3']) ? $_POST['expert_id_3'] : NULL; // Allow NULL
        $client_id = $_POST['client_id'];
        $price = $_POST['price'];
        $tl_price = $_POST['tl_price'];
        $expert_price1 = !empty($_POST['expert_price1']) ? $_POST['expert_price1'] : 0; // Default to 0 if no expert
        $expert_price2 = !empty($_POST['expert_price2']) ? $_POST['expert_price2'] : 0; // Default to 0 if no expert
        $expert_price3 = !empty($_POST['expert_price3']) ? $_POST['expert_price3'] : 0; // Default to 0 if no expert
        $task_date = $_POST['task_date'];
        $due_date = $_POST['due_date'];
        $status = $_POST['status'];
        $word_count = $_POST['word_count'];
        $issue = isset($_POST['issue']) ? implode(",", $_POST['issue']) : NULL; // Handle multiple issues
        $incomplete_information = isset($_POST['incomplete_information']) ? 1 : 0; // Checkbox value

        // Calculate total cost
        $total_cost = $tl_price + $expert_price1 + $expert_price2 + $expert_price3;

        // Fetch team lead name
        $team_lead_name = $conn->query("SELECT name FROM teamlead WHERE team_lead_id = $team_lead_id")->fetch_assoc()['name'];

        // Fetch expert names
        $expert_name_1 = $expert_id_1 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_1")->fetch_assoc()['expert_name'] : NULL;
        $expert_name_2 = $expert_id_2 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_2")->fetch_assoc()['expert_name'] : NULL;
        $expert_name_3 = $expert_id_3 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_3")->fetch_assoc()['expert_name'] : NULL;

        // Update task in the database
        $stmt = $conn->prepare("UPDATE task SET task_name=?, description=?, team_lead_id=?, assigned_team_lead_name=?, expert_id_1=?, assigned_expert_1=?, expert_id_2=?, assigned_expert_2=?, expert_id_3=?, assigned_expert_3=?, client_id=?, price=?, tl_price=?, expert_price1=?, expert_price2=?, expert_price3=?, task_date=?, due_date=?, status=?, word_count=?, issue=?, total_cost=?, incomplete_information=? WHERE task_id=?");
        $stmt->bind_param("ssisssssssiiiiiisssisiii", $task_name, $description, $team_lead_id, $team_lead_name, $expert_id_1, $expert_name_1, $expert_id_2, $expert_name_2, $expert_id_3, $expert_name_3, $client_id, $price, $tl_price, $expert_price1, $expert_price2, $expert_price3, $task_date, $due_date, $status, $word_count, $issue, $total_cost, $incomplete_information, $task_id);
        $stmt->execute();

        $redirect_url = "task.php";
        if (!empty($_POST['filter_column']) && !empty($_POST['filter_value'])) {
            $redirect_url .= "?filter_column=" . urlencode($_POST['filter_column']) . "&filter_value=" . urlencode($_POST['filter_value']);
        }
        header("Location: " . $redirect_url);
        exit();
    }

    // Handle deleting a task
    if (isset($_POST['delete'])) {
        $task_id = $_POST['task_id'];
        $conn->query("DELETE FROM task WHERE task_id = $task_id");

        $redirect_url = "task.php";
        if (!empty($_POST['filter_column']) && !empty($_POST['filter_value'])) {
            $redirect_url .= "?filter_column=" . urlencode($_POST['filter_column']) . "&filter_value=" . urlencode($_POST['filter_value']);
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// Handle filtering
$filter_column = isset($_POST['filter_column']) ? $_POST['filter_column'] : (isset($_GET['filter_column']) ? $_GET['filter_column'] : '');
$filter_value = isset($_POST['filter_value']) ? $_POST['filter_value'] : (isset($_GET['filter_value']) ? $_GET['filter_value'] : '');

// Handle sorting
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'default'; // Default is no sorting
$next_order = $sort_order === 'asc' ? 'desc' : 'asc';

// Base SQL query for tasks
$sql = "SELECT * FROM task";

// Add filtering to the query
if (!empty($filter_column) && !empty($filter_value)) {
    $sql .= " WHERE $filter_column LIKE ?";
}

// Add sorting to the query
if ($sort_order === 'asc') {
    $sql .= " ORDER BY price ASC";
} elseif ($sort_order === 'desc') {
    $sql .= " ORDER BY price DESC";
}

// Execute query
if (!empty($filter_column) && !empty($filter_value)) {
    $stmt = $conn->prepare($sql);
    $search_param = "%" . $filter_value . "%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Update total_cost for existing tasks
$tasks_to_update = $conn->query("SELECT task_id, tl_price, expert_price1, expert_price2, expert_price3 FROM task");
while ($task = $tasks_to_update->fetch_assoc()) {
    $total_cost = $task['tl_price'] + $task['expert_price1'] + $task['expert_price2'] + $task['expert_price3'];
    $conn->query("UPDATE task SET total_cost = $total_cost WHERE task_id = {$task['task_id']}");
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
    <title>Task Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        
        /* Failed row styling */
        .failed-row {
            background-color: #ff7575;
        }
        
        /* Dropdown container styles */
        .dropdown-container {
            position: relative;
        }
        
        .dropdown-list {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ccc;
            background-color: #fff;
            display: none;
        }
        
        .dropdown-list li {
            padding: 8px;
            cursor: pointer;
        }
        
        .dropdown-list li:hover {
            background-color: #f1f1f1;
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
        
        /* Checkbox styling */
        .form-check-input {
            margin-right: 5px;
        }
        
        /* Select2 adjustments */
        .select2-container .select2-selection--single {
            height: 45px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 45px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 45px;
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
                <h1 class="page-title"><i class="fas fa-tasks me-2"></i>Task Management</h1>
            </div>
            <div class="user-menu">
                <span class="d-none d-md-inline">Welcome, Admin</span>
                <img src="bg.jpg" alt="User" class="user-avatar">
            </div>
        </div>
        
        <!-- Add Task Card -->
        <div class="dashboard-card fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Task</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <!-- First row -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="incomplete_information" class="form-check-input" id="incomplete_information">
                                <label class="form-check-label" for="incomplete_information">Incomplete Information</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Second row -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="client_id" class="form-label">Client:</label>
                            <div class="dropdown-container">
                                <input type="text" id="client-search" class="form-control" placeholder="Search client...">
                                <ul class="dropdown-list" id="client-list">
                                    <?php while ($row = $clients->fetch_assoc()) { echo "<li data-value='{$row['client_id']}'>{$row['client_name']}</li>"; } ?>
                                </ul>
                                <input type="hidden" name="client_id" id="client-id">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="task_name" class="form-label">Task Name:</label>
                            <input type="text" name="task_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price" class="form-label">Price:</label>
                            <input type="number" name="price" class="form-control" required>
                        </div>
                    </div>

                    <!-- Third row -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="word_count" class="form-label">Word Count:</label>
                            <input type="number" name="word_count" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label for="description" class="form-label">Description:</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <!-- Fourth row -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="task_date" class="form-label">Date:</label>
                            <input type="date" name="task_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="due_date" class="form-label">Due Date:</label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                    </div>

                    <!-- Fifth row -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status:</label>
                            <select name="status" class="form-control select2" required>
                                <option value="in progress">In Progress</option>
                                <option value="submitted">Submitted</option>
                                <option value="passed">Passed</option>
                                <option value="failed">Failed</option>
                                <option value="submitted late">Submitted Late</option>
                            </select>
                        </div>
                        <div class="col-md-6">     
                            <label for="issue" class="form-label">Issue:</label>     
                            <div class="form-check">         
                                <input type="checkbox" name="issue[]" value="Low marks"> Low marks<br>         
                                <input type="checkbox" name="issue[]" value="Brief not followed"> Brief not followed<br>         
                                <input type="checkbox" name="issue[]" value="Word count lower"> Word count lower<br>         
                                <input type="checkbox" name="issue[]" value="Wordcount higher"> Wordcount higher<br>         
                                <input type="checkbox" name="issue[]" value="Referencing irrelevant"> Referencing irrelevant<br>         
                                <input type="checkbox" name="issue[]" value="AI used"> AI used<br>         
                                <input type="checkbox" name="issue[]" value="Plagiarism"> Plagiarism<br>         
                                <input type="checkbox" name="issue[]" value="Poor quality"> Poor quality<br>         
                                <input type="checkbox" name="issue[]" value="Money Less Taken"> Money Less Taken<br>
                            </div> 
                        </div>
                    </div>

                    <!-- Team Assignment Card -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">Team Assignment</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Role</th>
                                        <th>Person</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Team Lead</td>
                                        <td>
                                            <div class="dropdown-container">
                                                <input type="text" id="team-lead-search" class="form-control" placeholder="Search team lead...">
                                                <ul class="dropdown-list" id="team-lead-list">
                                                    <?php while ($row = $team_leads->fetch_assoc()) { echo "<li data-value='{$row['team_lead_id']}'>{$row['name']}</li>"; } ?>
                                                </ul>
                                                <input type="hidden" name="team_lead_id" id="team-lead-id">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="tl_price" class="form-control" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Expert 1</td>
                                        <td>
                                            <div class="dropdown-container">
                                                <input type="text" id="expert1-search" class="form-control" placeholder="Search expert...">
                                                <ul class="dropdown-list" id="expert1-list">
                                                    <?php $experts->data_seek(0); while ($row = $experts->fetch_assoc()) { echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>"; } ?>
                                                </ul>
                                                <input type="hidden" name="expert_id_1" id="expert1-id">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="expert_price1" class="form-control">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Expert 2</td>
                                        <td>
                                            <div class="dropdown-container">
                                                <input type="text" id="expert2-search" class="form-control" placeholder="Search expert...">
                                                <ul class="dropdown-list" id="expert2-list">
                                                    <?php $experts->data_seek(0); while ($row = $experts->fetch_assoc()) { echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>"; } ?>
                                                </ul>
                                                <input type="hidden" name="expert_id_2" id="expert2-id">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="expert_price2" class="form-control">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Expert 3</td>
                                        <td>
                                            <div class="dropdown-container">
                                                <input type="text" id="expert3-search" class="form-control" placeholder="Search expert...">
                                                <ul class="dropdown-list" id="expert3-list">
                                                    <?php $experts->data_seek(0); while ($row = $experts->fetch_assoc()) { echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>"; } ?>
                                                </ul>
                                                <input type="hidden" name="expert_id_3" id="expert3-id">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="expert_price3" class="form-control">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" name="add" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add Task
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Copy Details Button -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-body">
                <button type="button" id="copyDetailsBtn" class="btn btn-info w-100">
                    <i class="fas fa-copy me-2"></i>Copy Task Details
                </button>
            </div>
        </div>
        
        <!-- Filter Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.2s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Tasks</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select name="filter_column" id="filter_column" class="form-control select2" required onchange="updateFilterInput()">
                                <option value="task_name">Task Name</option>
                                <option value="team_lead_id">Team Lead</option>
                                <option value="expert_id_1">Expert 1</option>
                                <option value="expert_id_2">Expert 2</option>
                                <option value="expert_id_3">Expert 3</option>
                                <option value="client_id">Client</option>
                                <option value="price">Price</option>
                                <option value="task_date">Task Date</option>
                                <option value="due_date">Due Date</option>
                                <option value="status">Status</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="filter_input_container">
                            <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Sorting Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.3s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sort me-2"></i>Sort Tasks</h5>
            </div>
            <div class="card-body">
                <form method="get">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <button type="submit" name="sort" value="asc" class="btn btn-secondary w-100">
                                <i class="fas fa-sort-amount-up me-2"></i>Price (Low to High)
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="sort" value="desc" class="btn btn-secondary w-100">
                                <i class="fas fa-sort-amount-down me-2"></i>Price (High to Low)
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="sort" value="default" class="btn btn-secondary w-100">
                                <i class="fas fa-bars me-2"></i>Default Order
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tasks List Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.4s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Tasks List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>SL No</th>
                                <th>Task Name</th>
                                <th>Description</th>
                                <th>Tl</th>
                                <th>Expert1</th>
                                <th>Expert2</th>
                                <th>Expert3</th>
                                <th>Client</th>
                                <th>Price</th>
                                <th>Tl Price</th>
                                <th>Expert1 Price</th>
                                <th>Expert2 Price</th>
                                <th>Expert3 Price</th>
                                <th>Total Cost</th>
                                <th>Task Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Word Count</th>
                                <th>Issue</th>
                                <th>Incomplete Info</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch team leads, experts, and clients again for display
                            $team_leads_display = $conn->query("SELECT team_lead_id, name FROM teamlead");
                            $experts_display = $conn->query("SELECT expert_id, expert_name FROM expert");
                            $clients_display = $conn->query("SELECT client_id, client_name FROM client");
                            
                            // Create associative arrays for quick lookup
                            $team_lead_names = [];
                            while ($row = $team_leads_display->fetch_assoc()) {
                                $team_lead_names[$row['team_lead_id']] = $row['name'];
                            }
                            
                            $expert_names = [];
                            while ($row = $experts_display->fetch_assoc()) {
                                $expert_names[$row['expert_id']] = $row['expert_name'];
                            }
                            
                            $client_names = [];
                            while ($row = $clients_display->fetch_assoc()) {
                                $client_names[$row['client_id']] = $row['client_name'];
                            }
                            
                            $serial_no = 1; // Initialize serial number counter
                            while ($row = $result->fetch_assoc()) { 
                                $rowClass = $row['status'] === 'failed' ? 'failed-row' : '';
                            ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td><?php echo $serial_no++; ?></td>
                                    <td><?php echo $row['task_name']; ?></td>
                                    <td><?php echo $row['description'] ?? 'No description'; ?></td>
                                    <td><?php echo $row['assigned_team_lead_name']; ?></td>
                                    <td><?php echo $row['assigned_expert_1'] ?? 'None'; ?></td>
                                    <td><?php echo $row['assigned_expert_2'] ?? 'None'; ?></td>
                                    <td><?php echo $row['assigned_expert_3'] ?? 'None'; ?></td>
                                    <td><?php echo $client_names[$row['client_id']] . " (ID: " . $row['client_id'] . ")"; ?></td>
                                    <td><?php echo $row['price']; ?></td>
                                    <td><?php echo $row['tl_price']; ?></td>
                                    <td><?php echo $row['expert_price1']; ?></td>
                                    <td><?php echo $row['expert_price2']; ?></td>
                                    <td><?php echo $row['expert_price3']; ?></td>
                                    <td><?php echo $row['total_cost']; ?></td>
                                    <td><?php echo $row['task_date'] ? date('d/m/Y', strtotime($row['task_date'])) : ''; ?></td>
                                    <td><?php echo $row['due_date'] ? date('d/m/Y', strtotime($row['due_date'])) : ''; ?></td>
                                    <td><?php echo $row['status']; ?></td>
                                    <td><?php echo $row['word_count']; ?></td>
                                    <td><?php echo $row['issue']; ?></td>
                                    <td><?php echo $row['incomplete_information'] ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <div class="d-flex">
                                            <!-- Edit Button -->
                                            <button onclick="toggleEditForm(<?php echo $row['task_id']; ?>)" class="btn btn-warning btn-sm me-2">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <!-- Delete Form -->
                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="task_id" value="<?php echo $row['task_id']; ?>">
                                                <input type="hidden" name="filter_column" value="<?php echo htmlspecialchars($filter_column); ?>">
                                                <input type="hidden" name="filter_value" value="<?php echo htmlspecialchars($filter_value); ?>">
                                                <button type="submit" name="delete" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                        <!-- Edit Form -->
                                        <div id="edit-form-<?php echo $row['task_id']; ?>" class="edit-form mt-3">
                                            <form method="post">
                                                <input type="hidden" name="task_id" value="<?php echo $row['task_id']; ?>">
                                                <input type="hidden" name="filter_column" value="<?php echo htmlspecialchars($filter_column); ?>">
                                                <input type="hidden" name="filter_value" value="<?php echo htmlspecialchars($filter_value); ?>">
                                                <div class="row g-3">
                                                    <!-- Client -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Client:</label>
                                                        <select name="client_id" class="form-control select2" required>
                                                            <?php $clients->data_seek(0); while ($client = $clients->fetch_assoc()) { echo "<option value='{$client['client_id']}'" . ($client['client_id'] == $row['client_id'] ? ' selected' : '') . ">{$client['client_name']}</option>"; } ?>
                                                        </select>
                                                    </div>
                                                    <!-- Task Name -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Task Name:</label>
                                                        <input type="text" name="task_name" class="form-control" value="<?php echo $row['task_name']; ?>" required>
                                                    </div>
                                                    <!-- Description -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Description:</label>
                                                        <textarea name="description" class="form-control" rows="3"><?php echo $row['description']; ?></textarea>
                                                    </div>
                                                    <!-- Team Lead -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Team Lead:</label>
                                                        <select name="team_lead_id" class="form-control select2" required>
                                                            <?php $team_leads->data_seek(0); while ($lead = $team_leads->fetch_assoc()) { echo "<option value='{$lead['team_lead_id']}'" . ($lead['team_lead_id'] == $row['team_lead_id'] ? ' selected' : '') . ">{$lead['name']}</option>"; } ?>
                                                        </select>
                                                    </div>
                                                    <!-- Expert 1 (Optional) -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Expert 1 (Optional):</label>
                                                        <select name="expert_id_1" class="form-control select2">
                                                            <option value="">None</option>
                                                            <?php $experts->data_seek(0); while ($expert = $experts->fetch_assoc()) { echo "<option value='{$expert['expert_id']}'" . ($expert['expert_id'] == $row['expert_id_1'] ? ' selected' : '') . ">{$expert['expert_name']}</option>"; } ?>
                                                        </select>
                                                    </div>
                                                    <!-- Expert 2 (Optional) -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Expert 2 (Optional):</label>
                                                        <select name="expert_id_2" class="form-control select2">
                                                            <option value="">None</option>
                                                            <?php $experts->data_seek(0); while ($expert = $experts->fetch_assoc()) { echo "<option value='{$expert['expert_id']}'" . ($expert['expert_id'] == $row['expert_id_2'] ? ' selected' : '') . ">{$expert['expert_name']}</option>"; } ?>
                                                        </select>
                                                    </div>
                                                    <!-- Expert 3 (Optional) -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Expert 3 (Optional):</label>
                                                        <select name="expert_id_3" class="form-control select2">
                                                            <option value="">None</option>
                                                            <?php $experts->data_seek(0); while ($expert = $experts->fetch_assoc()) { echo "<option value='{$expert['expert_id']}'" . ($expert['expert_id'] == $row['expert_id_3'] ? ' selected' : '') . ">{$expert['expert_name']}</option>"; } ?>
                                                        </select>
                                                    </div>
                                                    <!-- Price -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Price:</label>
                                                        <input type="number" name="price" class="form-control" value="<?php echo $row['price']; ?>" required>
                                                    </div>
                                                    <!-- Team Lead Price -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Team Lead Price:</label>
                                                        <input type="number" name="tl_price" class="form-control" value="<?php echo $row['tl_price']; ?>" required>
                                                    </div>
                                                    <!-- Expert 1 Price (Optional) -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Expert 1 Price (Optional):</label>
                                                        <input type="number" name="expert_price1" class="form-control" value="<?php echo $row['expert_price1']; ?>">
                                                    </div>
                                                    <!-- Expert 2 Price (Optional) -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Expert 2 Price (Optional):</label>
                                                        <input type="number" name="expert_price2" class="form-control" value="<?php echo $row['expert_price2']; ?>">
                                                    </div>
                                                    <!-- Expert 3 Price (Optional) -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Expert 3 Price (Optional):</label>
                                                        <input type="number" name="expert_price3" class="form-control" value="<?php echo $row['expert_price3']; ?>">
                                                    </div>
                                                    <!-- Task Date -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Task Date:</label>
                                                        <input type="date" name="task_date" class="form-control" value="<?php echo $row['task_date']; ?>" required>
                                                    </div>
                                                    <!-- Due Date -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Due Date:</label>
                                                        <input type="date" name="due_date" class="form-control" value="<?php echo $row['due_date']; ?>" required>
                                                    </div>
                                                    <!-- Status -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Status:</label>
                                                        <select name="status" class="form-control select2" required>
                                                            <option value="in progress" <?php echo $row['status'] == 'in progress' ? 'selected' : ''; ?>>In Progress</option>
                                                            <option value="submitted" <?php echo $row['status'] == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                                            <option value="passed" <?php echo $row['status'] == 'passed' ? 'selected' : ''; ?>>Passed</option>
                                                            <option value="failed" <?php echo $row['status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                            <option value="submitted late" <?php echo $row['status'] == 'submitted late' ? 'selected' : ''; ?>>Submitted Late</option>
                                                        </select>
                                                    </div>
                                                    <!-- Word Count -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Word Count:</label>
                                                        <input type="number" name="word_count" class="form-control" value="<?php echo $row['word_count']; ?>">
                                                    </div>
                                                    <!-- Issue -->
                                                    <div class="col-md-6">     
                                                        <label class="form-label">Issue:</label>     
                                                        <div>         
                                                            <input type="checkbox" name="issue[]" value="Low marks" <?php echo strpos($row['issue'], 'Low marks') !== false ? 'checked' : ''; ?>> Low marks<br>         
                                                            <input type="checkbox" name="issue[]" value="Brief not followed" <?php echo strpos($row['issue'], 'Brief not followed') !== false ? 'checked' : ''; ?>> Brief not followed<br>         
                                                            <input type="checkbox" name="issue[]" value="Word count lower" <?php echo strpos($row['issue'], 'Word count lower') !== false ? 'checked' : ''; ?>> Word count lower<br>         
                                                            <input type="checkbox" name="issue[]" value="Wordcount higher" <?php echo strpos($row['issue'], 'Wordcount higher') !== false ? 'checked' : ''; ?>> Wordcount higher<br>         
                                                            <input type="checkbox" name="issue[]" value="Referencing irrelevant" <?php echo strpos($row['issue'], 'Referencing irrelevant') !== false ? 'checked' : ''; ?>> Referencing irrelevant<br>         
                                                            <input type="checkbox" name="issue[]" value="AI used" <?php echo strpos($row['issue'], 'AI used') !== false ? 'checked' : ''; ?>> AI used<br>         
                                                            <input type="checkbox" name="issue[]" value="Plagiarism" <?php echo strpos($row['issue'], 'Plagiarism') !== false ? 'checked' : ''; ?>> Plagiarism<br>         
                                                            <input type="checkbox" name="issue[]" value="Poor quality" <?php echo strpos($row['issue'], 'Poor quality') !== false ? 'checked' : ''; ?>> Poor quality<br>         
                                                            <input type="checkbox" name="issue[]" value="Money Less Taken" <?php echo strpos($row['issue'], 'Money Less Taken') !== false ? 'checked' : ''; ?>> Money Less Taken<br>
                                                        </div> 
                                                    </div>
                                                    <!-- Incomplete Information -->
                                                    <div class="col-md-6">
                                                        <label class="form-label">Incomplete Information:</label>
                                                        <div class="form-check">
                                                            <input type="checkbox" name="incomplete_information" class="form-check-input" id="incomplete_information_edit" <?php echo $row['incomplete_information'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="incomplete_information_edit">Incomplete Information</label>
                                                        </div>
                                                    </div>
                                                    <!-- Save Button -->
                                                    <div class="col-md-12">
                                                        <button type="submit" name="edit" class="btn btn-success w-100">
                                                            <i class="fas fa-save me-2"></i>Save
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
        
        // Toggle edit form
        function toggleEditForm(taskId) {
            const editForm = document.getElementById(`edit-form-${taskId}`);
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

        // Initialize Select2 for all select elements
        $(document).ready(function() {
            $('.select2').select2();
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

        function updateFilterInput() {
            const filterColumn = document.getElementById('filter_column').value;
            const container = document.getElementById('filter_input_container');
            const specialFilters = ['team_lead_id', 'expert_id_1', 'expert_id_2', 'expert_id_3', 'client_id', 'status'];
            
            if (specialFilters.includes(filterColumn)) {
                let options = '';
                if (filterColumn === 'team_lead_id') {
                    <?php
                    $team_leads->data_seek(0);
                    while ($row = $team_leads->fetch_assoc()) {
                        echo "options += `<option value='{$row['team_lead_id']}'>{$row['name']}</option>`;";
                    }
                    ?>
                } else if (filterColumn.startsWith('expert_id_')) {
                    <?php
                    $experts->data_seek(0);
                    while ($row = $experts->fetch_assoc()) {
                        echo "options += `<option value='{$row['expert_id']}'>{$row['expert_name']}</option>`;";
                    }
                    ?>
                } else if (filterColumn === 'client_id') {
                    <?php
                    $clients->data_seek(0);
                    while ($row = $clients->fetch_assoc()) {
                        echo "options += `<option value='{$row['client_id']}'>{$row['client_name']}</option>`;";
                    }
                    ?>
                } else if (filterColumn === 'status') {
                    // Add status options
                    options += `
                        <option value="in progress">In Progress</option>
                        <option value="submitted">Submitted</option>
                        <option value="passed">Passed</option>
                        <option value="failed">Failed</option>
                        <option value="submitted late">Submitted Late</option>
                    `;
                }
                
                container.innerHTML = `<select name="filter_value" class="form-control select2" required>${options}</select>`;
                $('.select2').select2(); // Reinitialize Select2 for the new dropdown
            } else {
                if (filterColumn === 'price') {
                    container.innerHTML = `<input type="number" name="filter_value" class="form-control" placeholder="Enter price" required>`;
                } else if (filterColumn === 'task_date' || filterColumn === 'due_date') {
                    container.innerHTML = `<input type="date" name="filter_value" class="form-control" required>`;
                } else {
                    container.innerHTML = `<input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>`;
                }
            }
        }

        document.getElementById('copyDetailsBtn').addEventListener('click', function() {
            // Get form values from the input fields
            const taskName = document.querySelector('input[name="task_name"]').value;
            const description = document.querySelector('textarea[name="description"]').value;
            const clientName = document.getElementById('client-search').value; // Get from search input
            const teamLeadName = document.getElementById('team-lead-search').value; // Get from team lead search input
            const taskDate = document.querySelector('input[name="task_date"]').value;
            const dueDate = document.querySelector('input[name="due_date"]').value;
            
            // Get expert names from their search inputs
            const expert1Name = document.getElementById('expert1-search').value;
            const expert2Name = document.getElementById('expert2-search').value;
            const expert3Name = document.getElementById('expert3-search').value;
            
            // Format experts list
            let expertsText = '';
            if (expert1Name) expertsText += expert1Name;
            if (expert2Name) expertsText += expertsText ? ', ' + expert2Name : expert2Name;
            if (expert3Name) expertsText += expertsText ? ', ' + expert3Name : expert3Name;
            expertsText = expertsText || 'None';
            
            // Format the text in the new requested format
            const detailsText = `Code: ${clientName}
Task Title: ${taskName}
Description: ${description}
Assigned to: ${teamLeadName}
Deadline: ${dueDate}
Issue date: ${taskDate}
Expert assigned: ${expertsText}`;

            // Copy to clipboard
            navigator.clipboard.writeText(detailsText)
            .then(() => {
                alert('Task details copied to clipboard!');
            })
            .catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy. Please try again.');
            });
        });
    </script>
</body>
</html>