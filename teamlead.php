<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../task.php"); // Adjust the path if needed
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

// Helper function to update team lead dues
function updateTeamLeadDues($conn, $team_lead_id = null) {
    // If a specific team lead ID is provided, update only that record
    // Otherwise, update all team leads
    $where_clause = $team_lead_id ? "WHERE tl.team_lead_id = $team_lead_id" : "";
    
    $update_sql = "
        UPDATE teamlead tl
        LEFT JOIN (
            SELECT 
                team_lead_id,
                COALESCE(SUM(tl_price), 0) AS total_tasks
            FROM task
            GROUP BY team_lead_id
        ) t ON tl.team_lead_id = t.team_lead_id
        LEFT JOIN (
            SELECT 
                tl_id,
                COALESCE(SUM(amount_paid), 0) AS total_payments
            FROM admintlpayment
            GROUP BY tl_id
        ) atp ON tl.team_lead_id = atp.tl_id
        SET tl.dues = COALESCE(tl.initial_due, 0) + 
                     COALESCE(t.total_tasks, 0) - 
                     COALESCE(atp.total_payments, 0)
        $where_clause
    ";
    
    if ($conn->query($update_sql) !== TRUE) {
        echo "Error updating dues: " . $conn->error;
    }
}

// First, add the dues column if it doesn't exist
$check_column_sql = "
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = '$dbname' 
    AND TABLE_NAME = 'teamlead' 
    AND COLUMN_NAME = 'dues'
";

$check_result = $conn->query($check_column_sql);
if ($check_result->num_rows == 0) {
    // Column doesn't exist, add it
    $add_column_sql = "ALTER TABLE teamlead ADD COLUMN dues DECIMAL(10,2) DEFAULT 0";
    if ($conn->query($add_column_sql) !== TRUE) {
        echo "Error adding dues column: " . $conn->error;
    } else {
        // Initialize dues for all team leads
        updateTeamLeadDues($conn);
    }
}

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $name = $_POST['name'];
        $mobile_no = $_POST['mobile_no'];
        $initial_due = $_POST['initial_due'];

        // Insert new team lead with initial due
        $sql = "INSERT INTO teamlead (name, mobile_no, initial_due, dues) 
                VALUES ('$name', '$mobile_no', '$initial_due', '$initial_due')";
        if ($conn->query($sql) === TRUE) {
            // Get the ID of the newly inserted team lead
            $team_lead_id = $conn->insert_id;
            // Update dues for the new team lead
            updateTeamLeadDues($conn, $team_lead_id);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['edit'])) {
        $team_lead_id = $_POST['team_lead_id'];
        $name = $_POST['name'];
        $mobile_no = $_POST['mobile_no'];
        $initial_due = $_POST['initial_due'];

        // Update team lead
        $sql = "UPDATE teamlead SET name='$name', mobile_no='$mobile_no', initial_due='$initial_due' 
                WHERE team_lead_id='$team_lead_id'";
        if ($conn->query($sql) === TRUE) {
            // Update dues for this team lead
            updateTeamLeadDues($conn, $team_lead_id);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete'])) {
        $team_lead_id = $_POST['team_lead_id'];
        $sql = "DELETE FROM teamlead WHERE team_lead_id='$team_lead_id'";
        if ($conn->query($sql) === TRUE) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Update all team lead dues to ensure they're current
updateTeamLeadDues($conn);

// Handle sorting
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'default'; // Default is no sorting

// Base SQL query - now using the stored dues value
$sql = "SELECT team_lead_id, name, mobile_no, initial_due, dues FROM teamlead";

// Add sorting to the query
if ($sort_order === 'asc') {
    $sql .= " ORDER BY dues ASC";
} elseif ($sort_order === 'desc') {
    $sql .= " ORDER BY dues DESC";
}

// Execute query
$result = $conn->query($sql);

// Debugging - check for SQL errors
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
    <title>TeamLead Management</title>
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
                <h1 class="page-title"><i class="fas fa-user-tie me-2"></i>Team Lead Management</h1>
            </div>
            <div class="user-menu">
                <span class="d-none d-md-inline">Welcome, Admin</span>
                <img src="bg.jpg" alt="User" class="user-avatar">
            </div>
        </div>
        
        <!-- Add Team Lead Card -->
        <div class="dashboard-card fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Team Lead</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="name" class="form-control" placeholder="Team Lead Name" required>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="mobile_no" class="form-control" placeholder="Mobile Number" required>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="initial_due" class="form-control" placeholder="Initial Due" required step="0.01">
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
        
        <!-- Sorting Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sort me-2"></i>Sort Team Leads</h5>
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
        
        <!-- Team Leads List Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.2s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Team Leads List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Initial Due</th>
                                <th>Total Due</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            while ($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $row['team_lead_id']; ?></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['mobile_no']; ?></td>
                                    <td>₹<?php echo number_format($row['initial_due'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($row['dues'], 2); ?></strong></td>
                                    <td>
                                        <div class="d-flex">
                                            <!-- Edit Button -->
                                            <button onclick="toggleEditForm(<?php echo $row['team_lead_id']; ?>)" class="btn btn-warning btn-sm me-2">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <!-- Delete Form -->
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this team lead?');">
                                                <input type="hidden" name="team_lead_id" value="<?php echo $row['team_lead_id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                        <!-- Edit Form -->
                                        <div id="edit-form-<?php echo $row['team_lead_id']; ?>" class="edit-form mt-3">
                                            <form method="POST">
                                                <input type="hidden" name="team_lead_id" value="<?php echo $row['team_lead_id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Team Lead Name</label>
                                                        <input type="text" name="name" class="form-control" value="<?php echo $row['name']; ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Mobile Number</label>
                                                        <input type="number" name="mobile_no" class="form-control" value="<?php echo $row['mobile_no']; ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Initial Due</label>
                                                        <input type="number" name="initial_due" class="form-control" value="<?php echo $row['initial_due']; ?>" required step="0.01">
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
        function toggleEditForm(teamLeadId) {
            const editForm = document.getElementById(`edit-form-${teamLeadId}`);
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