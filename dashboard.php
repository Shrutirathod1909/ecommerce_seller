<?php
header("Content-Type: application/json");
require_once "db.php";

/* ================= DB CONNECTION ================= */
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

/* ================= IMAGE URL FUNCTION ================= */
function normalizeImageUrl($image) {
    if (empty($image)) return '';

    $image = ltrim($image, '/');

    if (strpos($image, 'productgallery/') === 0) {
        return IMGPATH . $image;
    }

    if (strpos($image, 'uploads/') === 0) {
        return UPLOAD_URL . substr($image, 8);
    }

    return UPLOAD_URL . $image;
}

/* ================= INPUT ================= */
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if (!$vendor_id) {
    http_response_code(400);
    echo json_encode(["error" => "Vendor ID is required"]);
    exit;
}

/* ================= DEFAULT RESPONSE ================= */
$dashboard = [
    "approved_products" => 0,
    "pending_products" => 0,
    "rejected_products" => 0,
    "total_products" => 0,
    "top_products" => [],

   
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
    $dashboard["approved_products"] = intval($row['approved_products'] ?? 0);
    $dashboard["pending_products"] = intval($row['pending_products'] ?? 0);
    $dashboard["rejected_products"] = intval($row['rejected_products'] ?? 0);

    $dashboard["total_products"] =
        $dashboard["approved_products"] +
        $dashboard["pending_products"] +
        $dashboard["rejected_products"];
}
$stmt->close();

/* ================= TOP 5 PRODUCTS ================= */
$sql2 = "
SELECT 
    p.productid,
    p.item_name,
    p.image1,
    SUM(CAST(o.qty AS UNSIGNED)) AS total_qty
FROM ecommerce_orders o
JOIN products p ON o.product_id = p.productid
WHERE p.hide='N' 
  AND p.vendor_id = ?
GROUP BY p.productid, p.item_name, p.image1
ORDER BY total_qty DESC
LIMIT 5
";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $vendor_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

$top_products = [];
while ($row2 = $result2->fetch_assoc()) {
    $top_products[] = [
        "product_id" => $row2['productid'],
        "item_name" => $row2['item_name'],
        "image" => normalizeImageUrl($row2['image1']),
        "total_qty" => intval($row2['total_qty'])
    ];
}
$dashboard["top_products"] = $top_products;
$stmt2->close();


/* ================= OUTPUT ================= */
echo json_encode($dashboard);
$conn->close();
?>