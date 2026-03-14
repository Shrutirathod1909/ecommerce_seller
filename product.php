<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "db.php";

/* -------- GET INPUT -------- */

$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

/* ================= SHOW PRODUCTS ================= */

if($action == "show"){

    $status = $input['status'] ?? $_GET['status'] ?? "approved";

    if($status == "approved") $where = "p.verified=1 AND p.rejected=0 AND p.hide='N'";
    else if($status == "pending") $where = "p.verified=0 AND p.rejected=0 AND p.hide='N'";
    else if($status == "rejected") $where = "p.rejected=1 AND p.hide='N'";
    else if($status == "restore") $where = "p.hide='Y'";
    else $where = "1";

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

            GROUP BY 
                p.productid,
                p.item_name,
                p.subtitle,
                p.category,
                p.image1

            ORDER BY p.productid DESC";

    $result = $conn->query($sql);

    $products = [];

  while($row = $result->fetch_assoc()){

    if(!empty($row['image1'])){

        
        if(file_exists("uploads/".$row['image1'])){
            $row['image1'] = UPLOAD_URL.$row['image1'];
        }
        else{
            $row['image1'] = IMGPATH.$row['image1'];
        }

    }

    $products[] = $row;
}

    echo json_encode([
        "status"=>"success",
        "data"=>$products
    ]);

    exit;
}

/* ================= SHOW VARIANTS ================= */

if($action == "show_variants"){

    $product_id = $input['product_id'] ?? $_GET['product_id'] ?? 0;

    if(empty($product_id)){
        echo json_encode([
            "status"=>"error",
            "message"=>"Product ID Missing"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
        id,
        colour,
        size,
        sku_code,
        sale_price,
        qty
        FROM product_detail_description
        WHERE product_id=?
        ORDER BY id ASC
    ");

    $stmt->bind_param("i",$product_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $variants = [];

    while($row = $result->fetch_assoc()){
        $variants[] = $row;
    }

    echo json_encode([
        "status"=>"success",
        "data"=>$variants
    ]);

    $stmt->close();
    exit;
}

/* ================= ADD PRODUCT ================= */

if($action == "add"){

    $item_name = $input['item_name'] ?? '';
    $subtitle = $input['subtitle'] ?? '';
    $category = $input['category'] ?? '';

    $verified = 0;
    $rejected = 0;
    $hide = "N";

    $stmt = $conn->prepare("
        INSERT INTO products
        (item_name,subtitle,category,verified,rejected,hide)
        VALUES (?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "sssiss",
        $item_name,
        $subtitle,
        $category,
        $verified,
        $rejected,
        $hide
    );

    if($stmt->execute()){

        echo json_encode([
            "status"=>"success",
            "productid"=>$conn->insert_id,
            "message"=>"Product Created"
        ]);

    }else{

        echo json_encode([
            "status"=>"error",
            "message"=>$conn->error
        ]);
    }

    $stmt->close();
    exit;
}

/* ================= DELETE / RESTORE ================= */

if($action == "delete" || $action == "restore"){

    $productid = $input['productid'] ?? 0;

    $hide = ($action == "delete") ? "Y" : "N";

    $stmt = $conn->prepare("
        UPDATE products
        SET hide=?
        WHERE productid=?
    ");

    $stmt->bind_param("si",$hide,$productid);

    if($stmt->execute()){

        echo json_encode([
            "status"=>"success",
            "message"=>"Action Completed"
        ]);

    }else{

        echo json_encode([
            "status"=>"error",
            "message"=>$conn->error
        ]);
    }

    $stmt->close();
    exit;
}
/* ================= add_variants ================= */

if($action == "add_variants") {

    $inputJSON = file_get_contents("php://input");
    $data = json_decode($inputJSON, true);

    $product_id = $data["product_id"] ?? 0;
    $variants = $data["variants"] ?? [];

    if(empty($product_id) || empty($variants)){
        echo json_encode([
            "status"=>"error",
            "message"=>"Invalid data"
        ]);
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

        $stmt->bind_param("isssdi", $product_id, $color, $size, $sku, $price, $qty);
        $stmt->execute();
    }

    echo json_encode([
        "status"=>"success",
        "message"=>"Variants saved successfully"
    ]);

    exit;
}
/* ================= INVALID ACTION ================= */

echo json_encode([
    "status"=>"error",
    "message"=>"Invalid Action"
]);

$conn->close();

?>