<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';


/* ==================== SHOW PRODUCT ==================== */

if($action == "show") {
    $status = $data['status'] ?? 'approved';

    if($status == "approved") $where = "verified=1 AND rejected=0 AND hide='N'";
    else if($status == "pending") $where = "verified=0 AND rejected=0 AND hide='N'";
    else if($status == "rejected") $where = "rejected=1 AND hide='N'";
    else if($status == "restore") $where = "hide='Y'";
    else $where = "1";

    // Select products and calculate total stock from variants
    $sql = "SELECT p.productid, p.sku, p.item_name, p.subtitle, p.category, p.image1,
                   COALESCE(SUM(v.qty),0) as no_of_items,
                   COALESCE(MAX(v.sale_price),0) as sale_price
            FROM products p
            LEFT JOIN product_detail_description v ON p.productid = v.product_id
            WHERE $where
            GROUP BY p.productid
            ORDER BY p.productid DESC";
    
    $result = $conn->query($sql);

    $products = [];
    while($row = $result->fetch_assoc()){
        $products[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $products
    ]);
    exit;
}



/* ==================== SHOW VARIANTS ==================== */
if($action == "show_variants") {
    $product_id = $data['product_id'] ?? 0;
    $sql = "SELECT * FROM product_detail_description WHERE product_id=? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $variants = [];
    while($row = $result->fetch_assoc()){
        $variants[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $variants
    ]);
    $stmt->close();
    exit;
}

/* ==================== ADD PRODUCT ==================== */
if($action == "add") {
    $item_name = $data['item_name'] ?? '';
    $subtitle = $data['subtitle'] ?? '';
    $category = $data['category'] ?? '';
    $subcategory = $data['subcategory'] ?? '';
    $child_category = $data['child_category'] ?? '';
    $gender = $data['gender'] ?? '';
    $payment_method = $data['payment_method'] ?? '';
    $country_of_origin = $data['country_of_origin'] ?? '';
    $weight = $data['weight'] ?? '';
    $hsn = $data['hsn'] ?? '';
    $gst_type = $data['gst_type'] ?? '';
    $product_description = $data['product_description'] ?? '';
    $verified = 0; $rejected = 0; $hide = "N";

    $sql = "INSERT INTO products 
    (item_name, subtitle, category, subcategory, child_category, gender, payment_method, country_of_origin, weight, hsn, gst_type, product_description, verified, rejected, hide)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssssiss",
        $item_name, $subtitle, $category, $subcategory, $child_category, $gender,
        $payment_method, $country_of_origin, $weight, $hsn, $gst_type, $product_description,
        $verified, $rejected, $hide
    );

    if($stmt->execute()) {
        $productid = $conn->insert_id;
        echo json_encode([
            "status" => "success",
            "productid" => $productid,
            "message" => "Product Created (Pending Approval)"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => $conn->error
        ]);
    }
    $stmt->close();
    exit;
}

/* ==================== UPDATE PRODUCT ==================== */
if($action == "update") {
    $productid = $data['productid'] ?? 0;
    $item_name = $data['item_name'] ?? '';
    $subtitle = $data['subtitle'] ?? '';
    $category = $data['category'] ?? '';

    $sql = "UPDATE products SET item_name=?, subtitle=?, category=? WHERE productid=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $item_name, $subtitle, $category, $productid);

    if($stmt->execute()){
        echo json_encode([
            "status"=>"success",
            "message"=>"Product Updated"
        ]);
    } else {
        echo json_encode([
            "status"=>"error",
            "message"=>$conn->error
        ]);
    }
    $stmt->close();
    exit;
}

/* ==================== DELETE / RESTORE PRODUCT ==================== */
if($action == "delete" || $action == "restore") {
    $productid = $data['productid'] ?? 0;
    $hide = ($action=="delete") ? "Y" : "N";
    $sql = "UPDATE products SET hide=? WHERE productid=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $hide, $productid);

    if($stmt->execute()){
        echo json_encode([
            "status"=>"success",
            "message"=>($action=="delete")?"Product Deleted":"Product Restored"
        ]);
    } else {
        echo json_encode([
            "status"=>"error",
            "message"=>$conn->error
        ]);
    }
    $stmt->close();
    exit;
}

/* ==================== ADD VARIANTS ==================== */
if($action == "add_variants") {
    $product_id = $data["product_id"] ?? 0;
    $variants = $data["variants"] ?? [];

    if(!$product_id || empty($variants)){
        echo json_encode(["status"=>"error","message"=>"Invalid data"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO product_detail_description 
        (product_id, colour, size, sku_code, sale_price, qty)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach($variants as $v){
        $color = $v["color"] ?? '';
        $size = $v["size"] ?? '';
        $sku = $v["sku"] ?? '';
        $price = $v["price"] ?? 0;
        $qty = $v["stock"] ?? 0;

        // Prevent duplicate SKU per product
        $check = $conn->prepare("SELECT id FROM product_detail_description WHERE product_id=? AND sku_code=?");
        $check->bind_param("is", $product_id, $sku);
        $check->execute();
        $check->store_result();
        if($check->num_rows > 0){
            $check->close();
            continue; // skip duplicate
        }
        $check->close();

        $stmt->bind_param("isssdi", $product_id, $color, $size, $sku, $price, $qty);
        $stmt->execute();
    }

    echo json_encode(["status"=>"success","message"=>"Variants saved"]);
    $stmt->close();
    exit;
}

/* ==================== INVALID ACTION ==================== */
echo json_encode([
    "status"=>"error",
    "message"=>"Invalid Action"
]);
$conn->close();
?>