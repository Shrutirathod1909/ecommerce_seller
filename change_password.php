<?php

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
$row = mysqli_fetch_assoc($result);

if(!$row){

    echo json_encode([
        "status"=>"error",
        "message"=>"Vendor not found"
    ]);
    exit;
}

/* MD5 password check */
if(md5($old_password) != $row['password']){

    echo json_encode([
        "status"=>"error",
        "message"=>"Old password incorrect"
    ]);
    exit;
}

/* New password MD5 hash */
$newHash = md5($new_password);

$update = "UPDATE vendors SET password='$newHash' WHERE id='$vendor_id'";
mysqli_query($conn,$update);

echo json_encode([
    "status"=>"success",
    "message"=>"Password updated successfully"
]);

?>