<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "db.php";

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? $_GET['action'] ?? "";


/* ---------------- APPROVE VENDOR ---------------- */
if ($action == "approve_vendor") {

    $vendor_id = $input['vendor_id'] ?? $_GET['vendor_id'] ?? "";

    if ($vendor_id == "") {
        echo json_encode([
            "status" => "error",
            "message" => "vendor_id required"
        ]);
        exit;
    }

    $check = $conn->query("SELECT approved FROM vendors WHERE id='$vendor_id'");
    $row = $check->fetch_assoc();

    if ($row && $row['approved'] != 'approved') {

        $conn->query("UPDATE vendors SET approved='approved' WHERE id='$vendor_id'");

        echo json_encode([
            "status" => "success",
            "message" => "Vendor approved successfully",
            "notify" => "Your account has been approved"
        ]);

    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Already approved or invalid ID"
        ]);
    }

    exit;
}


/* ---------------- NOTIFICATION COUNT ---------------- */
if ($action == "notification_count") {

    $company_name = $input['company_name'] ?? $_GET['company_name'] ?? "";

    // ORDERS (pending only)
    $sql1 = "SELECT COUNT(DISTINCT id) as total
             FROM ecommerce_orders
             WHERE company_name='$company_name'
             AND approved='pending'
             AND hide='N'";

    $r1 = $conn->query($sql1);
    $orderCount = $r1->fetch_assoc()['total'] ?? 0;

    // PRODUCTS (ONLY approved + rejected)
    $sql2 = "SELECT COUNT(DISTINCT productid) as total
             FROM products
             WHERE company_id IN (
                 SELECT company_id 
                 FROM vendors 
                 WHERE company_name='$company_name'
             )
             AND hide='N'
             AND (verified='1' OR rejected='1')";

    $r2 = $conn->query($sql2);
    $productCount = $r2->fetch_assoc()['total'] ?? 0;

    echo json_encode([
        "status" => "success",
        "count" => $orderCount + $productCount
    ]);

    exit;
}


/* ---------------- NOTIFICATION LIST ---------------- */
if ($action == "notification_list") {

    $company_name = $input['company_name'] ?? $_GET['company_name'] ?? "";

    $sql = "
    SELECT * FROM (

        /* ---------------- ORDERS (PENDING ONLY) ---------------- */
        SELECT 
            CONCAT('O-', id) AS unique_id,
            id,
            order_id,
            product_name,
            customer_name,
            approved,
            order_date,
            'order' AS type
        FROM ecommerce_orders
        WHERE company_name='$company_name'
        AND approved='pending'
        AND hide='N'

        UNION ALL

        /* ---------------- PRODUCTS (APPROVED + REJECTED ONLY) ---------------- */
        SELECT 
            CONCAT('P-', productid) AS unique_id,
            productid AS id,
            '' AS order_id,
            item_name AS product_name,
            '' AS customer_name,

            CASE 
                WHEN verified='1' THEN 'approved'
                WHEN rejected='1' THEN 'rejected'
            END AS approved,

            created_on AS order_date,
            'product' AS type
        FROM products
        WHERE company_id IN (
            SELECT company_id 
            FROM vendors 
            WHERE company_name='$company_name'
        )
        AND hide='N'
        AND (verified='1' OR rejected='1')

    ) AS all_data

    GROUP BY unique_id
    ORDER BY order_date DESC
    ";

    $result = $conn->query($sql);

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);

    exit;
}


/* ---------------- CLEAR NOTIFICATIONS ---------------- */
if ($action == "clear_notifications") {

    $company_name = $_GET['company_name'] ?? "";

    if ($company_name == "") {
        echo json_encode([
            "status" => "error",
            "message" => "company_name required"
        ]);
        exit;
    }

    // hide orders
    $conn->query("
        UPDATE ecommerce_orders 
        SET hide='Y'
        WHERE company_name='$company_name'
    ");

    // hide products
    $conn->query("
        UPDATE products 
        SET hide='Y'
        WHERE company_id IN (
            SELECT company_id FROM vendors WHERE company_name='$company_name'
        )
    ");

    echo json_encode([
        "status" => "success",
        "message" => "Notifications cleared"
    ]);

    exit;
}
?>