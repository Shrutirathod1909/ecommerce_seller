<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$order_id = $data['order_id'] ?? '';

if(empty($order_id)){
    echo json_encode([
        "status"=>"error",
        "message"=>"Order ID required"
    ]);
    exit;
}

$query = "SELECT eo.order_id,
                 eo.order_date,
                 bd.customer_name,
                 bd.email_id,
                 bd.contact_no,
                 bd.customer_address,
                 bd.city,
                 bd.state,
                 bd.country,
                 bd.pincode,
                 bd.total_amount,
                 bd.totalgst,
                 bd.total_discount,
                 bd.shipping_charges,
                 bd.balance_amount,
                 bd.status
          FROM ecommerce_orders eo
          JOIN bill_details bd
          ON eo.order_id = bd.invoice_no
          WHERE eo.order_id='$order_id'";

$result = mysqli_query($conn,$query);

if(mysqli_num_rows($result) > 0){

    $order = mysqli_fetch_assoc($result);

    echo json_encode([
        "status"=>"success",
        "order"=>$order
    ]);

}else{

    echo json_encode([
        "status"=>"error",
        "message"=>"Order not found"
    ]);
}

$conn->close();
?>