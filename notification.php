<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
header("Content-Type: application/json");

require_once "db.php";

$input = json_decode(file_get_contents("php://input"), true);

$action = $input['action'] ?? $_GET['action'] ?? "";

/* ---------------- Notification Count ---------------- */

if($action == "notification_count"){

    $company_name = $input['company_name'] ?? $_GET['company_name'] ?? "";

    // Pending orders count
    $sql1 = "SELECT COUNT(id) as total
             FROM ecommerce_orders
             WHERE company_name='$company_name'
             AND approved='pending'
             AND hide='N'";
    $r1 = $conn->query($sql1);
    $orderCount = $r1->fetch_assoc()['total'];

    // Pending products count
    $sql2 = "SELECT COUNT(productid) as total
             FROM product
             WHERE company_id IN
                   (SELECT id FROM company WHERE company_name='$company_name')
             AND verified='0'
             AND rejected='0'
             AND hide='N'";
    $r2 = $conn->query($sql2);
    $productCount = $r2->fetch_assoc()['total'];

    $total = $orderCount + $productCount;

    echo json_encode([
        "status" => "success",
        "count" => $total
    ]);
    exit;
}


/* ---------------- Notification List ---------------- */

if($action == "notification_list"){

    $company_name = $input['company_name'] ?? $_GET['company_name'] ?? "";

    $sql = "SELECT 
                id,
                order_id,
                product_name,
                customer_name,
                approved,
                order_date
            FROM ecommerce_orders
            WHERE company_name='$company_name'
            AND hide='N'
            ORDER BY id DESC";

    $result = $conn->query($sql);

    $data = [];

    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }

    echo json_encode([
        "status"=>"success",
        "data"=>$data
    ]);

    exit;
}

?>