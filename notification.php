<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "db.php";

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? $_GET['action'] ?? "";

/* ================= APPROVE VENDOR ================= */
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

/* ================= NOTIFICATION COUNT ================= */
if ($action == "notification_list") {

    $company_name = $_GET['company_name'] ?? "";

    if ($company_name == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Company name required"
        ]);
        exit;
    }

    $company_name = $conn->real_escape_string($company_name);

    $sql = "
    SELECT * FROM (

        /* ================= ORDER ================= */
        SELECT 
            CONCAT('O-', MAX(eo.id)) AS unique_id,
            MAX(eo.id) AS id,
            eo.order_id,
            eo.product_name,
            eo.customer_name,
            eo.approved,
            eo.order_date,
            'order' AS type
        FROM ecommerce_orders eo
        WHERE eo.company_name = '$company_name'
        AND eo.approved = 'pending'
        AND eo.hide = 'N'
        GROUP BY DATE_FORMAT(eo.order_date, '%Y-%m-%d %H:%i')

        UNION

        /* ================= PRODUCT ================= */
        SELECT 
            CONCAT('P-', MAX(p.productid)) AS unique_id,
            MAX(p.productid) AS id,
            '' AS order_id,
            p.item_name AS product_name,
            '' AS customer_name,
            CASE 
                WHEN p.verified = '1' THEN 'approved'
                WHEN p.rejected = '1' THEN 'rejected'
            END AS approved,
            p.created_on AS order_date,
            'product' AS type
        FROM products p
        WHERE p.company_id IN (
            SELECT company_id FROM vendors WHERE company_name = '$company_name'
        )
        AND p.hide = 'N'
        AND (p.verified = '1' OR p.rejected = '1')
        GROUP BY DATE_FORMAT(p.created_on, '%Y-%m-%d %H:%i')

    ) AS all_notifications

    ORDER BY order_date DESC
    LIMIT 100
    ";

    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode([
            "status" => "error",
            "message" => "SQL Error: " . $conn->error
        ]);
        exit;
    }

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


/* =========================================================
   🔔 NOTIFICATION COUNT (ONLY NEW)
========================================================= */
if ($action == "notification_count") {

    $company_name = $_GET['company_name'] ?? "";
    $last_seen = $_GET['last_seen'] ?? 0;

    if ($company_name == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Company name required"
        ]);
        exit;
    }

    $company_name = $conn->real_escape_string($company_name);
    $last_seen = intval($last_seen);

    $sql = "
    SELECT COUNT(*) as total FROM (

        /* ORDER */
        SELECT MAX(eo.id) AS id
        FROM ecommerce_orders eo
        WHERE eo.company_name = '$company_name'
        AND eo.approved = 'pending'
        AND eo.hide = 'N'
        AND eo.order_date > FROM_UNIXTIME($last_seen / 1000)
        GROUP BY DATE_FORMAT(eo.order_date, '%Y-%m-%d %H:%i')

        UNION

        /* PRODUCT */
        SELECT MAX(p.productid) AS id
        FROM products p
        WHERE p.company_id IN (
            SELECT company_id FROM vendors WHERE company_name = '$company_name'
        )
        AND p.hide = 'N'
        AND (p.verified = '1' OR p.rejected = '1')
        AND p.created_on > FROM_UNIXTIME($last_seen / 1000)
        GROUP BY DATE_FORMAT(p.created_on, '%Y-%m-%d %H:%i')

    ) AS new_notifications
    ";

    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode([
            "status" => "error",
            "message" => "SQL Error: " . $conn->error
        ]);
        exit;
    }

    $row = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "count" => $row['total'] ?? 0
    ]);
    exit;
}


?>