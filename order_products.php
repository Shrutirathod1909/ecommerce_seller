<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "db.php";

/* ================= IMAGE URL FUNCTION ================= */
function normalizeImageUrl($image) {
    if (empty($image)) return '';

    $image = ltrim($image, '/');

    if (strpos($image, 'productgallery/') === 0) {
        return IMGPATH . $image;
    }

    // if (strpos($image, 'uploads/') === 0) {
    //     return UPLOAD_URL . substr($image, 8);
    // }

    // return UPLOAD_URL . $image;
}

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

// FETCH PRODUCTS INCLUDING IMAGE FROM products TABLE
$stmt = $conn->prepare("
    SELECT 
        o.product_name,
        o.qty,
        o.size,
        o.sale_price,
        p.image1
    FROM ecommerce_orders o
    LEFT JOIN products p ON p.productid = o.product_id
    WHERE o.order_id = ?
    AND o.hide = 'N'
");

$stmt->bind_param("s", $order_id);
$stmt->execute();
$result = $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = [
        "product_name" => $row["product_name"] ?? "",
        "qty" => $row["qty"] ?? "1",
        "size" => $row["size"] ?? "",
        "sale_price" => $row["sale_price"] ?? "0",
        "image" => normalizeImageUrl($row['image1']),
    ];
}

// RESPONSE
echo json_encode([
    "status" => "success",
    "products" => $products
]);
?>