<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include "db.php";

// ================= IMAGE FUNCTION =================
function normalizeImageUrl($image) {
    if (empty($image)) return '';

    $image = ltrim($image, '/');

    if (strpos($image, 'productgallery/') === 0) {
        return IMGPATH . $image;
    }

    return IMGPATH . $image;
}

// ================= GET REQUEST =================
if ($_SERVER['REQUEST_METHOD'] === "GET") {

    $vendor_id = mysqli_real_escape_string($conn, $_GET['vendor_id'] ?? '');
    if (empty($vendor_id)) {
        echo json_encode(["status"=>"error","message"=>"vendor_id required"]);
        exit;
    }

    // Ensure stock rows exist for all products
    $allProducts = mysqli_query($conn, "SELECT productid FROM products WHERE vendor_id='$vendor_id'");
    while ($p = mysqli_fetch_assoc($allProducts)) {
        $product_id = $p['productid'];
        mysqli_query($conn, "
            INSERT INTO product_stock (product_id, stock_count)
            VALUES ('$product_id', 0)
            ON DUPLICATE KEY UPDATE product_id=product_id
        ");
    }

    // ================= COUNT SUMMARY =================
    $count_sql = "
        SELECT 
            COUNT(DISTINCT p.productid) AS total_products,
            SUM(COALESCE(s.stock_sum,0) > 50) AS total_stock,
            SUM(COALESCE(s.stock_sum,0) = 0) AS out_of_stock,
            SUM(COALESCE(s.stock_sum,0) BETWEEN 1 AND 5) AS low_stock
        FROM products p
        LEFT JOIN (
            SELECT product_id, SUM(stock_count) AS stock_sum
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
            COALESCE(SUM(s.stock_count),0) AS stock_count
        FROM products p
        LEFT JOIN product_stock s ON p.productid = s.product_id
        WHERE p.vendor_id='$vendor_id'
        AND p.hide='N'
        AND p.verified='1'
        GROUP BY p.productid, p.item_name, p.image1
        ORDER BY p.productid DESC
    ";

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

// ================= POST REQUEST (UPDATE STOCK) =================
elseif ($_SERVER['REQUEST_METHOD'] === "POST") {

    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);

    if ($product_id == 0) {
        echo json_encode(["status"=>"error","message"=>"product_id required"]);
        exit;
    }

    // Ensure stock row exists
    mysqli_query($conn, "
        INSERT INTO product_stock (product_id, stock_count)
        VALUES ('$product_id', 0)
        ON DUPLICATE KEY UPDATE product_id=product_id
    ");

    if ($action === "increase") {
        $sql = "
            UPDATE product_stock 
            SET stock_count = stock_count + $qty
            WHERE product_id='$product_id'
        ";
    } elseif ($action === "decrease") {
        $sql = "
            UPDATE product_stock 
            SET stock_count = GREATEST(0, stock_count - $qty)
            WHERE product_id='$product_id'
        ";
    } else {
        echo json_encode(["status"=>"error","message"=>"invalid action"]);
        exit;
    }

    if (mysqli_query($conn, $sql)) {
        // Return updated stock
        $res = mysqli_query($conn, "
            SELECT SUM(stock_count) AS stock_count 
            FROM product_stock 
            WHERE product_id='$product_id'
        ");
        $row = mysqli_fetch_assoc($res);

        echo json_encode([
            "status" => "success",
            "product_id" => $product_id,
            "stock_count" => (int)$row['stock_count']
        ]);

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