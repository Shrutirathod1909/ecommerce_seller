<?php

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$action = $data['action'];

if($action == "get"){

$vendor_id = $data['vendor_id'];

$q = mysqli_query($conn,"SELECT gstin,business_name,business_address
FROM vendor WHERE id='$vendor_id'");

$row = mysqli_fetch_assoc($q);

echo json_encode([
"status"=>"success",
"data"=>$row
]);

}

if($action == "update"){

$vendor_id = $data['vendor_id'];

mysqli_query($conn,"UPDATE vendor SET

gstin='".$data['gstin']."',
business_name='".$data['business_name']."',
business_address='".$data['business_address']."'

WHERE id='$vendor_id'");

echo json_encode([
"status"=>"success"
]);

}