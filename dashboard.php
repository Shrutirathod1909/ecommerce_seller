
<?php
header("Content-Type: application/json");
require_once "db.php"; // Your DB connection file

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

/* ================= INPUT ================= */
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$company_name = $_GET['company_name'] ?? '';

if (!$vendor_id) {
    http_response_code(400);
    echo json_encode(["error" => "Vendor ID is required"]);
    exit;
}

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

$dashboard = [
    "approved_products" => 0,
    "pending_products" => 0,
    "rejected_products" => 0,
    "total_products" => 0,
    "top_product_name" => "",
    "top_product_qty" => 0,
];

if ($row = $result->fetch_assoc()) {
    $dashboard["approved_products"] = intval($row['approved_products'] ?? 0);
    $dashboard["pending_products"] = intval($row['pending_products'] ?? 0);
    $dashboard["rejected_products"] = intval($row['rejected_products'] ?? 0);
    $dashboard["total_products"] = $dashboard["approved_products"] + $dashboard["pending_products"] + $dashboard["rejected_products"];
}
$stmt->close();

/* ================= TOP PRODUCT ================= */
$sql2 = "
SELECT p.item_name, SUM(CAST(o.qty AS UNSIGNED)) AS total_qty
FROM ecommerce_orders o
JOIN products p ON o.product_id = p.productid
WHERE p.hide='N' AND p.vendor_id = ?
GROUP BY p.item_name
ORDER BY total_qty DESC
LIMIT 1
";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $vendor_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($row2 = $result2->fetch_assoc()) {
    $dashboard["top_product_name"] = $row2['item_name'] ?? "";
    $dashboard["top_product_qty"] = intval($row2['total_qty'] ?? 0);
}

$stmt2->close();

/* ================= OUTPUT ================= */
echo json_encode($dashboard);
$conn->close();
?>
