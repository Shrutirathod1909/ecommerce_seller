<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php";

$MAX_STOCK = 1000000;

function normalizeImageUrl($image) {
    if (empty($image)) return '';
    $image = ltrim($image, '/');
    return IMGPATH . $image;
}

// ================= GET =================
if ($_SERVER['REQUEST_METHOD'] === "GET") {

    $vendor_id = $_GET['vendor_id'] ?? '';

    if (empty($vendor_id)) {
        echo json_encode(["status"=>"error","message"=>"vendor_id required"]);
        exit;
    }

    // ================= SUMMARY (FIXED) =================
    $count_sql = "
        SELECT 
            COUNT(DISTINCT p.productid) AS total_products,

            -- ✅ TOTAL STOCK (IMPORTANT FIX)
            COALESCE(SUM(s.stock_sum),0) AS total_stock,

            -- out of stock
            SUM(IF(COALESCE(s.stock_sum,0)=0,1,0)) AS out_of_stock,

            -- low stock (1-5)
            SUM(IF(COALESCE(s.stock_sum,0) BETWEEN 1 AND 5,1,0)) AS low_stock

        FROM products p

        LEFT JOIN (
            SELECT 
                product_id, 
                SUM(stock_count) AS stock_sum
            FROM product_stock
            GROUP BY product_id
        ) s ON p.productid = s.product_id

        WHERE p.vendor_id='$vendor_id'
        AND p.hide='N'
        AND p.verified='1'
    ";

    $count_result = mysqli_query($conn, $count_sql);
    $count_data = mysqli_fetch_assoc($count_result);

    // ================= PRODUCT LIST =================
    $sql = "
        SELECT 
            p.productid,
            p.item_name,
            p.image1,
            MIN(s.skucode) AS skucode,
            COALESCE(SUM(s.stock_count),0) AS stock_count
        FROM products p
        LEFT JOIN product_stock s ON p.productid = s.product_id
        WHERE p.vendor_id='$vendor_id'
        AND p.hide='N'
        AND p.verified='1'
        GROUP BY p.productid
        ORDER BY p.productid DESC
    ";

    $result = mysqli_query($conn, $sql);

    $data = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $stock = max(0, min((int)$row['stock_count'], $MAX_STOCK));

        $data[] = [
            "productid"   => $row['productid'],
            "item_name"   => $row['item_name'],
            "skucode"     => $row['skucode'],
            "stock_count" => (string)$stock,
            "image"       => normalizeImageUrl($row['image1']),
        ];
    }

    echo json_encode([
        "status" => "success",

        // ✅ FIXED OUTPUT (NOW FLUTTER WILL WORK)
        "total_products" => (int)$count_data['total_products'],
        "total_stock"    => (int)$count_data['total_stock'],
        "out_of_stock"   => (int)$count_data['out_of_stock'],
        "low_stock"       => (int)$count_data['low_stock'],

        "data" => $data
    ]);

    exit;
}

// ================= POST =================
elseif ($_SERVER['REQUEST_METHOD'] === "POST") {

    $input = json_decode(file_get_contents("php://input"), true);

    $action  = strtolower(trim($input['action'] ?? ''));
    $skucode = trim($input['skucode'] ?? '');
    $qty     = (int)($input['qty'] ?? 0);

    if (!in_array($action, ["increase", "decrease"])) {
        echo json_encode(["status"=>"error","message"=>"Invalid action"]);
        exit;
    }

    if (empty($skucode) || $qty <= 0) {
        echo json_encode(["status"=>"error","message"=>"Invalid input"]);
        exit;
    }

    mysqli_begin_transaction($conn);

    try {

        $res = mysqli_query($conn, "
            SELECT stock_count 
            FROM product_stock 
            WHERE skucode='$skucode' 
            FOR UPDATE
        ");

        if (mysqli_num_rows($res) == 0) {
            throw new Exception("SKU not found");
        }

        $row = mysqli_fetch_assoc($res);
        $current = (int)$row['stock_count'];

        if ($action === "increase") {
            $new_stock = $current + $qty;
        } else {
            if ($current < $qty) throw new Exception("Not enough stock");
            $new_stock = $current - $qty;
        }

        mysqli_query($conn, "
            UPDATE product_stock 
            SET stock_count='$new_stock' 
            WHERE skucode='$skucode'
        ");

        mysqli_commit($conn);

        echo json_encode(["status"=>"success"]);

    } catch(Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
    }

    exit;
}
?>