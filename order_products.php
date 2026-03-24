<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "db.php";

// GET INPUT
$data = json_decode(file_get_contents("php://input"), true);
$order_id = $data['order_id'] ?? '';

if ($order_id == '') {
    echo json_encode([
        "status" => "error",
        "message" => "Order ID required"
    ]);
    exit;
}

// FETCH PRODUCTS (SAME TABLE)
$stmt = $conn->prepare("
    SELECT 
        product_name,
        qty,
        size,
        sale_price
    FROM ecommerce_orders
    WHERE order_id = ?
    AND hide = 'N'
");

$stmt->bind_param("s", $order_id);
$stmt->execute();
$result = $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {

    // 🔥 FORCE SAFE DATA (VERY IMPORTANT)
    $products[] = [
        "product_name" => $row["product_name"] ?? "",
        "qty" => $row["qty"] ?? "1",
        "size" => $row["size"] ?? "",
        "sale_price" => $row["sale_price"] ?? "0"
    ];
}

// RESPONSE
echo json_encode([
    "status" => "success",
    "products" => $products
]);