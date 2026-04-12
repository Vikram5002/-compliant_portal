<?php
session_start();

date_default_timezone_set('Asia/Kolkata');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'cprtl';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// THIS LINE TO SYNC MYSQL TIMEZONE 
$conn->query("SET time_zone = '+05:30'");

// Function to generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function sanitize_input($data) {
    return trim(filter_var($data, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
}
?>