<?php
header("Content-Type: application/json");
include "db.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!isset($_FILES['file'])) {
        echo json_encode(["status"=>false,"msg"=>"No file uploaded"]);
        exit;
    }

    $fileTmp = $_FILES['file']['tmp_name'];

    if (($handle = fopen($fileTmp, "r")) === FALSE) {
        echo json_encode(["status"=>false,"msg"=>"File open error"]);
        exit;
    }

    $count = 0;

    // ✅ Get header row
    $header = fgetcsv($handle);

    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {

        $data = array_combine($header, $row);

        // ================= SAFE VALUES =================
        $item_name   = mysqli_real_escape_string($conn, $data['Item Name'] ?? '');
        $hsn         = mysqli_real_escape_string($conn, $data['HSN'] ?? '');
        $primary_cat = mysqli_real_escape_string($conn, $data['Primary Category'] ?? '');
        $category    = mysqli_real_escape_string($conn, $data['Category'] ?? '');
        $subcategory = mysqli_real_escape_string($conn, $data['Sub Category'] ?? '');
        $child_cat   = mysqli_real_escape_string($conn, $data['Child Category'] ?? '');
        $gender      = mysqli_real_escape_string($conn, $data['Gender'] ?? '');
        $payment     = mysqli_real_escape_string($conn, $data['Payment Method'] ?? '');
        $country     = mysqli_real_escape_string($conn, $data['Country of Origin'] ?? '');
        $weight      = mysqli_real_escape_string($conn, $data['Weight'] ?? '');
        $gst         = mysqli_real_escape_string($conn, $data['GST Type'] ?? '');
        $desc        = mysqli_real_escape_string($conn, $data['Description'] ?? '');

        // ================= DISCOUNT =================
        $disc_start_date = mysqli_real_escape_string($conn, $data['Discount Start Date'] ?? '');
        $disc_end_date   = mysqli_real_escape_string($conn, $data['Discount END Date'] ?? '');
        $disc_type       = mysqli_real_escape_string($conn, $data['Discount Type'] ?? '');

        // OPTIONAL DATE FORMAT FIX
        if ($disc_start_date != '') {
            $disc_start_date = date('Y-m-d', strtotime($disc_start_date));
        }
        if ($disc_end_date != '') {
            $disc_end_date = date('Y-m-d', strtotime($disc_end_date));
        }

        // ================= PRICE =================
        $cad_price  = mysqli_real_escape_string($conn, $data['CAD Price(Product Details)'] ?? 0);
        $sale_price = mysqli_real_escape_string($conn, $data['Sale Price(Tools&Machinery)'] ?? 0);

        $vendor_id  = 1;
        $company_id = 1;

        // ❌ Skip empty row
        if ($item_name == '') continue;

        // ================= INSERT PRODUCT =================
        $sql1 = "INSERT INTO products 
        (item_name, category, subcategory, child_category, gender, payment_method,
         country_of_origin, weight, gst_type, hsn, primary_categories_name, product_description,
         Discount Start Date, Discount END Date, Discount Type,
         vendor_id, company_id, created_on)
        VALUES 
        ('$item_name', '$category', '$subcategory', '$child_cat', '$gender', '$payment',
         '$country', '$weight', '$gst', '$hsn', '$primary_cat', '$desc',
         '$disc_start_date', '$disc_end_date', '$disc_type',
         '$vendor_id', '$company_id', NOW())";

        if (!mysqli_query($conn, $sql1)) {
            die("Product Error: " . mysqli_error($conn));
        }

        $product_id = mysqli_insert_id($conn);

        // ================= INSERT PRODUCT DETAILS =================
        $sql2 = "INSERT INTO product_detail_description
        (product_id, sale_price, cad_price, created_on)
        VALUES 
        ('$product_id', '$sale_price', '$cad_price', NOW())";

        if (!mysqli_query($conn, $sql2)) {
            die("Details Error: " . mysqli_error($conn));
        }

        // ================= INSERT CAD FILE =================
        $fileName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $fileType = $_FILES['file']['type'];
        $uploaded_by = "admin";

        $sql3 = "INSERT INTO cad_files 
        (product_id, file_name, file_type, file_size, uploaded_by, uploaded_at)
        VALUES 
        ('$product_id', '$fileName', '$fileType', '$fileSize', '$uploaded_by', NOW())";

        if (!mysqli_query($conn, $sql3)) {
            die("CAD File Error: " . mysqli_error($conn));
        }

        $count++;
    }

    fclose($handle);

    echo json_encode([
        "status" => true,
        "msg" => "$count products inserted successfully"
    ]);
}
?>