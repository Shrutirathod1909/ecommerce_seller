<?php
header("Content-Type: application/json");
require_once "db.php";

if(!$conn){
    echo json_encode([
        "status"=>"error",
        "message"=>"Database connection failed"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$order_id = $data['order_id'] ?? '';

if(empty($order_id)){
    echo json_encode([
        "status"=>"error",
        "message"=>"Order ID required"
    ]);
    exit;
}

$stmt = $conn->prepare("
SELECT 
product_name,
qty,
sale_price,
(qty * sale_price) AS total_price,
size,
brand
FROM ecommerce_orders
WHERE order_id=?
");

$stmt->bind_param("s",$order_id);
$stmt->execute();

$result = $stmt->get_result();

$products = [];
$order_total = 0;

while($row = $result->fetch_assoc()){

    $order_total += $row['total_price'];

    $products[] = $row;
}

echo json_encode([
    "status"=>"success",
    "products"=>$products,
    "order_amount"=>$order_total
]);

$stmt->close();
$conn->close();
?>