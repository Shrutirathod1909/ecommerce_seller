<?php
header("Content-Type: application/json");
require_once "db.php";

/* ================= DB CONNECTION ================= */
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

/* ================= IMAGE URL FUNCTION ================= */
function normalizeImageUrl($image) {
    if (empty($image)) return '';
    $image = ltrim($image, '/');
    return IMGPATH . $image;
}

/* ================= INPUT ================= */
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if ($vendor_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid vendor_id"
    ]);
    exit;
}

/* ================= DEFAULT RESPONSE ================= */
$dashboard = [
    "status" => true,
    "approved_products" => 0,
    "pending_products" => 0,
    "rejected_products" => 0,
    "total_products" => 0,
    "top_products" => []
];

/* ================= PRODUCT STATUS ================= */
$sql = "
SELECT 
    SUM(CASE WHEN verified=1 AND rejected=0 AND hide='N' THEN 1 ELSE 0 END) AS approved_products,
    SUM(CASE WHEN verified=0 AND rejected=0 AND hide='N' THEN 1 ELSE 0 END) AS pending_products,
    SUM(CASE WHEN rejected=1 AND hide='N' THEN 1 ELSE 0 END) AS rejected_products
FROM products
WHERE vendor_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $dashboard["approved_products"] = (int)$row['approved_products'];
    $dashboard["pending_products"] = (int)$row['pending_products'];
    $dashboard["rejected_products"] = (int)$row['rejected_products'];

    $dashboard["total_products"] =
        $dashboard["approved_products"] +
        $dashboard["pending_products"] +
        $dashboard["rejected_products"];
}
$stmt->close();

/* ================= TOP PRODUCTS (COUNT) ================= */
$sql2 = "
SELECT 
    p.productid,
    p.item_name,
    p.image1,
    COUNT(*) AS total_orders

FROM fulfill_orders f
JOIN products p ON f.product_id = p.productid

WHERE p.hide='N'
  AND p.vendor_id = ?
  AND f.status IN ('Order Received', 'Order Placed')

GROUP BY p.productid, p.item_name, p.image1
ORDER BY total_orders DESC
LIMIT 5
";

$stmt2 = $conn->prepare($sql2);

if (!$stmt2) {
    echo json_encode([
        "status" => false,
        "message" => "Query failed",
        "error" => $conn->error
    ]);
    exit;
}

$stmt2->bind_param("i", $vendor_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

/* ================= FETCH DATA ================= */
while ($row2 = $result2->fetch_assoc()) {
    $dashboard["top_products"][] = [
        "product_id" => (int)$row2['productid'],
        "item_name" => $row2['item_name'],
        "image" => normalizeImageUrl($row2['image1']),
        "total_orders" => (int)$row2['total_orders']
    ];
}

$stmt2->close();

/* ================= FINAL OUTPUT ================= */
echo json_encode($dashboard);

$conn->close();
?>