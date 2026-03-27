<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// ✅ INDIA TIMEZONE
date_default_timezone_set("Asia/Kolkata");

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

    // ✅ CURRENT IST TIME
$currentTime = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
$currentTime = $currentTime->format("Y-m-d H:i:s");

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
                modified_on = ?
            WHERE order_id = ?
        ");

        $stmt->bind_param("ssss", $status, $currentTime, $currentTime, $order_id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Order updated",
            "time" => $currentTime   // 🔥 helpful for frontend
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
   ADD TO CART
================================ */
elseif($action == "seller_cart") {

    $company_id = $_GET['company_id'] ?? '';
    $from_date  = $_GET['from_date'] ?? '';
    $to_date    = $_GET['to_date'] ?? '';

    if($company_id == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Company ID missing"
        ]);
        exit;
    }

    $query = "
        SELECT 
            c.cart_id,
            c.userid,
            c.productid,
            MAX(c.quantity) AS quantity,
            c.unit_price,
            c.total_price,
            c.final_amount,
            c.gst_percentage,
            c.shipping_price,
            c.disc_amt,
            c.status,
            c.created_on,
            l.fullname AS customer_name,
            p.item_name AS item_name
        FROM cart c
        LEFT JOIN login l ON c.userid = l.userid
        LEFT JOIN products p ON c.productid = p.productid
        WHERE p.company_id = ? AND c.status='Added in cart'
    ";

    $params = [$company_id];
    $types = "s";

    if ($from_date != "") {
        $query .= " AND DATE(c.created_on) >= ?";
        $params[] = $from_date;
        $types .= "s";
    }

    if ($to_date != "") {
        $query .= " AND DATE(c.created_on) <= ?";
        $params[] = $to_date;
        $types .= "s";
    }

    $query .= " GROUP BY c.userid, c.productid ORDER BY c.created_on DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $carts = [];
    while($row = $result->fetch_assoc()) {
        $carts[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "count" => count($carts),
        "data" => $carts
    ]);
}


elseif($action == "seller_wishlist") {

    $company_id = $_GET['company_id'] ?? '';
    $from_date  = $_GET['from_date'] ?? '';
    $to_date    = $_GET['to_date'] ?? '';

    if($company_id == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Company ID missing"
        ]);
        exit;
    }

    $query = "
        SELECT 
            MIN(w.id) AS wishlist_id,
            w.userid,
            w.productid,
            MAX(w.quantity) AS quantity,
            w.unit_price,
            w.total_price,
            w.final_amount,
            w.status,
            w.created_on,
            l.fullname AS customer_name,
            p.item_name
        FROM wishlist w
        LEFT JOIN login l ON w.userid = l.userid
        LEFT JOIN products p ON w.productid = p.productid
        WHERE p.company_id = ? AND w.status='Created'
    ";

    $params = [$company_id];
    $types = "s";

    if ($from_date != "") {
        $query .= " AND DATE(w.created_on) >= ?";
        $params[] = $from_date;
        $types .= "s";
    }

    if ($to_date != "") {
        $query .= " AND DATE(w.created_on) <= ?";
        $params[] = $to_date;
        $types .= "s";
    }

    $query .= " GROUP BY w.userid, w.productid ORDER BY w.created_on DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $wishlists = [];
    while($row = $result->fetch_assoc()) {
        $wishlists[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "count" => count($wishlists),
        "data" => $wishlists
    ]);
}



/* ===============================
   ORDER DETAILS (RECEIVED / FAILED)
================================ */
elseif($action == "order_details_list") {

    $company_id = $_GET['company_id'] ?? '';
    $type       = $_GET['type'] ?? ''; // received / failed
    $from_date  = $_GET['from_date'] ?? '';
    $to_date    = $_GET['to_date'] ?? '';

    if($company_id == "" || $type == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Missing parameters"
        ]);
        exit;
    }

    $query = "
        SELECT 
            fo.id,
            fo.order_id,
            fo.status,
            fo.created_on,
            p.item_name,
            l.fullname AS customer_name
        FROM fulfill_orders fo
        INNER JOIN products p ON fo.product_id = p.productid
        LEFT JOIN login l ON fo.created_by = l.userid
        WHERE p.company_id = ?
    ";

    $params = [$company_id];
    $types = "s";

    // ✅ TYPE FILTER
    if ($type == "received") {
        $query .= " AND LOWER(fo.status) IN ('order received','order placed')";
    } else if ($type == "failed") {
        $query .= " AND LOWER(fo.status) IN ('failed','canceled','cancelled','pickup cancelled')";
    }

    // ✅ DATE FILTER
    if ($from_date != "") {
        $query .= " AND DATE(fo.created_on) >= ?";
        $params[] = $from_date;
        $types .= "s";
    }

    if ($to_date != "") {
        $query .= " AND DATE(fo.created_on) <= ?";
        $params[] = $to_date;
        $types .= "s";
    }

    $query .= " ORDER BY fo.created_on DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "count" => count($data),
        "data" => $data
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