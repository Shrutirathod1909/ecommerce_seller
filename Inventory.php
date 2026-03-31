<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php";




// ================= IMAGE URL FUNCTION =================
function normalizeImageUrl($image) {
    if (empty($image)) return '';

    $image = ltrim($image, '/');

    if (strpos($image, 'productgallery/') === 0) {
        return IMGPATH . $image;
    }

//     if (strpos($image, 'uploads/') === 0) {
//         return UPLOAD_URL . substr($image, 8);
//     }

//     return UPLOAD_URL . $image;
 }

// ================= GET METHOD =================
if ($_SERVER['REQUEST_METHOD'] === "GET") {

    $vendor_id = mysqli_real_escape_string($conn, $_GET['vendor_id'] ?? '');
    if (empty($vendor_id)) {
        echo json_encode(["status"=>"error","message"=>"vendor_id required"]);
        exit;
    }

    // Ensure stock rows exist
    $allProducts = mysqli_query($conn, "SELECT productid FROM products WHERE vendor_id='$vendor_id'");
    while ($p = mysqli_fetch_assoc($allProducts)) {
        $product_id = $p['productid'];
        $check = mysqli_query($conn, "SELECT 1 FROM product_stock WHERE product_id='$product_id'");
        if (mysqli_num_rows($check) === 0) {
            mysqli_query($conn, "INSERT INTO product_stock (product_id, stock_count) VALUES ('$product_id', 0)");
        }
    }

    // ================= COUNT =================
    $count_sql = "SELECT 
        COUNT(DISTINCT p.productid) AS total_products,
        SUM(CASE WHEN s.stock_count > 50 THEN 1 ELSE 0 END) AS total_stock,
        SUM(CASE WHEN s.stock_count = 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN s.stock_count BETWEEN 1 AND 4 THEN 1 ELSE 0 END) AS low_stock
    FROM products p
    LEFT JOIN (
        SELECT product_id, MAX(stock_count) AS stock_count
        FROM product_stock
        GROUP BY product_id
    ) s ON p.productid = s.product_id
    WHERE p.vendor_id='$vendor_id' AND p.hide='N' AND p.verified='1'";

    $count_result = mysqli_query($conn, $count_sql);
    $count_data = mysqli_fetch_assoc($count_result);

    // ================= LIST =================
    $sql = "SELECT 
                p.productid,
                p.item_name,
                p.image1,
                COALESCE(s.stock_count,0) AS stock_count
            FROM products p
            LEFT JOIN (
                SELECT product_id, MAX(stock_count) AS stock_count
                FROM product_stock
                GROUP BY product_id
            ) s ON p.productid = s.product_id
            WHERE p.vendor_id='$vendor_id' AND p.hide='N' AND p.verified='1'
            ORDER BY p.productid DESC";

    $result = mysqli_query($conn, $sql);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['stock_count'] = (int)$row['stock_count'];
        $row['image'] = normalizeImageUrl($row['image1']);
        unset($row['image1']);
        $data[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "total_products" => (int)$count_data['total_products'],
        "total_stock" => (int)$count_data['total_stock'],
        "out_of_stock" => (int)$count_data['out_of_stock'],
        "low_stock" => (int)$count_data['low_stock'],
        "data" => $data
    ]);
    exit;
}

// ================= POST METHOD =================
elseif ($_SERVER['REQUEST_METHOD'] === "POST") {

    $action = $_POST['action'] ?? '';
    $product_id = mysqli_real_escape_string($conn, $_POST['product_id'] ?? '');
    $qty = (int)($_POST['qty'] ?? 1);

    if (empty($product_id)) {
        echo json_encode(["status"=>"error","message"=>"product_id required"]);
        exit;
    }

    // Ensure product_stock exists
    $check = mysqli_query($conn, "SELECT 1 FROM product_stock WHERE product_id='$product_id'");
    if (mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "INSERT INTO product_stock (product_id, stock_count) VALUES ('$product_id', 0)");
    }

    if ($action === "increase") {
        $sql = "UPDATE product_stock SET stock_count = stock_count + $qty WHERE product_id='$product_id'";
    } elseif ($action === "decrease") {
        $sql = "UPDATE product_stock SET stock_count = GREATEST(0, stock_count - $qty) WHERE product_id='$product_id'";
    } else {
        echo json_encode(["status"=>"error","message"=>"invalid action"]);
        exit;
    }

    if (mysqli_query($conn, $sql)) {
        echo json_encode(["status"=>"success"]);
    } else {
        echo json_encode(["status"=>"error","message"=>mysqli_error($conn)]);
    }
    exit;
}

// ================= INVALID METHOD =================
else {
    echo json_encode(["status"=>"error","message"=>"Invalid request method"]);
}
?>