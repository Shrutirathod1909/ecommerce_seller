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

define('IMGPATH', 'https://orozone.in/cms/');
 define('UPLOADPATH', 'https://orozone.in/seller_api/');
 define('UPLOAD_URL', 'https://orozone.in/seller_api/uploads/');

//  define('UPLOAD_URL', 'http://192.168.1.39/e-seller/uploads/');
//  define('UPLOADPATH', 'http://192.168.1.39/e-seller/');

?>