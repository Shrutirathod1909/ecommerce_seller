<?php
error_reporting(-1);

$servername = "localhost";
$username = "root";
$password = "";
$database = "ecommerce_seller";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ❌ DO NOT echo anything here


?>