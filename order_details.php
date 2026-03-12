<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

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

$stmt = $conn->prepare("
SELECT 
order_id,
order_date,
customer_name,
email_id,
contact_no,
customer_address,
city,
state,
country,
pincode,

approved,
approved_on,

IFNULL(total_price,0) as total_price,
IFNULL(cgst,0) as cgst,
IFNULL(sgst,0) as sgst,
IFNULL(total_discount,0) as total_discount,
IFNULL(shipping_price,0) as shipping_price,
IFNULL(final_amount,0) as final_amount

FROM ecommerce_orders
WHERE order_id=?
LIMIT 1
");

$stmt->bind_param("s",$order_id);
$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows > 0){

    $order = $result->fetch_assoc();

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

$stmt->close();
$conn->close();
?>