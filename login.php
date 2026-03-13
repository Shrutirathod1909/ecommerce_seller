<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "db.php";

$debug = false; 

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

$stmt = $conn->prepare("SELECT id,email_id,password FROM vendors WHERE email_id=?");

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
                "email"=>$vendor['email_id']
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
        "message"=>"Email not registered"
    ]);

}

$stmt->close();
$conn->close();
?>