<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$action = $data['action'] ?? '';
$productid = intval($data['productid'] ?? 0);

/* ================= GET ================= */
if ($action == "get") {

    if ($productid == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Product ID missing"
        ]);
        exit;
    }

    $sql = "
        SELECT 
            p.weight,
            p.height,
            p.width,
            p.material,
            p.warranty_desc,

            d.size,
            d.colour AS color,
            d.sku_code AS sku,
            d.barcode,
            d.hsn,
            d.mrp_price,
            d.sale_price,
            d.cad_price

        FROM products p
        LEFT JOIN product_detail_description d 
            ON p.productid = d.product_id

        WHERE p.productid = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productid);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode([
        "status" => $row ? "success" : "error",
        "data" => $row ?? null
    ]);

    exit;
}

/* ================= UPDATE ================= */

// INPUTS
$weight = $data['weight'] ?? '';
$height = $data['height'] ?? '';
$width = $data['width'] ?? '';
$material = $data['material'] ?? '';
$warranty_desc = $data['warranty'] ?? '';

$size = $data['size'] ?? '';
$color = $data['color'] ?? '';
$sku_code = $data['sku'] ?? '';
$barcode = $data['barcode'] ?? '';
$hsn = $data['hsn'] ?? '';

$mrp_price = floatval($data['mrp_price'] ?? 0);
$sale_price = floatval($data['sale_price'] ?? 0);
$cad_price = floatval($data['cad_price'] ?? 0);

/* ================= GET CATEGORY ================= */

$primary_category_name = '';

$getCategory = $conn->prepare("
    SELECT primary_categories_name 
    FROM products 
    WHERE productid=? 
    LIMIT 1
");

$getCategory->bind_param("i", $productid);
$getCategory->execute();
$resCat = $getCategory->get_result();

if ($rowCat = $resCat->fetch_assoc()) {
    $primary_category_name = strtolower(trim($rowCat['primary_categories_name']));
}

$getCategory->close();

/* ================= CAD LOGIC ================= */

$isCadProduct = (strpos($primary_category_name, 'cad') !== false);

if ($isCadProduct) {
    // ✅ MAIN FIX
    $mrp_price  = $cad_price;
    $sale_price = $cad_price;
} else {
    // Normal product
    $mrp_price  = $mrp_price;
    $sale_price = $sale_price;
}

/* ================= UPDATE PRODUCTS ================= */

$sql1 = "UPDATE products SET
    weight=?,
    height=?,
    width=?,
    material=?,
    warranty_desc=?,
    modified_on=NOW()
WHERE productid=?";

$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param(
    "sssssi",
    $weight,
    $height,
    $width,
    $material,
    $warranty_desc,
    $productid
);
$result1 = $stmt1->execute();

/* ================= UPDATE DETAILS ================= */

$sql2 = "UPDATE product_detail_description SET
    size=?,
    colour=?,
    sku_code=?,
    barcode=?,
    hsn=?,
    mrp_price=?,
    sale_price=?,
    cad_price=?,
    modified_on=NOW()
WHERE product_id=?";

$stmt2 = $conn->prepare($sql2);

$stmt2->bind_param(
    "sssssdddi",
    $size,
    $color,
    $sku_code,
    $barcode,
    $hsn,
    $mrp_price,
    $sale_price,
    $cad_price,
    $productid
);

$result2 = $stmt2->execute();

/* ================= RESPONSE ================= */

if ($result1 && $result2) {
    echo json_encode([
        "status" => "success",
        "message" => "Updated Successfully",
        "is_cad" => $isCadProduct,
        "mrp_price" => $mrp_price,
        "sale_price" => $sale_price
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
}

$conn->close();
?>