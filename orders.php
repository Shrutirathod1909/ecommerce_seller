<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "db.php";

$action = $_GET['action'] ?? '';

/* ===============================
   GET ORDER LIST
================================ */
if ($action == "list") {

    $company_name = $_GET['company_name'] ?? '';

    if($company_name == ""){
        echo json_encode([
            "status"=>"error",
            "message"=>"Company name missing"
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

    $stmt->bind_param("s",$company_name);
    $stmt->execute();

    $result = $stmt->get_result();

    $orders = [];

    while($row = $result->fetch_assoc()){
        $orders[] = $row;
    }

    echo json_encode([
        "status"=>"success",
        "count"=>count($orders),
        "data"=>$orders
    ]);
}

/* ===============================
   UPDATE ORDER STATUS
================================ */
elseif ($action == "update_status") {

    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? '';

    if($order_id=="" || $status==""){
        echo json_encode([
            "status"=>"error",
            "message"=>"Missing data"
        ]);
        exit;
    }

    if($status == "shipped"){

        $query = "UPDATE ecommerce_orders
                  SET approved='shipped',
                      dispatched='1',
                      dispatched_on=NOW(),
                      modified_on=NOW()
                  WHERE order_id='$order_id'";

    } else {

        $query = "UPDATE ecommerce_orders
                  SET approved='$status',
                      approved_on=NOW(),
                      modified_on=NOW()
                  WHERE order_id='$order_id'";
    }

    if(mysqli_query($conn,$query)){
        echo json_encode([
            "status"=>"success",
            "message"=>"Order updated"
        ]);
    } else {
        echo json_encode([
            "status"=>"error",
            "message"=>"Update failed"
        ]);
    }
}

/* ===============================
   ORDER DETAILS
================================ */
elseif ($action == "details") {

    $order_id = $_GET['order_id'] ?? '';

    $query = "SELECT * FROM ecommerce_orders WHERE order_id='$order_id'";
    $result = mysqli_query($conn,$query);

    $order = mysqli_fetch_assoc($result);

    echo json_encode([
        "status"=>"success",
        "order"=>$order
    ]);
}

/* ===============================
   INVALID
================================ */
else{

    echo json_encode([
        "status"=>"error",
        "message"=>"Invalid action"
    ]);

}
?>