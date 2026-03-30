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
$company_id = intval($input['company_id'] ?? $_GET['company_id'] ?? 0);

/* -------- HELPER: NORMALIZE IMAGE URL -------- */
function normalizeImageUrl($image) {
    if (empty($image)) return '';

    $image = ltrim($image, '/');

    // Product gallery images
    if (strpos($image, 'productgallery/') === 0) {
        return IMGPATH . $image;
    }

    // Uploads folder images
    if (strpos($image, 'uploads/') === 0) {
        return UPLOAD_URL . substr($image, 8); // remove 'uploads/' if already present
    }

    // Default fallback
    return UPLOAD_URL . $image;
}


/* ================= SHOW PRODUCTS ================= */
if ($action == "show") {
    $status = $input['status'] ?? $_GET['status'] ?? "approved";

    // Build WHERE clause
    if ($status == "approved") $where = "p.verified=1 AND p.rejected=0 AND p.hide='N'";
    else if ($status == "pending") $where = "p.verified=0 AND p.rejected=0 AND p.hide='N'";
    else if ($status == "rejected") $where = "p.rejected=1 AND p.hide='N'";
    else if ($status == "restore") $where = "p.hide='Y'";
    else $where = "1";

    if (!empty($vendor_id)) $where .= " AND p.vendor_id = $vendor_id";

    // Fetch products with aggregated stock and sale_price
    $sql = "
    SELECT 
        p.productid,
        p.item_name,
        p.subtitle,
        p.category,
        p.image1,
        MAX(pdd.sku_code) AS sku,
        COALESCE(SUM(CAST(pdd.qty AS UNSIGNED)), 0) AS stock,
        COALESCE(MAX(pdd.sale_price), 0) AS sale_price
    FROM products p
    LEFT JOIN product_detail_description pdd
        ON p.productid = pdd.product_id
    WHERE $where
    GROUP BY p.productid
    ORDER BY p.productid DESC
    ";

    $result = $conn->query($sql);
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $row['image1'] = normalizeImageUrl($row['image1']);
        $products[] = $row;

        // Update product_stock table
        $productId = $row['productid'];
        $stockCount = $row['stock'];
        $sku = $row['sku'];

        $check = $conn->query("SELECT id FROM product_stock WHERE product_id = $productId");
        if ($check->num_rows > 0) {
            // Update existing stock
            $conn->query("UPDATE product_stock 
                          SET stock_count = $stockCount, skucode='$sku' 
                          WHERE product_id = $productId");
        } else {
            // Insert new stock record
            $conn->query("INSERT INTO product_stock (product_id, stock_count, skucode) 
                          VALUES ($productId, $stockCount, '$sku')");
        }
    }

    echo json_encode(["status" => "success", "data" => $products]);
    exit;
}
/* ================= SHOW VARIANTS ================= */
if ($action == "show_variants") {

    $product_id = intval($input['product_id'] ?? $_GET['product_id'] ?? 0);

    if ($product_id == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Product ID Missing"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            id,
            IFNULL(colour,'') as colour,
            IFNULL(size,'') as size,
            IFNULL(sku_code,'') as sku_code,
            IFNULL(sale_price,0) as sale_price,
            IFNULL(qty,0) as qty
        FROM product_detail_description
        WHERE product_id=?
        ORDER BY id ASC
    ");

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

    exit;
}


/* ================= GET SINGLE PRODUCT ================= */
if ($action == "get_product") {
    $productid = intval($input['product_id'] ?? $input['productid'] ?? $_GET['productid'] ?? 0);

    if (empty($productid)) {
        echo json_encode(["status" => "error", "message" => "Product ID Missing"]);
        exit;
    }

    // Always fetch all fields
    $stmt = $conn->prepare("
        SELECT 
            productid,
            item_name,
            subtitle,
            category,
            subcategory,
            child_category,
            gender,
            payment_method,
            gst_type,
         unit,
            weight,
            hsn,
            country_of_origin,
            product_description,
            image1,
            vendor_id,
            company_id
        FROM products 
        WHERE productid=?
        ");


    $stmt->bind_param("i", $productid);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        echo json_encode(["status" => "error", "message" => "Product not found"]);
        exit;
    }

    $product['image1'] = normalizeImageUrl($product['image1']);

    // Fetch variants
    $stmt2 = $conn->prepare("
        SELECT id, colour, size, sku_code, sale_price, qty
        FROM product_detail_description 
        WHERE product_id=?
        ORDER BY id ASC
    ");
    $stmt2->bind_param("i", $productid);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    $variants = [];
    while ($row = $res2->fetch_assoc()) {
        $variants[] = $row;
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

    /* -------- COMMON INPUT -------- */
   $productid = intval($input['productid'] ?? $input['product_id'] ?? 0);

    $item_name = trim($input['item_name'] ?? '');
    $subtitle = trim($input['subtitle'] ?? '');
    $category = trim($input['category'] ?? '');

    $subcategory = $input['subcategory'] ?? '';
    $child_category = $input['child_category'] ?? '';
    $gender = $input['gender'] ?? '';
    $payment_method = $input['payment_method'] ?? '';
    $gst_type = $input['gst_type'] ?? '';

    $weight = $input['weight'] ?? '';
    $hsn = $input['hsn'] ?? '';
    $country_of_origin = $input['country_of_origin'] ?? '';
    $product_description = $input['product_description'] ?? '';

    $vendor_id = intval($input['vendor_id'] ?? 0);
    $company_id = intval($input['company_id'] ?? 0);

    /* -------- VALIDATION -------- */
    if ($item_name == '') {
        echo json_encode([
            "status"=>"error",
            "message"=>"Product name required"
        ]);
        exit;
    }

    /* ================= ADD ================= */
    if ($action == "add") {

        $stmt = $conn->prepare("
            INSERT INTO products
            (
                item_name, subtitle, category, subcategory, child_category,
                gender, payment_method, gst_type,
                weight, hsn, country_of_origin,
                product_description,
                verified, rejected, hide,
                vendor_id, company_id, created_on
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'N', ?, ?, NOW())
        ");

        if (!$stmt) {
            echo json_encode([
                "status"=>"error",
                "message"=>"Prepare failed",
                "error"=>$conn->error
            ]);
            exit;
        }

       $stmt->bind_param(
    "ssssssssssssii",
    $item_name,
    $subtitle,
    $category,
    $subcategory,
    $child_category,
    $gender,
    $payment_method,
    $gst_type,
    $weight,
    $hsn,
    $country_of_origin,
    $product_description,
    $vendor_id,
    $company_id
);

        if (!$stmt->execute()) {
            echo json_encode([
                "status"=>"error",
                "message"=>"Execute failed",
                "error"=>$stmt->error
            ]);
            exit;
        }

        $newId = mysqli_insert_id($conn);

        if ($newId == 0) {
            $res = $conn->query("SELECT MAX(productid) as last_id FROM products");
            $row = $res->fetch_assoc();
            $newId = $row['last_id'] ?? 0;
        }

        echo json_encode([
            "status"=>"success",
            "productid"=>$newId
        ]);
        exit;
    }

    /* ================= UPDATE ================= */
if ($action == "update_product") {

    $productid = intval($input['productid'] ?? $input['product_id'] ?? 0);

    if ($productid <= 0) {
        echo json_encode([
            "status"=>"error",
            "message"=>"Invalid product ID"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE products SET
            item_name=?,
            subtitle=?,
            category=?,
            subcategory=?,
            child_category=?,
            gender=?,
            payment_method=?,
            gst_type=?,
            weight=?,
            hsn=?,
            country_of_origin=?,
            product_description=?,
            modified_on=NOW()
        WHERE productid=? AND vendor_id=? AND company_id=?
    ");

    $stmt->bind_param(
        "ssssssssssssiii",
        $item_name,
        $subtitle,
        $category,
        $subcategory,
        $child_category,
        $gender,
        $payment_method,
        $gst_type,
        $weight,
        $hsn,
        $country_of_origin,
        $product_description,
        $productid,
        $vendor_id,
        $company_id
    );

    $stmt->execute();

    // ✅ Fetch the updated product data
    $stmt2 = $conn->prepare("
        SELECT *
        FROM products
        WHERE productid=? AND vendor_id=? AND company_id=?
    ");
    $stmt2->bind_param("iii", $productid, $vendor_id, $company_id);
    $stmt2->execute();
    $product = $stmt2->get_result()->fetch_assoc();

    echo json_encode([
        "status"=>"success",
        "productid"=>$productid,
        "product"=>$product
    ]);
    exit;
}
}

/* ================= ADD VARIANTS (NO DELETE) ================= */
if ($action == "update_variants") {

    $product_id = intval($input["product_id"] ?? 0);
    $variants = $input["variants"] ?? [];

    if ($product_id == 0) {
        echo json_encode([
            "status"=>"error",
            "message"=>"Product ID Missing"
        ]);
        exit;
    }

    if (empty($variants)) {
        echo json_encode([
            "status"=>"error",
            "message"=>"No variants provided"
        ]);
        exit;
    }

    foreach ($variants as $v) {

        $colour = trim($v["colour"] ?? '');
        $size   = trim($v["size"] ?? '');
        $sku    = trim($v["sku_code"] ?? '');
        $price  = floatval($v["sale_price"] ?? 0);
        $qty    = $v["qty"] ?? '';

        // ✅ SKU must
        if (empty($sku)) continue;

        // ✅ DUPLICATE CHECK (same SKU not allowed)
        $check = $conn->prepare("
            SELECT id FROM product_detail_description 
            WHERE product_id=? AND sku_code=?
        ");
        $check->bind_param("is", $product_id, $sku);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            continue; // skip duplicate SKU
        }

        // ✅ INSERT NEW VARIANT
        $stmt = $conn->prepare("
            INSERT INTO product_detail_description
            (product_id, colour, size, sku_code, sale_price, qty)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssss",
            $product_id,
            $colour,
            $size,
            $sku,
            $price,
            $qty
        );

        if (!$stmt->execute()) {
            echo json_encode([
                "status"=>"error",
                "message"=>$stmt->error
            ]);
            exit;
        }
    }

    echo json_encode([
        "status"=>"success",
        "message"=>"Variants Added Successfully"
    ]);
    exit;
}

/* ================= APPROVE PRODUCT + ADD STOCK ================= */
if ($action == "approve_product") {

    $productid = intval($input['productid'] ?? 0);

    if ($productid <= 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid product ID"
        ]);
        exit;
    }

    // ✅ Approve the product
    $stmt = $conn->prepare("
        UPDATE products 
        SET verified=1, rejected=0 
        WHERE productid=?
    ");
    $stmt->bind_param("i", $productid);
    $stmt->execute();

    // ✅ Fetch variants safely
    $stmtVar = $conn->prepare("
        SELECT sku_code, COALESCE(qty, 0) AS qty, COALESCE(size,'') AS size
        FROM product_detail_description 
        WHERE product_id=?
    ");
    $stmtVar->bind_param("i", $productid);
    $stmtVar->execute();
    $variants = $stmtVar->get_result();

    if ($variants->num_rows == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "No variants found"
        ]);
        exit;
    }

    while ($v = $variants->fetch_assoc()) {

        // ✅ Extract SKU, Size, and Qty correctly
        $sku  = trim($v['sku_code']);
        $size = trim($v['size']);
        $qty  = (int)$v['qty'];

        if (empty($sku) || $qty <= 0) continue;

        // ✅ Check if stock already exists
        $checkStmt = $conn->prepare("
            SELECT id FROM product_stock
            WHERE product_id=? AND skucode=?
        ");
        $checkStmt->bind_param("is", $productid, $sku);
        $checkStmt->execute();
        $check = $checkStmt->get_result();

        if ($check->num_rows == 0) {
            $stmt2 = $conn->prepare("
                INSERT INTO product_stock
                (product_id, skucode, size, stock_count, created_on, active)
                VALUES (?, ?, ?, ?, NOW(), 1)
            ");
            $stmt2->bind_param("issi", $productid, $sku, $size, $qty);
            $stmt2->execute();
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "Product Approved & Stock Added"
    ]);
    exit;
}
/* ================= DELETE / RESTORE ================= */
if ($action == "delete" || $action == "restore") {
    $productid = intval($input['productid'] ?? 0);
    if (empty($productid)) {
        echo json_encode(["status"=>"error","message"=>"Product ID Missing"]);
        exit;
    }

    $hide = ($action == "delete") ? "Y" : "N";
    $stmt = $conn->prepare("UPDATE products SET hide=? WHERE productid=? AND vendor_id=?");
    $stmt->bind_param("sii", $hide, $productid, $vendor_id);
    $stmt->execute();

    echo json_encode(["status"=>"success","message"=>"Done"]);
    exit;
}

/* ================= DEFAULT ================= */
echo json_encode(["status"=>"error","message"=>"Invalid Action"]);
$conn->close();
?>