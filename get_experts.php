<?php
// Start the session
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // Return an empty array if not logged in
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";    // XAMPP default username
$password = "";        // XAMPP default (blank password)
$database = "u522875338_PACE_DB";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    // Return an empty array on error
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Get team lead ID from the request
$teamLeadId = isset($_GET['team_lead_id']) ? intval($_GET['team_lead_id']) : 0;

// Fetch experts for the specified team lead
$sql = "SELECT expert_id, expert_name FROM expert WHERE team_lead_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teamLeadId);
$stmt->execute();
$result = $stmt->get_result();

// Create an array of experts
$experts = [];
while ($row = $result->fetch_assoc()) {
    $experts[] = $row;
}

// Return the experts as JSON
header('Content-Type: application/json');
echo json_encode($experts);

// Close the connection
$stmt->close();
$conn->close();