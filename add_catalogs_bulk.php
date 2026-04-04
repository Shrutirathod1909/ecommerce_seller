<?php
header("Content-Type: application/json");

// 🔥 Prevent HTML errors in response
error_reporting(0);
ini_set('display_errors', 0);

include "db.php";

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(["status" => false, "msg" => "Invalid request"]);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(["status" => false, "msg" => "No CSV file uploaded"]);
    exit;
}

$fileTmp = $_FILES['file']['tmp_name'];

if (($handle = fopen($fileTmp, "r")) === FALSE) {
    echo json_encode(["status" => false, "msg" => "File open error"]);
    exit;
}

$vendor_id  = $_POST['vendor_id'] ?? 0;
$company_id = $_POST['company_id'] ?? 0;

$count = 0;
$product_ids = [];

// ================= HEADER =================
$header = fgetcsv($handle);

$header = array_map(function ($h) {
    return strtolower(trim(preg_replace('/\s+/', ' ', $h)));
}, $header);

// ================= LOOP CSV =================
while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {

    $row = array_map('trim', $row);
    $data = array_combine($header, $row);
    if (!$data) continue;

    $item_name = mysqli_real_escape_string($conn, $data['item name'] ?? '');
    if ($item_name == '') continue;

    $hsn         = mysqli_real_escape_string($conn, $data['hsn'] ?? '');
    $category    = mysqli_real_escape_string($conn, $data['category'] ?? '');
    $subcategory = mysqli_real_escape_string($conn, $data['sub category'] ?? '');
    $child_cat   = mysqli_real_escape_string($conn, $data['child category'] ?? '');
    $gender      = mysqli_real_escape_string($conn, $data['gender'] ?? '');
    $payment     = mysqli_real_escape_string($conn, $data['payment method'] ?? '');
    $country     = mysqli_real_escape_string($conn, $data['country of origin'] ?? '');
    $weight      = mysqli_real_escape_string($conn, $data['weight'] ?? '');
    $gst         = mysqli_real_escape_string($conn, $data['gst type'] ?? '');
    $desc        = mysqli_real_escape_string($conn, $data['description'] ?? '');
    $primary_cat = mysqli_real_escape_string($conn, $data['primary category'] ?? '');

    $disc_type = mysqli_real_escape_string($conn, $data['discount type'] ?? '');
    $disc_amt  = mysqli_real_escape_string($conn, $data['discount amount'] ?? 0);

    $disc_start_date = !empty($data['discount start date'])
        ? date('Y-m-d', strtotime($data['discount start date']))
        : NULL;

    $disc_end_date = !empty($data['discount end date'])
        ? date('Y-m-d', strtotime($data['discount end date']))
        : NULL;

    $cad_price  = mysqli_real_escape_string($conn, $data['cad price(product details)'] ?? 0);
    $sale_price = mysqli_real_escape_string($conn, $data['sale price(tools&machinery)'] ?? 0);
    $sku_code   = mysqli_real_escape_string($conn, $data['product sku(product details)'] ?? '');

    // 🔥 NEW (IMAGE NAME FROM CSV)
    $image_name = mysqli_real_escape_string($conn, $data['image name'] ?? '');

    // ================= INSERT PRODUCT =================
    $sql1 = "INSERT INTO products 
    (item_name, category, subcategory, child_category, gender, payment_method,
     country_of_origin, weight, gst_type, hsn, primary_categories_name, product_description,
     disc_start_date, disc_end_date, disc_type, disc_amt,
     vendor_id, company_id, image1, created_on)
    VALUES 
    ('$item_name', '$category', '$subcategory', '$child_cat', '$gender', '$payment',
     '$country', '$weight', '$gst', '$hsn', '$primary_cat', '$desc',
     '$disc_start_date', '$disc_end_date', '$disc_type', '$disc_amt',
     '$vendor_id', '$company_id', '$image_name', NOW())";

    if (!mysqli_query($conn, $sql1)) {
        echo json_encode([
            "status" => false,
            "msg" => "Product Error: " . mysqli_error($conn)
        ]);
        exit;
    }

    // ✅ GET INSERTED ID (productid)
    $product_id = mysqli_insert_id($conn);
    $product_ids[] = $product_id;

    // ================= INSERT PRICE =================
    mysqli_query($conn, "INSERT INTO product_detail_description
    (product_id, sku_code, sale_price, cad_price, created_on)
    VALUES 
    ('$product_id', '$sku_code','$sale_price', '$cad_price', NOW())");

    $count++;
}

fclose($handle);

// ================= SAVE CSV FILE =================
$cad_file_name = $_FILES['file']['name'];
$cad_file_type = $_FILES['file']['type'];
$cad_file_size = $_FILES['file']['size'];

foreach ($product_ids as $pid) {
    mysqli_query($conn, "INSERT INTO cad_files
    (product_id, file_name, file_type, file_size, uploaded_by, uploaded_at)
    VALUES
    ('$pid', '$cad_file_name', '$cad_file_type', '$cad_file_size', '$vendor_id', NOW())");
}

// ================= GET PRODUCTS DATA =================
$data = [];

if (!empty($product_ids)) {

    $ids = implode(",", array_map('intval', $product_ids));

    $sql = "SELECT 
    p.productid AS product_id, 
    p.item_name, 
    p.image1,
    d.sku_code, 
    d.sale_price
    FROM products p
    LEFT JOIN product_detail_description d 
    ON p.productid = d.product_id
    WHERE p.productid IN ($ids)
    ORDER BY p.productid DESC";

    $result = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

// ================= FINAL RESPONSE =================
echo json_encode([
    "status" => true,
    "msg" => "$count products uploaded successfully",
    "products" => $data
]);
?>