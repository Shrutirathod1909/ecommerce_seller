<?php

header("Content-Type: application/json");
require_once "db.php";

$invoice_no = $_GET['invoice_no'] ?? '';

if(empty($invoice_no)){
 echo json_encode([
  "status"=>"error",
  "message"=>"Invoice number required"
 ]);
 exit;
}

/* BILL HEADER */

$stmt = $conn->prepare("
SELECT
invoice_no,
customer_name,
customer_address,
city,
state,
pincode,
contact_no,
email_id,
total_amount,
totalgst,
total_discount,
shipping_charges,
received_amount,
balance_amount,
mode,
created_on
FROM bill_details
WHERE invoice_no=?
");

$stmt->bind_param("s",$invoice_no);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

/* BILL PRODUCTS */

$items = [];

$stmt2 = $conn->prepare("
SELECT
company_name,
product_name,
sku_code,
qty,
sale_price,
total_price,
hsn_code
FROM ecommerce_orders
WHERE order_id=?
");

$stmt2->bind_param("s",$invoice_no);
$stmt2->execute();

$result = $stmt2->get_result();

while($row=$result->fetch_assoc()){
$items[]=$row;
}

echo json_encode([
"status"=>"success",
"bill"=>$bill,
"items"=>$items
]);

?>