<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
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
    <title>Database Admin Portal</title>
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
        }
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background-color: white;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
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
        
        /* Quick Access Buttons */
        .quick-access-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 140px;
            border-radius: 12px;
            color: white;
            text-align: center;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .quick-access-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0));
            z-index: 1;
        }
        
        .quick-access-btn i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .quick-access-btn span {
            position: relative;
            z-index: 2;
            font-weight: 500;
        }
        
        .quick-access-btn:hover {
            text-decoration: none;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        /* Color Classes */
        .bg-primary-custom {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
        }
        
        .bg-success-custom {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
        }
        
        .bg-info-custom {
            background: linear-gradient(135deg, #4895ef, #4361ee);
        }
        
        .bg-warning-custom {
            background: linear-gradient(135deg, #f8961e, #f3722c);
        }
        
        .bg-danger-custom {
            background: linear-gradient(135deg, #f94144, #f3722c);
        }
        
        .bg-secondary-custom {
            background: linear-gradient(135deg, #577590, #4a4e69);
        }
        
        .bg-dark-custom {
            background: linear-gradient(135deg, #2b2d42, #1a1a2e);
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
                        <a class="nav-link" href="<?= $link ?>">
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
        <div class="container-fluid p-4">
            <!-- Top Bar -->
            <div class="topbar rounded-3">
                <div>
                    <button class="btn btn-link sidebar-toggle d-lg-none me-3" style="display: none;">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                    <h1 class="page-title"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
                </div>
                <div class="user-menu">
                    <span class="d-none d-md-inline">Welcome, Admin</span>
                    <img src="bg.jpg" alt="User" class="user-avatar">
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <div class="row fade-in">
                <div class="col-lg-12">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Quick Access</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <a href="task.php" class="quick-access-btn bg-primary-custom">
                                        <i class="fas fa-tasks"></i>
                                        <span>Tasks Management</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="client.php" class="quick-access-btn bg-success-custom">
                                        <i class="fas fa-users"></i>
                                        <span>Clients Management</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="visualization.php" class="quick-access-btn bg-info-custom">
                                        <i class="fas fa-file-excel"></i>
                                        <span>Generate Sheets</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="admin_client_payment.php" class="quick-access-btn bg-warning-custom">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>A-Client Payments</span>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <a href="teamlead.php" class="quick-access-btn bg-danger-custom">
                                        <i class="fas fa-user-tie"></i>
                                        <span>Team Leads</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="admin_tl_payment.php" class="quick-access-btn bg-secondary-custom">
                                        <i class="fas fa-hand-holding-usd"></i>
                                        <span>A-TL Payments</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="expert.php" class="quick-access-btn bg-dark-custom">
                                        <i class="fas fa-user-graduate"></i>
                                        <span>Experts</span>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="tl_expert_payment.php" class="quick-access-btn bg-primary-custom">
                                        <i class="fas fa-money-check-alt"></i>
                                        <span>TL-Expert Payments</span>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 offset-md-4">
                                    <a href="colleges.php" class="quick-access-btn bg-success-custom">
                                        <i class="fas fa-university"></i>
                                        <span>Colleges</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Welcome Card -->
            <div class="row fade-in" style="animation-delay: 0.2s;">
                <div class="col-lg-12">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Overview</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="row align-items-center">
                                <div class="col-lg-6 mb-4 mb-lg-0">
                                    <img src="https://cdn-icons-png.flaticon.com/512/1322/1322530.png" alt="Dashboard" class="img-fluid" style="max-height: 200px;">
                                </div>
                                <div class="col-lg-6">
                                    <h3 class="mb-3">Database Management System</h3>
                                    <p class="lead">Welcome to your admin dashboard. Here you can manage all aspects of your system including tasks, clients, payments, and more.</p>
                                    <div class="alert alert-primary mt-4">
                                        <i class="fas fa-lightbulb me-2"></i> Pro Tip: Use the quick access buttons for faster navigation to frequently used sections.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Script -->
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
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