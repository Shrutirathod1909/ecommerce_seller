<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if(empty($email) || empty($password)){
    echo json_encode([
        "status"=>"error",
        "message"=>"Email and Password required"
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id,email_id,password 
                        FROM vendors 
                        WHERE email_id=?");

$stmt->bind_param("s",$email);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){

    $vendor = $result->fetch_assoc();

    if(password_verify($password, $vendor['password'])){

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

$conn->close();
?>