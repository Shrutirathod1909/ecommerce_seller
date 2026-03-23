<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// ✅ FORCE INDIA TIMEZONE (STRONG FIX)
$dt = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
$currentTime = $dt->format("Y-m-d H:i:s");

require_once "db.php";

$action = $_GET['action'] ?? '';

/* ===============================
   GET ORDER LIST
================================ */
if ($action == "list") {

    $company_name = $_GET['company_name'] ?? '';

    if ($company_name == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Company name missing"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            id,
            order_id,
            product_name,
            customer_name,
            company_name,
            order_date,
            qty,
            payment_mode,
            total_price,
            total_discount,
            final_amount,
            approved
        FROM ecommerce_orders
        WHERE hide='N'
        AND LOWER(company_name)=LOWER(?)
        ORDER BY id DESC
    ");

    $stmt->bind_param("s", $company_name);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "count" => count($orders),
        "data" => $orders
    ]);
}


/* ===============================
   UPDATE ORDER STATUS
================================ */
elseif ($action == "update_status") {

    $order_id = $_POST['order_id'] ?? '';
    $status   = $_POST['status'] ?? '';
    $reason   = $_POST['reason'] ?? '';

    if ($order_id == "" || $status == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Missing data"
        ]);
        exit;
    }

    // ✅ GET CURRENT IST TIME (LIVE)
    $dt = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
    $currentTime = $dt->format("Y-m-d H:i:s");

    if ($status == "shipped") {

        $stmt = $conn->prepare("
            UPDATE ecommerce_orders
            SET approved = ?,
                dispatched = '1',
                dispatched_on = ?,
                modified_on = ?
            WHERE order_id = ?
        ");

        $stmt->bind_param("ssss", $status, $currentTime, $currentTime, $order_id);

    } else {

        $stmt = $conn->prepare("
            UPDATE ecommerce_orders
            SET approved = ?,
                approved_on = ?,
                modified_on = ?,
                reject_reason = ?
            WHERE order_id = ?
        ");

        $stmt->bind_param("sssss", $status, $currentTime, $currentTime, $reason, $order_id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Order updated",

            // 🔥 IMPORTANT FOR FLUTTER
            "time" => $currentTime,
            "timezone" => "Asia/Kolkata"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Update failed"
        ]);
    }
}


/* ===============================
   ORDER DETAILS
================================ */
elseif ($action == "details") {

    $order_id = $_GET['order_id'] ?? '';

    if ($order_id == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Order ID missing"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT * FROM ecommerce_orders WHERE order_id = ?
    ");

    $stmt->bind_param("s", $order_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "order" => $order
    ]);
}


/* ===============================
   INVALID ACTION
================================ */
else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid action"
    ]);
}
?>