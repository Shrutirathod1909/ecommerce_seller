<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "db.php";

/* -------- GET INPUT -------- */
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);
if (!$input) $input = $_POST;

/* -------- SAFE INPUT -------- */
$action = trim($input['action'] ?? $_GET['action'] ?? '');
$vendor_id = intval($input['vendor_id'] ?? $_GET['vendor_id'] ?? 0);

/* ================= SHOW PRODUCTS ================= */
if ($action == "show") {

    $status = $input['status'] ?? $_GET['status'] ?? "approved";

    if ($status == "approved") $where = "p.verified=1 AND p.rejected=0 AND p.hide='N'";
    else if ($status == "pending") $where = "p.verified=0 AND p.rejected=0 AND p.hide='N'";
    else if ($status == "rejected") $where = "p.rejected=1 AND p.hide='N'";
    else if ($status == "restore") $where = "p.hide='Y'";
    else $where = "1";

    if (!empty($vendor_id)) {
        $where .= " AND p.vendor_id = $vendor_id";
    }

    $sql = "SELECT 
                p.productid,
                p.item_name,
                p.subtitle,
                p.category,
                p.image1,
                MAX(v.sku_code) as sku,
                COALESCE(SUM(v.qty),0) as stock,
                COALESCE(MAX(v.sale_price),0) as sale_price
            FROM products p
            LEFT JOIN product_detail_description v 
            ON p.productid = v.product_id
            WHERE $where
            GROUP BY p.productid
            ORDER BY p.productid DESC";

    $result = $conn->query($sql);
    $products = [];

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['image1'])) {
            $row['image1'] = UPLOAD_URL . $row['image1'];
        }
        $products[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $products]);
    exit;
}

/* ================= GET SINGLE PRODUCT ================= */
if ($action == "get_product") {

    // 🔥 FIX: accept both names
    $productid = intval($input['product_id'] ?? $input['productid'] ?? $_GET['productid'] ?? 0);

    if (empty($productid)) {
        echo json_encode(["status" => "error", "message" => "Product ID Missing"]);
        exit;
    }

    // 🔥 FIX: vendor optional
    if ($vendor_id > 0) {
        $stmt = $conn->prepare("
            SELECT productid, item_name, subtitle, category, image1, vendor_id
            FROM products 
            WHERE productid=? AND vendor_id=?
        ");
        $stmt->bind_param("ii", $productid, $vendor_id);
    } else {
        $stmt = $conn->prepare("
            SELECT productid, item_name, subtitle, category, image1, vendor_id
            FROM products 
            WHERE productid=?
        ");
        $stmt->bind_param("i", $productid);
    }

    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        echo json_encode([
            "status" => "error",
            "message" => "Product not found",
            "debug" => [
                "productid" => $productid,
                "vendor_id" => $vendor_id
            ]
        ]);
        exit;
    }

    if (!empty($product['image1'])) {
        $product['image1'] = UPLOAD_URL . $product['image1'];
    }

    /* ===== VARIANTS ===== */
    $stmt2 = $conn->prepare("
        SELECT id, colour, size, sku_code, sale_price, qty
        FROM product_detail_description 
        WHERE product_id=?
    ");
    $stmt2->bind_param("i", $productid);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    $variants = [];
    while ($row = $res2->fetch_assoc()) {
        $variants[] = [
            "id" => $row["id"],
            "colour" => $row["colour"],
            "size" => $row["size"],
            "sku_code" => $row["sku_code"],
            "sale_price" => $row["sale_price"],
            "qty" => $row["qty"]
        ];
    }

    echo json_encode([
        "status" => "success",
        "product" => $product,
        "variants" => $variants
    ]);
    exit;
}

/* ================= ADD / UPDATE PRODUCT ================= */
if ($action == "add" || $action == "update_product") {

    // 🔥 FIX: accept both
    $productid = intval($input['product_id'] ?? $input['productid'] ?? 0);

    $item_name = $input['item_name'] ?? '';
    $subtitle = $input['subtitle'] ?? '';
    $category = $input['category'] ?? '';

    if (empty($item_name) || empty($vendor_id)) {
        echo json_encode(["status" => "error", "message" => "Required fields missing"]);
        exit;
    }

    if ($action == "add") {

        $stmt = $conn->prepare("
            INSERT INTO products
            (item_name, subtitle, category, verified, rejected, hide, vendor_id)
            VALUES (?, ?, ?, 0, 0, 'N', ?)
        ");
        $stmt->bind_param("sssi", $item_name, $subtitle, $category, $vendor_id);
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "productid" => $conn->insert_id
        ]);

    } else {

        $stmt = $conn->prepare("
            UPDATE products
            SET item_name=?, subtitle=?, category=?
            WHERE productid=? AND vendor_id=?
        ");
        $stmt->bind_param("sssii", $item_name, $subtitle, $category, $productid, $vendor_id);
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "productid" => $productid
        ]);
    }

    exit;
}

/* ================= UPDATE VARIANTS ================= */
if ($action == "update_variants") {

    $product_id = intval($input["product_id"] ?? 0);
    $variants = $input["variants"] ?? [];

    if ($product_id == 0) {
        echo json_encode(["status" => "error", "message" => "Product ID Missing"]);
        exit;
    }

    // 🔥 DELETE OLD
    $del = $conn->prepare("DELETE FROM product_detail_description WHERE product_id=?");
    $del->bind_param("i", $product_id);
    $del->execute();

    // 🔥 INSERT NEW
    foreach ($variants as $v) {

        $colour = trim($v["colour"] ?? '');
        $size = trim($v["size"] ?? '');
        $sku = trim($v["sku_code"] ?? '');
        $price = floatval($v["sale_price"] ?? 0);
        $qty = intval($v["qty"] ?? 0);

        if ($colour == '' || $size == '' || $sku == '') continue;

        $stmt = $conn->prepare("
            INSERT INTO product_detail_description
            (product_id, colour, size, sku_code, sale_price, qty)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("isssdi", $product_id, $colour, $size, $sku, $price, $qty);
        $stmt->execute();
    }

    echo json_encode([
        "status" => "success",
        "message" => "Variants Saved Successfully"
    ]);
    exit;
}

/* ================= DELETE / RESTORE ================= */
if ($action == "delete" || $action == "restore") {

    $productid = intval($input['productid'] ?? 0);

    if (empty($productid)) {
        echo json_encode(["status" => "error", "message" => "Product ID Missing"]);
        exit;
    }

    $hide = ($action == "delete") ? "Y" : "N";

    $stmt = $conn->prepare("
        UPDATE products 
        SET hide=? 
        WHERE productid=? AND vendor_id=?
    ");
    $stmt->bind_param("sii", $hide, $productid, $vendor_id);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Done"]);
    exit;
}

/* ================= DEFAULT ================= */
echo json_encode([
    "status" => "error",
    "message" => "Invalid Action"
]);

$conn->close();
?>