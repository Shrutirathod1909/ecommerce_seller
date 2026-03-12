<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "db.php";

$action = $_GET['action'] ?? '';

/* ===============================
   GET ORDER LIST
================================ */
if ($action == "list") {

    $query = "SELECT 
                id,
                order_id,
                product_name,
                customer_name,
                order_date,
                qty,
                payment_mode,
                total_price,
                total_discount,
                final_amount,
                approved
              FROM ecommerce_orders
              WHERE hide='N'
              ORDER BY id DESC";

    $result = mysqli_query($conn, $query);

    $orders = [];

    while($row = mysqli_fetch_assoc($result)){
        $orders[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $orders
    ]);
}

/* ===============================
   UPDATE ORDER STATUS
================================ */
elseif ($action == "update_status") {

    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $approved_on = date('Y-m-d H:i:s');

    if($status == 'shipped') {
        $query = "UPDATE ecommerce_orders
                  SET approved='shipped',
                      dispatched='1',
                      dispatched_on=NOW(),
                      modified_on=NOW()
                  WHERE order_id='$order_id'";
    } else {
        $query = "UPDATE ecommerce_orders
                  SET approved='$status',
                      approved_on='$approved_on',
                      modified_on=NOW()
                  WHERE order_id='$order_id'";
    }

    if(mysqli_query($conn, $query)){
        echo json_encode([
            "status" => "success",
            "message" => "Order status updated"
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

    $query = "SELECT * 
              FROM ecommerce_orders
              WHERE order_id='$order_id'";

    $result = mysqli_query($conn, $query);

    $order = mysqli_fetch_assoc($result);

    echo json_encode([
        "status" => "success",
        "order" => $order // key "order" to match Flutter
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