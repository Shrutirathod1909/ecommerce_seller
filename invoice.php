<?php

header("Content-Type: application/json");
require_once "db.php";

/* ================= GET DATA (POST) ================= */
$invoice_no = $_POST['invoice_no'] ?? '';

if(empty($invoice_no)){
 echo json_encode([
  "status"=>"error",
  "message"=>"Invoice number required"
 ]);
 exit;
}

/* ================= BILL DETAILS ================= */
$stmt = $conn->prepare("
SELECT
invoice_no,
customer_name,
customer_address,
city,
pincode,
contact_no,
email_id,
total_amount,
totalgst,
total_discount,
shipping_charges,
created_on
FROM bill_details
WHERE invoice_no=?
");

$stmt->bind_param("s",$invoice_no);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if(!$bill){
 echo json_encode([
  "status"=>"error",
  "message"=>"Invoice not found"
 ]);
 exit;
}

/* ================= IMAGE URL FUNCTION ================= */
function normalizeImageUrl($image) {
    if (empty($image)) return '';

    $image = ltrim($image, '/');

    if (strpos($image, 'productgallery/') === 0) {
        return IMGPATH . $image;
    }

    if (strpos($image, 'uploads/') === 0) {
        return UPLOAD_URL . substr($image, 8);
    }

    return UPLOAD_URL . $image;
}

/* ================= ITEMS + IMAGE + MERGE ================= */
$mergedItems = [];

$stmt2 = $conn->prepare("
SELECT
eo.company_name,
eo.product_name,
eo.sku_code,
eo.qty,
eo.sale_price,
eo.total_price,
p.image1
FROM ecommerce_orders eo
LEFT JOIN products p 
ON TRIM(LOWER(eo.product_name)) = TRIM(LOWER(p.item_name))
WHERE eo.order_id=?
");

$stmt2->bind_param("s",$invoice_no);
$stmt2->execute();
$result = $stmt2->get_result();

while($row = $result->fetch_assoc()){

    $key = strtolower(trim($row['product_name']));

    // ✅ image add
    $image = normalizeImageUrl($row['image1'] ?? '');

    if(isset($mergedItems[$key])){
        // ✅ merge qty + total
        $mergedItems[$key]['qty'] += (int)$row['qty'];
        $mergedItems[$key]['total_price'] += (float)$row['total_price'];
    } else {
        $mergedItems[$key] = [
            "company_name" => $row['company_name'],
            "product_name" => $row['product_name'],
            "sku_code" => $row['sku_code'],
            "qty" => (int)$row['qty'],
            "sale_price" => $row['sale_price'],
            "total_price" => (float)$row['total_price'],
            "image" => $image
        ];
    }
}

$items = array_values($mergedItems);

/* ================= COMPANY DETAILS ================= */
$stmt3 = $conn->prepare("
SELECT
site_name,
contact_phone,
contact_email,
address,
site_logo,
gst_no
FROM settings
ORDER BY id DESC
LIMIT 1
");

$stmt3->execute();
$company = $stmt3->get_result()->fetch_assoc();

/* ================= FIX site_logo ================= */
if (!empty($company['site_logo'])) {
    $company['site_logo'] =IMGPATH . ltrim($company['site_logo'], '/');
} else {
    $company['site_logo'] = ''; // fallback if empty
}

/* ================= FINAL RESPONSE ================= */
echo json_encode([
 "status"=>"success",
 "company"=>$company,
 "bill"=>$bill,
 "items"=>$items
]);