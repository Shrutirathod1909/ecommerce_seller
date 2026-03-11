<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$productid = $data["productid"] ?? '';
$disc_type = $data["disc_type"] ?? '';
$disc_amt = $data["disc_amt"] ?? '';
$disc_start_date = $data["disc_start_date"] ?? '';
$disc_end_date = $data["disc_end_date"] ?? '';

$sql = "UPDATE products SET
disc_type=?,
disc_amt=?,
disc_start_date=?,
disc_end_date=?
WHERE productid=?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssssi",
    $disc_type,
    $disc_amt,
    $disc_start_date,
    $disc_end_date,
    $productid
);

if($stmt->execute()){

 echo json_encode([
   "status"=>"success",
   "message"=>"Discount Updated"
 ]);

}else{

 echo json_encode([
   "status"=>"error",
   "message"=>$conn->error
 ]);

}

$conn->close();
?>