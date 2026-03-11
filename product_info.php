<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$productid = $data['productid'] ?? '';

$brand = $data['brand'] ?? '';
$sku = $data['sku'] ?? '';
$barcode = $data['barcode'] ?? '';
$material = $data['material'] ?? '';
$color = $data['color'] ?? '';
$size = $data['size'] ?? '';

$length = $data['length'] ?? '';
$width = $data['width'] ?? '';
$height = $data['height'] ?? '';
$weight = $data['weight'] ?? '';

$manufacturer = $data['manufacturer'] ?? '';
$warranty_desc = $data['warranty'] ?? '';

$sql = "UPDATE products SET
brand=?,
sku=?,
barcode=?,
material=?,
color=?,
size=?,
length=?,
width=?,
height=?,
weight=?,
manufacturer=?,
warranty_desc=?
WHERE productid=?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
"ssssssssssssi",
$brand,
$sku,
$barcode,
$material,
$color,
$size,
$length,
$width,
$height,
$weight,
$manufacturer,
$warranty_desc,
$productid
);

if($stmt->execute()){

    echo json_encode([
        "status"=>"success",
        "message"=>"Product Info Updated"
    ]);

}else{

    echo json_encode([
        "status"=>"error",
        "message"=>$conn->error
    ]);

}

$conn->close();
?>