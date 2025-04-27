<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "u522875338_PACE_DB";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate dues for ALL experts
function calculateAllExpertsDues($conn) {
    $sql = "
        UPDATE expert e
        JOIN (
            SELECT 
                e.expert_id,
                COALESCE(e.initial_dues, 0) 
                + COALESCE(SUM(CASE WHEN ta.expert_id_1 = e.expert_id THEN ta.expert_price1 ELSE 0 END), 0) 
                + COALESCE(SUM(CASE WHEN ta.expert_id_2 = e.expert_id THEN ta.expert_price2 ELSE 0 END), 0) 
                + COALESCE(SUM(CASE WHEN ta.expert_id_3 = e.expert_id THEN ta.expert_price3 ELSE 0 END), 0) 
                - COALESCE(payments.total_paid, 0) AS total_dues
            FROM expert e
            LEFT JOIN task ta ON e.expert_id IN (ta.expert_id_1, ta.expert_id_2, ta.expert_id_3)
            LEFT JOIN (
                SELECT expert_id, SUM(amount_paid) AS total_paid 
                FROM tlexpertpayment 
                GROUP BY expert_id
            ) payments ON e.expert_id = payments.expert_id
            GROUP BY e.expert_id
        ) AS calc ON e.expert_id = calc.expert_id
        SET e.dues = calc.total_dues
    ";

    if (!$conn->query($sql)) {
        die("Error updating dues for all experts: " . $conn->error);
    }
}

// Call dues calculation for ALL experts when the page loads
calculateAllExpertsDues($conn);

// Handle filtering
$filter_column = isset($_POST['filter_column']) ? $_POST['filter_column'] : '';
$filter_value = isset($_POST['filter_value']) ? $_POST['filter_value'] : '';

// Handle sorting
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'default';

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $expert_name = $_POST['expert_name'];
        $mobile_no = $_POST['mobile_no'];
        $initial_dues = $_POST['initial_dues'];
        $specialization = $_POST['specialization'] ?? null;

        $sql = "INSERT INTO expert (expert_name, mobile_no, initial_dues, specialization) 
                VALUES ('$expert_name', '$mobile_no', '$initial_dues', '$specialization')";
        if ($conn->query($sql) === TRUE) {
            calculateAllExpertsDues($conn);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    if (isset($_POST['edit'])) {
        $expert_id = $_POST['expert_id'];
        $expert_name = $_POST['expert_name'];
        $mobile_no = $_POST['mobile_no'];
        $initial_dues = $_POST['initial_dues'];
        $specialization = $_POST['specialization'] ?? null;

        $sql = "UPDATE expert 
                SET expert_name='$expert_name', mobile_no='$mobile_no', initial_dues='$initial_dues', specialization='$specialization' 
                WHERE expert_id='$expert_id'";
        if ($conn->query($sql) === TRUE) {
            calculateAllExpertsDues($conn);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    if (isset($_POST['delete'])) {
        $expert_id = $_POST['expert_id'];
        $sql = "DELETE FROM expert WHERE expert_id='$expert_id'";
        if ($conn->query($sql) === TRUE) {
            calculateAllExpertsDues($conn);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Base SQL query
$sql = "
    SELECT 
        e.expert_id, 
        e.expert_name, 
        e.mobile_no, 
        e.initial_dues,
        e.specialization,
        e.dues AS total_dues
    FROM expert e
";

// Add filtering
if (!empty($filter_column) && !empty($filter_value)) {
    $sql .= " WHERE $filter_column LIKE '%$filter_value%'";
}

// Add sorting
if ($sort_order === 'asc') {
    $sql .= " ORDER BY e.dues ASC";
} elseif ($sort_order === 'desc') {
    $sql .= " ORDER BY e.dues DESC";
}

$result = $conn->query($sql);

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
    <title>Expert Management</title>
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
            padding-bottom: 120px;
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
                <h1 class="page-title"><i class="fas fa-user-graduate me-2"></i>Expert Management</h1>
            </div>
            <div class="user-menu">
                <span class="d-none d-md-inline">Welcome, Admin</span>
                <img src="bg.jpg" alt="User" class="user-avatar">
            </div>
        </div>
        
        <!-- Add Expert Card -->
        <div class="dashboard-card fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Expert</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="expert_name" class="form-control" placeholder="Expert Name" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="mobile_no" class="form-control" placeholder="Mobile Number" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="initial_dues" class="form-control" placeholder="Initial Dues" required>
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="specialization" class="form-control" placeholder="Specialization">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="add" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Filter Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Experts</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select name="filter_column" id="filter_column" class="form-control" required onchange="toggleFilterInput()">
                                <option value="expert_name">Expert Name</option>
                                <option value="mobile_no">Mobile Number</option>
                                <option value="specialization">Specialization</option>
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
                <h5 class="mb-0"><i class="fas fa-sort me-2"></i>Sort Experts</h5>
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
                            <button type="submit" name="sort" value="default" class="btn btn-secondary w-100">
                                <i class="fas fa-bars me-2"></i>Default Order
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Experts List Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.3s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Experts List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>SL No</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Initial Dues</th>
                                <th>Specialization</th>
                                <th>Total Dues</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sl_no = 1;
                            while ($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo $sl_no++; ?></td>
                                    <td><?php echo $row['expert_id']; ?></td>
                                    <td><?php echo $row['expert_name']; ?></td>
                                    <td><?php echo $row['mobile_no']; ?></td>
                                    <td>₹<?php echo number_format($row['initial_dues'], 2); ?></td>
                                    <td><?php echo $row['specialization']; ?></td>
                                    <td><strong>₹<?php echo number_format($row['total_dues'], 2); ?></strong></td>
                                    <td>
                                        <div class="d-flex">
                                            <!-- Edit Button -->
                                            <button onclick="toggleEditForm(<?php echo $row['expert_id']; ?>)" class="btn btn-warning btn-sm me-2">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <!-- Delete Form -->
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this expert?');">
                                                <input type="hidden" name="expert_id" value="<?php echo $row['expert_id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                        <!-- Edit Form -->
                                        <div id="edit-form-<?php echo $row['expert_id']; ?>" class="edit-form mt-3">
                                            <form method="POST">
                                                <input type="hidden" name="expert_id" value="<?php echo $row['expert_id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-3">
                                                        <label class="form-label">Expert Name</label>
                                                        <input type="text" name="expert_name" class="form-control" value="<?php echo $row['expert_name']; ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Mobile Number</label>
                                                        <input type="number" name="mobile_no" class="form-control" value="<?php echo $row['mobile_no']; ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Initial Dues</label>
                                                        <input type="number" name="initial_dues" class="form-control" value="<?php echo $row['initial_dues']; ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Specialization</label>
                                                        <input type="text" name="specialization" class="form-control" value="<?php echo $row['specialization']; ?>">
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
        function toggleEditForm(expertId) {
            const editForm = document.getElementById(`edit-form-${expertId}`);
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
            } else {
                editForm.style.display = 'none';
            }
        }
        
        function toggleFilterInput() {
            const filterColumn = document.getElementById("filter_column").value;
            const filterValueContainer = document.getElementById("filter_value_container");

            // Show text input for all columns
            filterValueContainer.innerHTML = `
                <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>
            `;
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