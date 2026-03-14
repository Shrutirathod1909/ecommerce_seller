<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$productid = $data["productid"] ?? '';
$disc_type = $data["disc_type"] ?? '';
$disc_amt = $data["disc_amt"] ?? '';
$disc_start_date = $data["disc_start_date"] ?? '';
$disc_end_date = $data["disc_end_date"] ?? '';

// Convert date for TEXT column
$disc_start_date = !empty($disc_start_date) ? date("d-m-Y", strtotime($disc_start_date)) : '';
$disc_end_date   = !empty($disc_end_date) ? date("d-m-Y", strtotime($disc_end_date)) : '';

$sql = "UPDATE products SET
disc_type=?,
disc_amt=?,
disc_start_date=?,
disc_end_date=?
WHERE productid=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $disc_type, $disc_amt, $disc_start_date, $disc_end_date, $productid);

if($stmt->execute()){

    // fetch updated record
    $stmt2 = $conn->prepare("SELECT productid, disc_type, disc_amt, disc_start_date, disc_end_date FROM products WHERE productid=?");
    $stmt2->bind_param("i", $productid);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $updated = $result->fetch_assoc();

    echo json_encode([
        "status"=>"success",
        "message"=>"Discount Updated",
        "data"=>$updated
    ]);

}else{
    echo json_encode([
        "status"=>"error",
        "message"=>$conn->error
    ]);
}

$conn->close();
?>