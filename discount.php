<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$productid       = $data["productid"] ?? '';
$disc_type       = $data["disc_type"] ?? '';
$disc_amt        = $data["disc_amt"] ?? '';
$disc_start_date = $data["disc_start_date"] ?? '';
$disc_end_date   = $data["disc_end_date"] ?? '';
$discount_title  = $data["discount_title"] ?? '';
$description     = $data["description"] ?? '';
$created_by      = $data["created_by"] ?? 'admin';

// Convert dates to proper format
$disc_start_date = !empty($disc_start_date) ? date("Y-m-d", strtotime($disc_start_date)) : null;
$disc_end_date   = !empty($disc_end_date) ? date("Y-m-d", strtotime($disc_end_date)) : null;

// 1️⃣ Update the product
$sql = "UPDATE products SET
    disc_type = ?,
    disc_amt = ?,
    disc_start_date = ?,
    disc_end_date = ?
    WHERE productid = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $disc_type, $disc_amt, $disc_start_date, $disc_end_date, $productid);

if($stmt->execute()){

    // 2️⃣ Insert into discount table
    $insert_sql = "INSERT INTO discount (
        discount_title, description, discount_type, discount_value, 
        applicable_from, applicable_to, created_by, active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Y')";

    $stmt2 = $conn->prepare($insert_sql);
    $stmt2->bind_param(
        "sssssss",
        $discount_title,
        $description,
        $disc_type,
        $disc_amt,
        $disc_start_date,
        $disc_end_date,
        $created_by
    );

    if($stmt2->execute()){
        echo json_encode([
            "status" => "success",
            "message" => "Discount updated in products and inserted in discount table"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Product updated but discount insert failed: ".$conn->error
        ]);
    }

} else {
    echo json_encode([
        "status" => "error",
        "message" => "Product update failed: ".$conn->error
    ]);
}

$conn->close();
?>