<?php

error_reporting(0); // 👈 IMPORTANT (hide warnings)
ini_set('display_errors', 0);

header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$vendor_id = $data['vendor_id'] ?? '';
$old_password = $data['old_password'] ?? '';
$new_password = $data['new_password'] ?? '';

if(empty($vendor_id) || empty($old_password) || empty($new_password)){
    echo json_encode([
        "status"=>"error",
        "message"=>"All fields required"
    ]);
    exit;
}

$query = "SELECT password FROM vendors WHERE id='$vendor_id'";
$result = mysqli_query($conn,$query);

if(!$result){
    echo json_encode([
        "status"=>"error",
        "message"=>"DB Error"
    ]);
    exit;
}

$row = mysqli_fetch_assoc($result);

if(!$row){
    echo json_encode([
        "status"=>"error",
        "message"=>"Vendor not found"
    ]);
    exit;
}

/* CHECK OLD PASSWORD */
if(md5($old_password) != $row['password']){
    echo json_encode([
        "status"=>"error",
        "message"=>"Old password incorrect"
    ]);
    exit;
}

/* UPDATE PASSWORD */
$newHash = md5($new_password);

$update = mysqli_query($conn,"UPDATE vendors SET password='$newHash' WHERE id='$vendor_id'");

if($update){
    echo json_encode([
        "status"=>"success",
        "message"=>"Password updated successfully"
    ]);
}else{
    echo json_encode([
        "status"=>"error",
        "message"=>"Update failed"
    ]);
}