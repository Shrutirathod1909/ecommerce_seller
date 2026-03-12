<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$order_id = $data['order_id'] ?? '';

if(empty($order_id)){
    echo json_encode([
        "status"=>"error",
        "message"=>"Order ID required"
    ]);
    exit;
}

$query = "SELECT 
            product_name,
            qty,
            price,
            size
          FROM order_items
          WHERE invoice_no = '$order_id'";

$result = mysqli_query($conn,$query);

$products = [];

while($row = mysqli_fetch_assoc($result)){
    $products[] = $row;
}

echo json_encode([
    "status"=>"success",
    "products"=>$products
]);

$conn->close();
?>