<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$productid = $data['productid'] ?? 0;

/* ---------- products table fields ---------- */

// manufacturer = vendor_id
$weight = $data['weight'] ?? '';
$height = $data['height'] ?? '';
$width = $data['width'] ?? '';
$warranty_desc = $data['warranty'] ?? '';
$material = $data['material'] ?? '';

/* ---------- product_detail_description fields ---------- */

$size = $data['size'] ?? '';
$color = $data['color'] ?? ''; // DB column = colour
$sku_code = $data['sku'] ?? '';
$barcode = $data['barcode'] ?? '';
$hsn = $data['hsn'] ?? '';
$sale_price = $data['sale_price'] ?? 0;

/* ---------- Update products table ---------- */

$sql1 = "UPDATE products SET

weight=?,
height=?,
width=?,
material=?,
warranty_desc=?,
modified_on=NOW()
WHERE productid=?";

$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param(
    "sssssi",
    
    $weight,
    $height,
    $width,
    $material,
    $warranty_desc,
    $productid
);

$result1 = $stmt1->execute();

/* ---------- Update product_detail_description ---------- */

$sql2 = "UPDATE product_detail_description SET
size=?,
colour=?,
sku_code=?,
barcode=?,
hsn=?,
sale_price=?,
modified_on=NOW()
WHERE product_id=?";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param(
    "sssssdi",
    $size,
    $color,
    $sku_code,
    $barcode,
    $hsn,
    $sale_price,
    $productid
);

$result2 = $stmt2->execute();

/* ---------- Response ---------- */

if($result1 && $result2){
    echo json_encode([
        "status" => "success",
        "message" => "Product Info Updated Successfully"
    ]);
}else{
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
}

$stmt1->close();
$stmt2->close();
$conn->close();
?>