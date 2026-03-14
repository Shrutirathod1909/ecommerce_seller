<?php

header("Content-Type: application/json");
require_once "db.php";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$company_name = $_GET['company_name'] ?? '';
$range = $_GET['range'] ?? null;

if (!$company_name) {
    http_response_code(400);
    echo json_encode(["error" => "Company name is required"]);
    exit;
}

$start_date = null;
$end_date = null;

if ($range) {

    $today = date('Y-m-d');

    switch ($range) {

        case '1week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = $today;
            break;

        case '1month':
            $start_date = date('Y-m-d', strtotime('-1 month'));
            $end_date = $today;
            break;

        case '5months':
            $start_date = date('Y-m-d', strtotime('-5 months'));
            $end_date = $today;
            break;

        case '1year':
            $start_date = date('Y-m-d', strtotime('-1 year'));
            $end_date = $today;
            break;
    }
}

$dateFilter = "";
$params = [$company_name];
$types = "s";

if ($start_date && $end_date) {

    $dateFilter = " AND o.order_date BETWEEN ? AND ? ";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

$sql = "

SELECT 
    o.company_name,
    COUNT(DISTINCT o.customer_name) AS customer_count,
    SUM(CAST(o.qty AS UNSIGNED)) AS total_products_sold,
    SUM(o.final_amount) AS total_revenue,
    AVG(o.final_amount) AS avg_order_value

FROM ecommerce_orders o

JOIN products p 
ON o.product_id = p.productid

WHERE p.hide='N'
AND o.company_name = ?
$dateFilter

GROUP BY o.company_name

";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();

$dashboard = [
    "company_name" => $company_name,
    "customer_count" => 0,
    "total_products_sold" => 0,
    "total_revenue" => 0,
    "avg_order_value" => 0,
    "top_product_name" => null,
    "top_product_owner" => null,
    "top_product_qty" => 0,
    "start_date" => $start_date,
    "end_date" => $end_date
];

if ($row = $result->fetch_assoc()) {

    $dashboard["customer_count"] = intval($row['customer_count']);
    $dashboard["total_products_sold"] = intval($row['total_products_sold']);
    $dashboard["total_revenue"] = floatval($row['total_revenue']);
    $dashboard["avg_order_value"] = floatval($row['avg_order_value']);
}

$stmt->close();


/* -------- TOP PRODUCT -------- */

$sql2 = "

SELECT 
p.item_name,
o.customer_name,
SUM(CAST(o.qty AS UNSIGNED)) as total_qty

FROM ecommerce_orders o

JOIN products p 
ON o.product_id = p.productid

WHERE p.hide='N'
AND o.company_name=?

GROUP BY p.item_name,o.customer_name

ORDER BY total_qty DESC

LIMIT 1

";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s",$company_name);
$stmt2->execute();

$result2 = $stmt2->get_result();

if ($row2 = $result2->fetch_assoc()) {

    $topQty = intval($row2['total_qty']);

    if ($topQty >= 20) {

        $dashboard["top_product_name"] = $row2['item_name'];
        $dashboard["top_product_owner"] = $row2['customer_name'];
        $dashboard["top_product_qty"] = $topQty;

    }
}

echo json_encode($dashboard);

$conn->close();

?>