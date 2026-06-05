<?php
// db.php
$host = 'localhost';
$username = 'root'; // Default XAMPP
$password = ''; // Default XAMPP tiada password
$dbname = 'childcare_db';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>