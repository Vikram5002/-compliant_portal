<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $user_id = $_SESSION['user_id'];

    $sql = "SELECT user_id FROM tickets WHERE ticket_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) die("Prepare failed: " . $conn->error);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $ticket = $result->fetch_assoc();
        if ($ticket['user_id'] == $user_id) {
            $sql = "DELETE FROM tickets WHERE ticket_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) die("Prepare failed: " . $conn->error);
            $stmt->bind_param("i", $ticket_id);
            if ($stmt->execute()) {
                header("Location: view_tickets.php?message=Ticket deleted successfully");
            } else {
                header("Location: view_tickets.php?message=Error deleting ticket: " . $conn->error);
            }
            $stmt->close();
        } else {
            header("Location: view_tickets.php?message=Unauthorized action");
        }
    } else {
        header("Location: view_tickets.php?message=Ticket not found");
    }
    exit;
}

header("Location: view_tickets.php");
exit;