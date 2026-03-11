<?php

header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';

/* ================= SHOW PRODUCTS ================= */

if($action == "show"){

$status = $data['status'] ?? 'approved';

if($status == "approved"){
$where = "verified=1 AND rejected=0 AND hide='N'";
}

else if($status == "pending"){
$where = "verified=0 AND rejected=0 AND hide='N'";
}

else if($status == "rejected"){
$where = "rejected=1 AND hide='N'";
}

else if($status == "restore"){
$where = "hide='Y'";
}

else{
$where = "1";
}

$sql = "SELECT productid,sku,item_name,subtitle,category,image1
FROM products
WHERE $where
ORDER BY productid DESC";

$result = $conn->query($sql);

$products = [];

while($row = $result->fetch_assoc()){
$products[] = $row;
}

echo json_encode([
"status"=>"success",
"data"=>$products
]);

}


/* ================= ADD PRODUCT ================= */

else if($action == "add"){

$item_name = $data['item_name'] ?? '';
$subtitle = $data['subtitle'] ?? '';
$category = $data['category'] ?? '';
$subcategory = $data['subcategory'] ?? '';
$child_category = $data['child_category'] ?? '';
$gender = $data['gender'] ?? '';
$payment_method = $data['payment_method'] ?? '';
$country_of_origin = $data['country_of_origin'] ?? '';
$weight = $data['weight'] ?? '';
$hsn = $data['hsn'] ?? '';
$gst_type = $data['gst_type'] ?? '';
$product_description = $data['product_description'] ?? '';

$verified = 0;
$rejected = 0;
$hide = "N";

$sql = "INSERT INTO products
(item_name,subtitle,category,subcategory,child_category,gender,
payment_method,country_of_origin,weight,hsn,gst_type,product_description,
verified,rejected,hide)

VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
"ssssssssssssiss",
$item_name,
$subtitle,
$category,
$subcategory,
$child_category,
$gender,
$payment_method,
$country_of_origin,
$weight,
$hsn,
$gst_type,
$product_description,
$verified,
$rejected,
$hide
);

if($stmt->execute()){

$productid = $conn->insert_id;

echo json_encode([
"status"=>"success",
"productid"=>$productid,
"message"=>"Product Created (Pending Approval)"
]);

}else{

echo json_encode([
"status"=>"error",
"message"=>$conn->error
]);

}

}


/* ================= UPDATE PRODUCT ================= */

else if($action == "update"){

$productid = $data['productid'] ?? '';

$item_name = $data['item_name'] ?? '';
$subtitle = $data['subtitle'] ?? '';
$category = $data['category'] ?? '';

$sql = "UPDATE products
SET item_name=?, subtitle=?, category=?
WHERE productid=?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
"sssi",
$item_name,
$subtitle,
$category,
$productid
);

if($stmt->execute()){

echo json_encode([
"status"=>"success",
"message"=>"Product Updated"
]);

}else{

echo json_encode([
"status"=>"error",
"message"=>$conn->error
]);

}

}


/* ================= DELETE PRODUCT (SOFT DELETE) ================= */

else if($action == "delete"){

$productid = $data['productid'] ?? '';

$sql = "UPDATE products SET hide='Y' WHERE productid=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$productid);

if($stmt->execute()){

echo json_encode([
"status"=>"success",
"message"=>"Product Deleted"
]);

}else{

echo json_encode([
"status"=>"error",
"message"=>$conn->error
]);

}

}


/* ================= RESTORE PRODUCT ================= */

else if($action == "restore"){

$productid = $data['productid'] ?? '';

$sql = "UPDATE products SET hide='N' WHERE productid=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$productid);

if($stmt->execute()){

echo json_encode([
"status"=>"success",
"message"=>"Product Restored"
]);

}else{

echo json_encode([
"status"=>"error",
"message"=>$conn->error
]);

}

}


/* ================= INVALID ACTION ================= */

else{

echo json_encode([
"status"=>"error",
"message"=>"Invalid Action"
]);

}

$conn->close();

?>