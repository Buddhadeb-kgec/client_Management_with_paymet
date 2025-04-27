<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";    // XAMPP default username
$password = "";        // XAMPP default (blank password)
$dbname = "u522875338_PACE_DB";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch dropdown data
$clients_result = $conn->query("SELECT client_id, client_name FROM client");
$team_leads_result = $conn->query("SELECT team_lead_id, name FROM teamlead");
$experts_result = $conn->query("SELECT expert_id, expert_name FROM expert");

// Handle task addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add'])) {
    $task_name = $_POST['task_name'];
    $description = !empty($_POST['description']) ? $_POST['description'] : NULL;
    $team_lead_id = $_POST['team_lead_id'];
    $expert_id_1 = !empty($_POST['expert_id_1']) ? $_POST['expert_id_1'] : NULL;
    $expert_id_2 = !empty($_POST['expert_id_2']) ? $_POST['expert_id_2'] : NULL;
    $expert_id_3 = !empty($_POST['expert_id_3']) ? $_POST['expert_id_3'] : NULL;
    $client_id = $_POST['client_id'];
    $price = $_POST['price'];
    $tl_price = $_POST['tl_price'];
    $expert_price1 = !empty($_POST['expert_price1']) ? $_POST['expert_price1'] : 0;
    $expert_price2 = !empty($_POST['expert_price2']) ? $_POST['expert_price2'] : 0;
    $expert_price3 = !empty($_POST['expert_price3']) ? $_POST['expert_price3'] : 0;
    $task_date = $_POST['task_date'];
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];
    $word_count = $_POST['word_count'];
    $issue = isset($_POST['issue']) ? implode(",", $_POST['issue']) : NULL;

    // Fetch team lead name
    $team_lead_name = $conn->query("SELECT name FROM teamlead WHERE team_lead_id = $team_lead_id")->fetch_assoc()['name'];

    // Fetch expert names
    $expert_name_1 = $expert_id_1 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_1")->fetch_assoc()['expert_name'] : NULL;
    $expert_name_2 = $expert_id_2 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_2")->fetch_assoc()['expert_name'] : NULL;
    $expert_name_3 = $expert_id_3 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_3")->fetch_assoc()['expert_name'] : NULL;

    // Insert task into the database
    $stmt = $conn->prepare("INSERT INTO task (task_name, description, team_lead_id, assigned_team_lead_name, expert_id_1, assigned_expert_1, expert_id_2, assigned_expert_2, expert_id_3, assigned_expert_3, client_id, price, tl_price, expert_price1, expert_price2, expert_price3, task_date, due_date, status, word_count, issue) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssssssiiiiiisssis", $task_name, $description, $team_lead_id, $team_lead_name, $expert_id_1, $expert_name_1, $expert_id_2, $expert_name_2, $expert_id_3, $expert_name_3, $client_id, $price, $tl_price, $expert_price1, $expert_price2, $expert_price3, $task_date, $due_date, $status, $word_count, $issue);
    $stmt->execute();

    // Update dues for the client
    $conn->query("UPDATE client SET due_payment = due_payment + $price WHERE client_id = $client_id");

    // Update dues for the team lead (excluding expert prices)
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

    header("Location: visualization.php");
    exit();
}

// Handle bill generation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_bill'])) {
    $role = $_POST['role'];
    $month = $_POST['month'];

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        die("Invalid month format. Use YYYY-MM.");
    }

    if ($role === 'monthly_return') {
        // Fetch payments made by clients to admin (positive values)
        $client_payments_sql = "
            SELECT 
                'Client Payment' AS activity_type,
                c.client_name AS description,
                acp.payment_date AS date,
                acp.payment_in_inr AS amount,
                '' AS task_status
            FROM adminclientpayment acp
            JOIN client c ON acp.client_id = c.client_id
            WHERE DATE_FORMAT(acp.payment_date, '%Y-%m') = '$month'
        ";

        // Fetch payments made by admin to team leads (negative values)
        $team_lead_payments_sql = "
            SELECT 
                'Team Lead Payment' AS activity_type,
                tl.name AS description,
                atp.payment_date AS date,
                -atp.amount_paid AS amount,
                '' AS task_status
            FROM admintlpayment atp
            JOIN teamlead tl ON atp.tl_id = tl.team_lead_id
            WHERE DATE_FORMAT(atp.payment_date, '%Y-%m') = '$month'
        ";

        // Fetch payments made by team leads to experts (negative values)
        $expert_payments_sql = "
            SELECT 
                'Expert Payment' AS activity_type,
                e.expert_name AS description,
                tep.payment_date AS date,
                -tep.amount_paid AS amount,
                '' AS task_status
            FROM tlexpertpayment tep
            JOIN expert e ON tep.expert_id = e.expert_id
            WHERE DATE_FORMAT(tep.payment_date, '%Y-%m') = '$month'
        ";

        // Combine all queries
        $sql = "
            $client_payments_sql
            UNION ALL
            $team_lead_payments_sql
            UNION ALL
            $expert_payments_sql
            ORDER BY date
        ";

        $activities_result = $conn->query($sql);
        if (!$activities_result) {
            die("Query failed: " . $conn->error);
        }

        // Calculate total profit or loss
        $total_profit_loss = 0;
        while ($row = $activities_result->fetch_assoc()) {
            $total_profit_loss += $row['amount'];
        }
        $total_profit_loss = number_format($total_profit_loss, 2, '.', '');
    } else {
        $entity_id = isset($_POST['entity_id']) ? $_POST['entity_id'] : null;
        if (!$entity_id && $role !== 'expert') {
            die("Entity ID is required for this role.");
        }

        // Fetch entity details based on role
        $entity_name = '';
        switch ($role) {
            case 'client':
                $sql = "SELECT client_id, client_name FROM client WHERE client_id = $entity_id";
                $entity_result = $conn->query($sql);
                if (!$entity_result) {
                    die("Query failed: " . $conn->error);
                }
                $entity_row = $entity_result->fetch_assoc();
                $entity_name = $entity_row['client_name'];
                $entity_id = $entity_row['client_id'];
                break;

            case 'teamlead':
                $sql = "SELECT team_lead_id, name FROM teamlead WHERE team_lead_id = $entity_id";
                $entity_result = $conn->query($sql);
                if (!$entity_result) {
                    die("Query failed: " . $conn->error);
                }
                $entity_row = $entity_result->fetch_assoc();
                $entity_name = $entity_row['name'];
                $entity_id = $entity_row['team_lead_id'];
                break;

            case 'expert':
                $sql = "SELECT expert_id, expert_name FROM expert WHERE expert_id = $entity_id";
                $entity_result = $conn->query($sql);
                if (!$entity_result) {
                    die("Query failed: " . $conn->error);
                }
                $entity_row = $entity_result->fetch_assoc();
                $entity_name = $entity_row['expert_name'];
                $entity_id = $entity_row['expert_id'];
                break;

            default:
                die("Invalid role selected.");
        }

        // Calculate previous month dues
        $previous_month = date('Y-m', strtotime($month . '-01 -1 month'));
        $previous_month_name = date('F Y', strtotime($previous_month . '-01')); // Format: "February 2025"

        // Get initial dues
        $initial_dues = 0;
        switch ($role) {
            case 'client':
                $sql = "SELECT initial_dues FROM client WHERE client_id = $entity_id";
                break;
            case 'teamlead':
                $sql = "SELECT initial_due FROM teamlead WHERE team_lead_id = $entity_id";
                break;
            case 'expert':
                $sql = "SELECT initial_dues FROM expert WHERE expert_id = $entity_id";
                break;
        }
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $initial_dues = $row[$role === 'teamlead' ? 'initial_due' : 'initial_dues'];

        // Get total price from task table till the previous month
        $total_price = 0;
        switch ($role) {
            case 'client':
                $sql = "SELECT COALESCE(SUM(price), 0) AS total_price 
                        FROM task 
                        WHERE client_id = $entity_id AND DATE_FORMAT(task_date, '%Y-%m') <= '$previous_month'";
                break;
            case 'teamlead':
                // Updated: Exclude expert prices from team lead dues
                $sql = "SELECT COALESCE(SUM(tl_price), 0) AS total_price 
                        FROM task 
                        WHERE team_lead_id = $entity_id AND DATE_FORMAT(task_date, '%Y-%m') <= '$previous_month'";
                break;
            case 'expert':
    $sql = "SELECT COALESCE(SUM(
                CASE 
                    WHEN expert_id_1 = $entity_id THEN expert_price1
                    WHEN expert_id_2 = $entity_id THEN expert_price2
                    WHEN expert_id_3 = $entity_id THEN expert_price3
                    ELSE 0 
                END
            ), 0) AS total_price 
            FROM task 
            WHERE (expert_id_1 = $entity_id OR expert_id_2 = $entity_id OR expert_id_3 = $entity_id) 
            AND DATE_FORMAT(task_date, '%Y-%m') <= '$previous_month'";
    break;
        }
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $total_price = $row['total_price'];

        // Get total amount_paid till the previous month
        $total_paid = 0;
        switch ($role) {
            case 'client':
                $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid 
                        FROM adminclientpayment 
                        WHERE client_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') <= '$previous_month'";
                break;
            case 'teamlead':
                $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid 
                        FROM admintlpayment 
                        WHERE tl_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') <= '$previous_month'";
                break;
            case 'expert':
                $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid 
                        FROM tlexpertpayment 
                        WHERE expert_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') <= '$previous_month'";
                break;
        }
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $total_paid = $row['total_paid'];

        // Calculate dues till the previous month
        $dues_till_previous_month = ($initial_dues + $total_price) - $total_paid;

        // Fetch activities for the selected month
        $activities_result = null;
        switch ($role) {
            case 'client':
                $sql = "
                    SELECT 
                        'Task' AS activity_type,
                        task_name AS description,
                        task_date AS date,
                        price AS amount,
                        t.name AS assigned_to,  -- Fetch team lead name from teamlead table
                        NULL AS inr_amount,
                        ta.status AS task_status,
                        CONCAT_WS(', ', 
                            IFNULL(e1.expert_name, ''), 
                            IFNULL(e2.expert_name, ''), 
                            IFNULL(e3.expert_name, '')
                        ) AS expert_names
                    FROM task ta
                    LEFT JOIN teamlead t ON ta.team_lead_id = t.team_lead_id  -- Join with teamlead table
                    LEFT JOIN expert e1 ON ta.expert_id_1 = e1.expert_id
                    LEFT JOIN expert e2 ON ta.expert_id_2 = e2.expert_id
                    LEFT JOIN expert e3 ON ta.expert_id_3 = e3.expert_id
                    WHERE ta.client_id = $entity_id AND DATE_FORMAT(ta.task_date, '%Y-%m') = '$month'
                    UNION ALL
                    SELECT 
                        'Payment' AS activity_type,
                        'Payment Received' AS description,
                        payment_date AS date,
                        -amount_paid AS amount,
                        NULL AS assigned_to,
                        payment_in_inr AS inr_amount,
                        '' AS task_status,
                        '' AS expert_names
                    FROM adminclientpayment
                    WHERE client_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') = '$month'
                    ORDER BY date
                ";
                break;

            case 'teamlead':
                $sql = "
                    SELECT 
                        'Task' AS activity_type,
                        task_name AS description,
                        task_date AS date,
                        tl_price AS amount,  -- Updated: Only include tl_price
                        NULL AS assigned_to,
                        ta.status AS task_status,
                        CONCAT_WS(', ', 
                            IFNULL(e1.expert_name, ''), 
                            IFNULL(e2.expert_name, ''), 
                            IFNULL(e3.expert_name, '')
                        ) AS expert_names,
                        c.client_name AS client_name
                    FROM task ta
                    LEFT JOIN expert e1 ON ta.expert_id_1 = e1.expert_id
                    LEFT JOIN expert e2 ON ta.expert_id_2 = e2.expert_id
                    LEFT JOIN expert e3 ON ta.expert_id_3 = e3.expert_id
                    LEFT JOIN client c ON ta.client_id = c.client_id
                    WHERE ta.team_lead_id = $entity_id AND DATE_FORMAT(ta.task_date, '%Y-%m') = '$month'
                    UNION ALL
                    SELECT 
                        'Payment' AS activity_type,
                        'Payment Received' AS description,
                        payment_date AS date,
                        -amount_paid AS amount,
                        NULL AS assigned_to,
                        '' AS task_status,
                        '' AS expert_names,
                        '' AS client_name
                    FROM admintlpayment
                    WHERE tl_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') = '$month'
                    ORDER BY date
                ";
                break;

            case 'expert':
                $team_lead_id = isset($_POST['team_lead_id']) ? $_POST['team_lead_id'] : null;
                
                // Create separate conditions for task and payment parts
                $task_team_lead_condition = $team_lead_id ? "AND ta.team_lead_id = $team_lead_id" : "";
                $payment_team_lead_condition = $team_lead_id ? "AND tep.team_lead_id = $team_lead_id" : "";

                $sql = "
                    SELECT 
                        'Task' AS activity_type,
                        task_name AS description,
                        task_date AS date,
                        (CASE 
                            WHEN expert_id_1 = $entity_id THEN expert_price1
                            WHEN expert_id_2 = $entity_id THEN expert_price2
                            WHEN expert_id_3 = $entity_id THEN expert_price3
                            ELSE 0
                        END) AS amount,
                        t.name AS team_lead_name,
                        c.client_name AS client_name,
                        ta.status AS task_status
                    FROM task ta
                    LEFT JOIN teamlead t ON ta.team_lead_id = t.team_lead_id
                    LEFT JOIN client c ON ta.client_id = c.client_id
                    WHERE (expert_id_1 = $entity_id OR expert_id_2 = $entity_id OR expert_id_3 = $entity_id)
                    AND DATE_FORMAT(ta.task_date, '%Y-%m') = '$month'
                    $task_team_lead_condition
                    UNION ALL
                    SELECT 
                        'Payment' AS activity_type,
                        'Payment Received' AS description,
                        payment_date AS date,
                        -amount_paid AS amount,
                        t.name AS team_lead_name,
                        NULL AS client_name,
                        '' AS task_status
                    FROM tlexpertpayment tep
                    LEFT JOIN teamlead t ON tep.team_lead_id = t.team_lead_id
                    WHERE expert_id = $entity_id 
                    AND DATE_FORMAT(payment_date, '%Y-%m') = '$month'
                    $payment_team_lead_condition
                    ORDER BY date
                ";
                break;

            default:
                die("Invalid role selected.");
        }

        $activities_result = $conn->query($sql);
        if (!$activities_result) {
            die("Query failed: " . $conn->error);
        }

        // Calculate total dues at the end of the month
        $total_dues = $dues_till_previous_month;
        while ($row = $activities_result->fetch_assoc()) {
            $total_dues += $row['amount'];
        }
    }
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
    <title>Bill Visualization</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- html2canvas library for JPEG download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- SheetJS library for Excel download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
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
        
        /* Dropdown Styles - Updated with fixes */
        .dropdown-container { 
            position: relative;
            z-index: 100; /* Ensure dropdowns appear above other elements */
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
            z-index: 1100; /* Higher than other elements */
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            background-color: #fff;
            display: none;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-top: 5px;
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
            margin-right: 0.5rem;
        }
        
        .form-check-label {
            margin-right: 1rem;
        }
        
        /* Failed row styling */
        .failed-row {
            background-color: #ff7575;
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
                <h1 class="page-title"><i class="fas fa-file-excel me-2"></i>Generate Sheets</h1>
            </div>
            <div class="user-menu">
                <span class="d-none d-md-inline">Welcome, Admin</span>
                <img src="bg.jpg" alt="User" class="user-avatar">
            </div>
        </div>
        
        <!-- Generate Bill Card -->
        <div class="dashboard-card fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Generate Bill</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <div class="row g-3">
                        <!-- Role Dropdown -->
                        <div class="col-md-3">
                            <label for="role" class="form-label">Role:</label>
                            <select name="role" id="role" class="form-control" required onchange="updateEntityDropdown()">
                                <option value="client">Client</option>
                                <option value="teamlead">TeamLead</option>
                                <option value="expert">Expert</option>
                                <option value="monthly_return">Monthly Return</option>
                            </select>
                        </div>

                        <!-- Entity Dropdown with Search -->
                        <div class="col-md-3">
                            <label class="form-label">Select:</label>
                            <div class="dropdown-container">
                                <input type="text" id="entity-search" class="form-control" placeholder="Search...">
                                <ul class="dropdown-list" id="entity-list">
                                    <?php
                                    $clients_result->data_seek(0);
                                    while ($row = $clients_result->fetch_assoc()) {
                                        echo "<li data-value='{$row['client_id']}'>{$row['client_name']}</li>";
                                    }
                                    ?>
                                </ul>
                                <input type="hidden" name="entity_id" id="entity_id">
                            </div>
                        </div>

                        <!-- Team Lead Dropdown (for Expert role) -->
                        <div class="col-md-3" id="team_lead_dropdown">
                            <label class="form-label">Team Lead:</label>
                            <select name="team_lead_id" id="team_lead_id" class="form-control">
                                <option value="">All Team Leads</option>
                                <?php
                                $team_leads_result->data_seek(0);
                                while ($row = $team_leads_result->fetch_assoc()) {
                                    echo "<option value='{$row['team_lead_id']}'>{$row['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Month Input -->
                        <div class="col-md-3">
                            <label for="month" class="form-label">Month:</label>
                            <input type="month" name="month" id="month" class="form-control" required value="<?php echo date('Y-m'); ?>">
                        </div>

                        <!-- Generate Bill Button -->
                        <div class="col-md-3">
                            <button type="submit" name="generate_bill" class="btn btn-primary w-100 mt-4">
                                <i class="fas fa-file-export me-2"></i>Generate Bill
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($activities_result)) { ?>
            <!-- Bill Content Card -->
            <div class="dashboard-card fade-in" style="animation-delay: 0.1s;">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Bill Details</h5>
                </div>
                <div class="card-body">
                    <div id="bill-content">
                        <?php if ($role === 'monthly_return') { ?>
                            <h2>Monthly Return for <?php echo date('F Y', strtotime($month . '-01')); ?></h2>
                        <?php } else { ?>
                            <div class="entity-details">
                                <p><?php echo ucfirst($role); ?> Name: <?php echo $entity_name; ?></p>
                                <p><?php echo ucfirst($role); ?> ID: <?php echo $entity_id; ?></p>
                            </div>
                            <h2>Bill for <?php echo date('F Y', strtotime($month . '-01')); ?></h2>
                        <?php } ?>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Activity</th>
                                        <th>Description</th>
                                        <th>Amount </th>
                                        <?php if ($role === 'client') { ?>
                                            <th>Assigned To (Team Lead)</th>
                                            <th>Payment in INR</th>
                                            <th>Expert Name(s)</th>
                                        <?php } ?>
                                        <?php if ($role === 'teamlead') { ?>
                                            <th>Expert Name(s)</th>
                                            <th>Client Name</th>
                                        <?php } ?>
                                        <?php if ($role === 'expert') { ?>
                                            <th>Team Lead Name</th>
                                            <th>Client Name</th>
                                        <?php } ?>
                                        <th>Task Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($role !== 'monthly_return') { ?>
                                        <!-- Dues till previous month -->
                                        <tr>
                                            <td><?php echo $previous_month_name; ?></td>
                                            <td>Dues Till Previous Month</td>
                                            <td>Initial Dues + Task Price - Payments (Till <?php echo $previous_month_name; ?>)</td>
                                            <td><?php echo $dues_till_previous_month; ?></td>
                                            <?php if ($role === 'client') { ?>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            <?php } ?>
                                            <?php if ($role === 'teamlead') { ?>
                                                <td></td>
                                                <td></td>
                                            <?php } ?>
                                            <?php if ($role === 'expert') { ?>
                                                <td></td>
                                                <td></td>
                                            <?php } ?>
                                            <td></td>
                                        </tr>
                                    <?php } ?>

                                    <?php
                                    $activities_result->data_seek(0); // Reset pointer to the beginning
                                    while ($row = $activities_result->fetch_assoc()) { ?>
                                        <tr class="<?php echo ($row['task_status'] === 'failed') ? 'failed-row' : ''; ?>">
                                            <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                            <td><?php echo $row['activity_type']; ?></td>
                                            <td><?php echo $row['description']; ?></td>
                                            <td><?php echo number_format($row['amount'], 2, '.', ''); ?></td>
                                            <?php if ($role === 'client') { ?>
                                                <td><?php echo $row['assigned_to']; ?></td>
                                                <td><?php echo $row['inr_amount']; ?></td>
                                                <td><?php echo $row['expert_names']; ?></td>
                                            <?php } ?>
                                            <?php if ($role === 'teamlead') { ?>
                                                <td><?php echo $row['expert_names']; ?></td>
                                                <td><?php echo $row['client_name']; ?></td>
                                            <?php } ?>
                                            <?php if ($role === 'expert') { ?>
                                                <td><?php echo $row['team_lead_name']; ?></td>
                                                <td><?php echo $row['client_name']; ?></td>
                                            <?php } ?>
                                            <td><?php echo $row['task_status']; ?></td>
                                        </tr>
                                    <?php } ?>

                                    <?php if ($role !== 'monthly_return') { ?>
                                        <!-- Final Dues -->
                                        <tr>
                                            <td></td>
                                            <td>Final Dues</td>
                                            <td>Dues till previous month + Current month transactions (<?php echo date('F Y', strtotime($month . '-01')); ?>)</td>
                                            <td><?php echo $total_dues; ?></td>
                                            <?php if ($role === 'client') { ?>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            <?php } ?>
                                            <?php if ($role === 'teamlead') { ?>
                                                <td></td>
                                                <td></td>
                                            <?php } ?>
                                            <?php if ($role === 'expert') { ?>
                                                <td></td>
                                                <td></td>
                                            <?php } ?>
                                            <td></td>
                                        </tr>
                                    <?php } ?>

                                    <?php if ($role === 'monthly_return') { ?>
                                        <!-- Total Profit or Loss -->
                                        <tr>
                                            <td></td>
                                            <td>Total Profit/Loss</td>
                                            <td>Final calculation for the month</td>
                                            <td><?php echo number_format($total_profit_loss, 2, '.', ''); ?></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Download Buttons -->
                    <div class="mt-4">
                        <button onclick="downloadAsJPEG()" class="btn btn-warning">
                            <i class="fas fa-file-image me-2"></i>Download as JPEG
                        </button>
                        <button onclick="downloadAsExcel()" class="btn btn-success">
                            <i class="fas fa-file-excel me-2"></i>Download as Excel
                        </button>
                    </div>
                </div>
            </div>
        <?php } ?>

        <!-- Add Task Card -->
        <div class="dashboard-card fade-in" style="animation-delay: 0.2s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Task</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <!-- Incomplete Information Checkbox -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="incomplete_information" class="form-check-input" id="incomplete_information">
                                <label class="form-check-label" for="incomplete_information">Incomplete Information</label>
                            </div>
                        </div>
                    </div>

                    <!-- Client Dropdown with Search -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Client:</label>
                            <div class="dropdown-container">
                                <input type="text" id="task-client-search" class="form-control" placeholder="Search client...">
                                <ul class="dropdown-list" id="task-client-list">
                                    <?php
                                    $clients_result->data_seek(0);
                                    while ($row = $clients_result->fetch_assoc()) {
                                        echo "<li data-value='{$row['client_id']}'>{$row['client_name']}</li>";
                                    }
                                    ?>
                                </ul>
                                <input type="hidden" name="client_id" id="task_client_id">
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

                    <!-- Second row -->
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

                    <!-- Third row -->
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

                    <!-- Fourth row -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status:</label>
                            <select name="status" class="form-control" required>
                                <option value="in progress">In Progress</option>
                                <option value="submitted">Submitted</option>
                                <option value="passed">Passed</option>
                                <option value="failed">Failed</option>
                                <option value="submitted late">Submitted Late</option>
                            </select>
                        </div>
                        <div class="col-md-6">     
                            <label class="form-label">Issue:</label>     
                            <div class="form-check">         
                                <input type="checkbox" name="issue[]" value="Low marks" class="form-check-input"> Low marks<br>         
                                <input type="checkbox" name="issue[]" value="Brief not followed" class="form-check-input"> Brief not followed<br>         
                                <input type="checkbox" name="issue[]" value="Word count lower" class="form-check-input"> Word count lower<br>         
                                <input type="checkbox" name="issue[]" value="Wordcount higher" class="form-check-input"> Wordcount higher<br>         
                                <input type="checkbox" name="issue[]" value="Referencing irrelevant" class="form-check-input"> Referencing irrelevant<br>         
                                <input type="checkbox" name="issue[]" value="AI used" class="form-check-input"> AI used<br>         
                                <input type="checkbox" name="issue[]" value="Plagiarism" class="form-check-input"> Plagiarism<br>         
                                <input type="checkbox" name="issue[]" value="Poor quality" class="form-check-input"> Poor quality<br>         
                                <input type="checkbox" name="issue[]" value="Incomplete information" class="form-check-input"> Incomplete information<br>
                                <input type="checkbox" name="issue[]" value="Money Less Taken" class="form-check-input"> Money Less Taken<br>
                            </div> 
                        </div>
                    </div>

                    <!-- Team Assignment Section with Searchable Dropdowns -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <table class="table">
                                <tbody>
                                    <!-- Team Lead -->
                                    <tr>
                                        <td>Team Lead</td>
                                        <td>
                                            <div class="dropdown-container">
                                                <input type="text" id="task-team-lead-search" class="form-control" placeholder="Search team lead...">
                                                <ul class="dropdown-list" id="task-team-lead-list">
                                                    <?php
                                                    $team_leads_result->data_seek(0);
                                                    while ($row = $team_leads_result->fetch_assoc()) {
                                                        echo "<li data-value='{$row['team_lead_id']}'>{$row['name']}</li>";
                                                    }
                                                    ?>
                                                </ul>
                                                <input type="hidden" name="team_lead_id" id="task_team_lead_id">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="tl_price" class="form-control" required>
                                        </td>
                                    </tr>

                                    <!-- Expert 1 -->
                                    <tr>
                                        <td>Expert 1</td>
                                        <td>
                                            <div class="dropdown-container">
                                                <input type="text" id="task-expert1-search" class="form-control" placeholder="Search expert...">
                                                <ul class="dropdown-list" id="task-expert1-list">
                                                    <?php
                                                    $experts_result->data_seek(0);
                                                    while ($row = $experts_result->fetch_assoc()) {
                                                        echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>";
                                                    }
                                                    ?>
                                                </ul>
                                                <input type="hidden" name="expert_id_1" id="task_expert1_id">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="expert_price1" class="form-control">
                                        </td>
                                    </tr>

                                    <!-- Expert 2 -->
                                    <tr>
                                        <td>Expert 2</td>
                                        <td>
                                            <div class="dropdown-container">
                                                <input type="text" id="task-expert2-search" class="form-control" placeholder="Search expert...">
                                                <ul class="dropdown-list" id="task-expert2-list">
                                                    <?php
                                                    $experts_result->data_seek(0);
                                                    while ($row = $experts_result->fetch_assoc()) {
                                                        echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>";
                                                    }
                                                    ?>
                                                </ul>
                                                <input type="hidden" name="expert_id_2" id="task_expert2_id">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="expert_price2" class="form-control">
                                        </td>
                                    </tr>

                                    <!-- Expert 3 -->
                                    <tr>
                                        <td>Expert 3</td>
                                        <td>
                                            <div class="dropdown-container">
                                                <input type="text" id="task-expert3-search" class="form-control" placeholder="Search expert...">
                                                <ul class="dropdown-list" id="task-expert3-list">
                                                    <?php
                                                    $experts_result->data_seek(0);
                                                    while ($row = $experts_result->fetch_assoc()) {
                                                        echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>";
                                                    }
                                                    ?>
                                                </ul>
                                                <input type="hidden" name="expert_id_3" id="task_expert3_id">
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

                    <!-- Submit Button -->
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" name="add" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add Task
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Copy Task Details Button -->
                <div class="col-md-12 mt-3">
                    <button type="button" onclick="copyTaskDetails()" class="btn btn-info w-100">
                        <i class="fas fa-copy me-2"></i>Copy Task Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Script -->
    <script>
        // Initialize all dropdowns
        function initDropdowns() {
            document.querySelectorAll('.dropdown-container').forEach(dropdown => {
                const input = dropdown.querySelector('input[type="text"]');
                const list = dropdown.querySelector('.dropdown-list');
                const hiddenInput = dropdown.querySelector('input[type="hidden"]');

                input.addEventListener('focus', () => {
                    list.style.display = 'block';
                    // Close other open dropdowns
                    document.querySelectorAll('.dropdown-list').forEach(otherList => {
                        if (otherList !== list) {
                            otherList.style.display = 'none';
                        }
                    });
                });
                
                input.addEventListener('input', () => {
                    const searchTerm = input.value.toLowerCase();
                    const items = list.querySelectorAll('li');
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });

                list.addEventListener('click', (e) => {
                    if (e.target.tagName === 'LI') {
                        input.value = e.target.textContent;
                        hiddenInput.value = e.target.getAttribute('data-value');
                        list.style.display = 'none';
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!dropdown.contains(e.target)) {
                        list.style.display = 'none';
                    }
                });
            });
        }

        // Team Lead visibility for Expert role
        function handleRoleChange() {
            const role = document.getElementById('role').value;
            const teamLeadDiv = document.getElementById('team_lead_dropdown');
            
            if (role === 'expert') {
                teamLeadDiv.style.display = 'block';
            } else {
                teamLeadDiv.style.display = 'none';
            }
        }

        // Update entity dropdown
        function updateEntityDropdown() {
            handleRoleChange();
            const role = document.getElementById('role').value;
            const entityList = document.getElementById('entity-list');
            
            // Clear existing options
            entityList.innerHTML = '';

            // Populate based on role
            switch(role) {
                case 'client':
                    <?php
                    $clients_result->data_seek(0);
                    while ($row = $clients_result->fetch_assoc()) {
                        echo "entityList.innerHTML += '<li data-value=\'{$row['client_id']}\'>{$row['client_name']}</li>';";
                    }
                    ?>
                    break;
                case 'teamlead':
                    <?php
                    $team_leads_result->data_seek(0);
                    while ($row = $team_leads_result->fetch_assoc()) {
                        echo "entityList.innerHTML += '<li data-value=\'{$row['team_lead_id']}\'>{$row['name']}</li>';";
                    }
                    ?>
                    break;
                case 'expert':
                    <?php
                    $experts_result->data_seek(0);
                    while ($row = $experts_result->fetch_assoc()) {
                        echo "entityList.innerHTML += '<li data-value=\'{$row['expert_id']}\'>{$row['expert_name']}</li>';";
                    }
                    ?>
                    break;
            }

            // Reinitialize dropdown functionality
            initDropdowns();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            initDropdowns();
            handleRoleChange();
            document.getElementById('role').addEventListener('change', handleRoleChange);
            
            // Set today's date as default for task_date and due_date
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="task_date"]').value = today;
            document.querySelector('input[name="due_date"]').value = today;
        });

        // Keep existing bill generation logic
        function downloadAsJPEG() {
            html2canvas(document.querySelector("#bill-content")).then(canvas => {
                const link = document.createElement('a');
                link.download = 'bill.jpeg';
                link.href = canvas.toDataURL('image/jpeg');
                link.click();
            });
        }

        function downloadAsExcel() {
            const table = document.querySelector("#bill-content table");
            const ws = XLSX.utils.table_to_sheet(table);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Bill");
            XLSX.writeFile(wb, "bill.xlsx");
        }

        function copyTaskDetails() {
            // Get form values from the new dropdown inputs
            const taskName = document.querySelector('input[name="task_name"]').value;
            const description = document.querySelector('textarea[name="description"]').value;
            const clientName = document.getElementById('task-client-search').value;
            const teamLeadName = document.getElementById('task-team-lead-search').value;
            const taskDate = document.querySelector('input[name="task_date"]').value;
            const dueDate = document.querySelector('input[name="due_date"]').value;
            
            // Get expert names from their search inputs
            const expert1Name = document.getElementById('task-expert1-search').value;
            const expert2Name = document.getElementById('task-expert2-search').value;
            const expert3Name = document.getElementById('task-expert3-search').value;
            
            // Format experts list
            let expertsText = [];
            if (expert1Name) expertsText.push(expert1Name);
            if (expert2Name) expertsText.push(expert2Name);
            if (expert3Name) expertsText.push(expert3Name);
            expertsText = expertsText.length > 0 ? expertsText.join(', ') : 'None';
            
            // Format the text
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
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>