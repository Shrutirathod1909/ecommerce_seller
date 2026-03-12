<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // allow Flutter app calls
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "db.php";

// DEBUG MODE: set to true to see input and DB hashes
$debug = false;

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Fallback to $_POST if JSON not sent
if (!$data || !is_array($data)) {
    $data = $_POST;
}

// Extract email and password
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

// Validate input
if(empty($email) || empty($password)){
    echo json_encode([
        "status"=>"error",
        "message"=>"Email and Password are required"
    ]);
    exit;
}

// Prepare SQL
$stmt = $conn->prepare("SELECT id, email_id, password FROM vendors WHERE email_id=?");
if(!$stmt){
    echo json_encode([
        "status"=>"error",
        "message"=>"Database error: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){

    $vendor = $result->fetch_assoc();

    // Clean DB password
    $db_password = strtolower(trim($vendor['password']));
    $input_md5 = md5($password);

    // Debug output
    if($debug){
        echo json_encode([
            "status"=>"debug",
            "email"=>$email,
            "original_password"=>$password,
            "md5_hashed_input"=>$input_md5,
            "db_password"=>$db_password
        ]);
        exit;
    }

    // MD5 comparison
    if($input_md5 === $db_password){
        echo json_encode([
            "status"=>"success",
            "vendor"=>[
                "vendor_id"=>$vendor['id'],
                "email"=>$vendor['email_id']
            ]
        ]);
    } else {
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