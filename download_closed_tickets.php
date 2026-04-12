<?php
include 'db_connect.php';

// Security check: ensure only logged-in admins or super_visors can access this
$allowed_roles = ['admin', 'super_visor'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    die("Access denied.");
}

// Set the HTTP headers to trigger a file download
$filename = "closed_tickets_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Write the CSV header row
fputcsv($output, [
    'Ticket ID',
    'Title',
    'Category',
    'Status',
    'Created By (SAP ID)',
    'Feedback Text',
    'Star Rating'
]);

// Fetch the closed tickets data from the database
$sql = "SELECT t.ticket_id, t.title, t.category, t.status, u.sap_id as creator_sap_id,
        (SELECT feedback_text FROM feedback WHERE ticket_id = t.ticket_id AND feedback_text IS NOT NULL ORDER BY created_at DESC LIMIT 1) AS feedback_text,
        (SELECT rating FROM feedback WHERE ticket_id = t.ticket_id AND rating IS NOT NULL ORDER BY created_at DESC LIMIT 1) AS rating
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.user_id
        WHERE t.status = 'Closed'
        ORDER BY t.ticket_id DESC";

$result = $conn->query($sql);

// Loop through the data and write each row to the CSV
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
?>