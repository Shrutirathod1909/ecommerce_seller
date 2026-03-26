<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once "db.php";

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if(!$data){
    $data = $_POST;
}

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if(empty($email) || empty($password)){
    echo json_encode([
        "status"=>"error",
        "message"=>"Email and Password are required"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, email_id, password, company_name, company_id, approved, hide 
    FROM vendors 
    WHERE email_id=? AND approved='yes' AND hide='N'
");

if(!$stmt){
    echo json_encode([
        "status"=>"error",
        "message"=>"Database error"
    ]);
    exit;
}

$stmt->bind_param("s",$email);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){

    $vendor = $result->fetch_assoc();

    $db_password = strtolower(trim($vendor['password']));
    $input_md5 = md5($password);

    if($input_md5 === $db_password){

        echo json_encode([
            "status"=>"success",
            "vendor"=>[
                "vendor_id"=>$vendor['id'],
                "email"=>$vendor['email_id'],
                "company_name"=>$vendor['company_name'],
                "company_id"=>$vendor['company_id']
            ]
        ]);

    }else{
        echo json_encode([
            "status"=>"error",
            "message"=>"Invalid password"
        ]);
    }

}else{

    echo json_encode([
        "status"=>"error",
        "message"=>"Account not approved or blocked / Email not registered"
    ]);
}

$stmt->close();
$conn->close();
?>