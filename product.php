<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once "db.php";

/* -------- GET INPUT -------- */
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);
if (!$input) $input = $_POST;

/* -------- VENDOR ID -------- */
$vendor_id = $input['vendor_id'] ?? $_GET['vendor_id'] ?? 0;
if (!$vendor_id || !is_numeric($vendor_id)) {
    echo json_encode(["status" => "error", "message" => "vendor_id required"]);
    exit;
}

/* -------- ACTION -------- */
$action = $input['action'] ?? $_GET['action'] ?? '';

/* ================= SHOW PRODUCTS ================= */
if ($action == "show") {
    $status = $input['status'] ?? $_GET['status'] ?? "approved";

    if ($status == "approved") $where = "p.verified='1' AND p.rejected=0 AND p.hide='N'";
    else if ($status == "pending") $where = "p.verified='0' AND p.rejected=0 AND p.hide='N'";
    else if ($status == "rejected") $where = "p.rejected=1 AND p.hide='N'";
    else if ($status == "restore") $where = "p.hide='Y'";
    else $where = "1";

    $sql = "SELECT 
                p.productid,
                p.item_name,
                p.subtitle,
                p.category,
                p.subcategory,
                p.child_category,
                p.gender,
                p.payment_method,
                p.country_of_origin,
                p.product_description,
                p.image1,
                MAX(v.sku_code) as sku,
                COALESCE(SUM(v.qty),0) as stock,
                COALESCE(MAX(v.sale_price),0) as sale_price
            FROM products p
            LEFT JOIN product_detail_description v 
            ON p.productid = v.product_id
            WHERE $where AND p.vendor_id=?
            GROUP BY 
                p.productid,
                p.item_name,
                p.subtitle,
                p.category,
                p.subcategory,
                p.child_category,
                p.gender,
                p.payment_method,
                p.country_of_origin,
                p.product_description,
                p.image1
            ORDER BY p.productid DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['image1'])) {
            if (file_exists("uploads/" . $row['image1'])) $row['image1'] = UPLOAD_URL . $row['image1'];
            else $row['image1'] = IMGPATH . $row['image1'];
        }
        $products[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $products]);
    $stmt->close();
    exit;
}

/* ================= SHOW VARIANTS ================= */
    
if ($action == "show_variants") {
    $product_id = $input['product_id'] ?? $_GET['product_id'] ?? 0;

    if (!$product_id) {
        echo json_encode(["status" => "error", "message" => "Product ID Missing"]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            product_id,
            colour AS color,
            size,
            hsn,
            sale_price,
            sku_code,
            weight,
            qty
        FROM product_detail_description 
        WHERE product_id=?"
    );

    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => $conn->error]);
        exit;
    }

    // ✅ VERY IMPORTANT
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $variants = [];
    while ($row = $result->fetch_assoc()) {
        $variants[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $variants
    ]);

    $stmt->close();
    exit;
}

/* ================= ADD PRODUCT ================= */
if ($action == "add") {
    $item_name = $input['item_name'] ?? '';
    $subtitle = $input['subtitle'] ?? '';
    $category = $input['category'] ?? '';
    $subcategory = $input['subcategory'] ?? '';
    $child_category = $input['child_category'] ?? '';
    $gender = $input['gender'] ?? '';
    $payment_method = $input['payment_method'] ?? '';
    $country_of_origin = $input['country_of_origin'] ?? '';
    $description = $input['description'] ?? '';

    $verified = '0'; // pending
    $rejected = 0;
    $hide = 'N';

    $stmt = $conn->prepare("
        INSERT INTO products
        (vendor_id, item_name, subtitle, category, subcategory, child_category, gender, payment_method, country_of_origin, product_description, verified, rejected, hide)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "issssssssssss",
        $vendor_id, $item_name, $subtitle, $category, $subcategory,
        $child_category, $gender, $payment_method, $country_of_origin,
        $description, $verified, $rejected, $hide
    );

    if ($stmt->execute()) {
        // return productid immediately
        $product_id = $conn->insert_id;
        echo json_encode(["status" => "success", "productid" => $product_id, "message" => "Product Created"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
    $stmt->close();
    exit;
}

/* ================= DELETE / RESTORE ================= */
if ($action == "delete" || $action == "restore") {
    $productid = $input['productid'] ?? 0;
    if (!$productid) {
        echo json_encode(["status" => "error", "message" => "Product ID Missing"]);
        exit;
    }

    if ($action == "delete") {
        $stmt = $conn->prepare("UPDATE products SET hide='Y' WHERE productid=? AND vendor_id=? AND verified='0' AND rejected=0");
    } else {
        $stmt = $conn->prepare("UPDATE products SET hide='N' WHERE productid=? AND vendor_id=?");
    }

    $stmt->bind_param("ii", $productid, $vendor_id);
    if ($stmt->execute()) echo json_encode(["status" => "success", "message" => "Action Completed"]);
    else echo json_encode(["status" => "error", "message" => $conn->error]);
    $stmt->close();
    exit;
}

/* ================= ADD VARIANTS ================= */
if ($action == "add_variants") {
    $product_id = intval($input["product_id"] ?? 0);
    $variants = $input["variants"] ?? [];

    if (!$product_id || empty($variants)) {
        echo json_encode(["status" => "error", "message" => "Invalid product ID or empty variants"]);
        exit;
    }

    // Security check
    $check = $conn->prepare("SELECT productid FROM products WHERE productid=? AND vendor_id=?");
    $check->bind_param("ii", $product_id, $vendor_id);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Unauthorized product"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO product_detail_description
        (product_id, colour, size, hsn, sale_price, sku_code, weight, qty)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => $conn->error]);
        exit;
    }

    foreach ($variants as $v) {
        $color = $v["color"] ?? '';
        $size = $v["size"] ?? '';
        $hsn = $v["hsn"] ?? '';
        $sku = $v["sku"] ?? '';
        $price = floatval($v["price"] ?? 0);
        $weight = $v["weight"] ?? '';
        $qty = intval($v["stock"] ?? 0);

        $stmt->bind_param("issdsssi", $product_id, $color, $size, $hsn, $price, $sku, $weight, $qty);
        if (!$stmt->execute()) {
            echo json_encode(["status" => "error", "message" => "Failed to insert variant: " . $stmt->error]);
            exit;
        }
    }

    $stmt->close();
    echo json_encode(["status" => "success", "message" => "Variants saved successfully"]);
    exit;
}

/* ================= INVALID ACTION ================= */
echo json_encode(["status" => "error", "message" => "Invalid Action"]);
$conn->close();
?>