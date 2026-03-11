<?php

header("Content-Type: application/json");
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

if(!isset($data["product_id"]) || !isset($data["variants"])){

echo json_encode([
"status"=>"error",
"message"=>"Invalid data"
]);
exit;

}

$product_id = $data["product_id"];
$variants = $data["variants"];

$stmt = $conn->prepare("
INSERT INTO variants
(product_id, colour, size, sku_code, sale_price, qty)
VALUES (?, ?, ?, ?, ?, ?)
");

foreach($variants as $v){

$color = $v["color"] ?? '';
$size = $v["size"] ?? '';
$sku = $v["sku"] ?? '';
$price = $v["price"] ?? 0;
$stock = $v["stock"] ?? 0;

$stmt->bind_param("isssdi",
$product_id,
$color,
$size,
$sku,
$price,
$stock
);

$stmt->execute();

}

echo json_encode([
"status"=>"success"
]);

$stmt->close();
$conn->close();

?>