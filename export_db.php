<?php
// Start the session
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../index.php");
    exit();
}

// Database connection details
$servername = "localhost"; // Replace with your server name if different
$username = "root";    // XAMPP default username
$password = "";        // XAMPP default (blank password)
$dbname = "u522875338_PACE_DB";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Array of tables to export
$tables = array(
    "admin",
    "adminclientpayment",
    "admintlpayment",
    "client",
    "colleges",
    "expert",
    "task",
    "teamlead",
    "tlexpertpayment"
);

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=PACE_DB_Export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add a UTF-8 BOM
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Loop through tables
foreach ($tables as $table) {
    // Add table name as a header
    fputcsv($output, array(''));
    fputcsv($output, array('TABLE: ' . $table));
    
    // Get column names
    $columns_result = $conn->query("SHOW COLUMNS FROM `$table`");
    $columns = array();
    while ($column = $columns_result->fetch_assoc()) {
        $columns[] = $column['Field'];
    }
    
    // Write column headers
    fputcsv($output, $columns);
    
    // Get data
    $result = $conn->query("SELECT * FROM `$table`");
    
    // Write data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

// Close connection
$conn->close();

// End script
exit();
?>