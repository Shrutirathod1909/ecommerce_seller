<?php
error_reporting(E_ERROR | E_PARSE); 
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php"; // DB connection

// ================= VALIDATION =================
$email = $_POST['email_id'] ?? '';
$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

if(empty($email) || empty($phone) || empty($password)){
    echo json_encode([
        "status" => "error",
        "message" => "Required fields missing"
    ]);
    exit;
}

// ================= ESCAPE FUNCTION =================
function esc($conn, $value){
    return mysqli_real_escape_string($conn, $value ?? '');
}

// ================= EMAIL / PHONE UNIQUE CHECK =================
$checkSql = "SELECT id FROM vendors WHERE email_id = '".esc($conn, $email)."' OR phone = '".esc($conn, $phone)."'";
$checkRes = mysqli_query($conn, $checkSql);

if(mysqli_num_rows($checkRes) > 0){
    echo json_encode([
        "status" => "error",
        "message" => "Email or Phone already registered"
    ]);
    exit;
}

// ================= FILE UPLOAD FUNCTION =================
function uploadFile($fileKey, $folder = "vendor/") {
    if(isset($_FILES[$fileKey]) && $_FILES[$fileKey]['name'] != ""){

        // ✅ Allow more image types
        $allowedTypes = ['image/jpeg','image/png','image/jpg','image/webp'];
        $fileType = $_FILES[$fileKey]['type'];
        $fileSize = $_FILES[$fileKey]['size'];

        if(!in_array($fileType, $allowedTypes)){
            error_log("File type $fileType not allowed for $fileKey");
            return ""; // invalid file type
        }

        if($fileSize > 2 * 1024 * 1024){ // 2MB limit
            error_log("File size $fileSize too large for $fileKey");
            return ""; // file too large
        }

        $fileName = time() . "_" . basename($_FILES[$fileKey]['name']);
        $target = $folder . $fileName;

        if(!is_dir($folder)) mkdir($folder, 0777, true);

        if(move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target)){
            return $fileName;
        } else {
            error_log("Failed to move uploaded file for $fileKey");
        }
    } else {
        error_log("$fileKey not set in \$_FILES");
    }
    return "";
}

// ================= FILES =================
$gov_id_file = uploadFile("gov_id_file");
$company_logo_file = uploadFile("company_logo"); // New: company logo upload

// ================= VENDOR INSERT =================
$sql = "INSERT INTO vendors SET
vendor_name = '".esc($conn, $_POST['vendor_name'])."',
email_id = '".esc($conn, $email)."',
phone = '".esc($conn, $phone)."',
password = '".md5($password)."',
company_name = '".esc($conn, $_POST['company_name'])."',
vendor_img = '$company_logo_file',
address = '".esc($conn, $_POST['address'])."',
roomno = '".esc($conn, $_POST['roomno'])."',
street = '".esc($conn, $_POST['street'])."',
landmark = '".esc($conn, $_POST['landmark'])."',
city = '".esc($conn, $_POST['city'])."',
state = '".esc($conn, $_POST['state'])."',
statecode = '".esc($conn, $_POST['statecode'])."',
pincode = '".esc($conn, $_POST['pincode'])."',
country = '".esc($conn, $_POST['country'])."',

business_type = '".esc($conn, $_POST['business_type'])."',
business_type_other = '".esc($conn, $_POST['business_type_other'])."',
gst_no = '".esc($conn, $_POST['gst_no'])."',
pancard_no = '".esc($conn, $_POST['pancard_no'])."',
registration_no = '".esc($conn, $_POST['registration_no'])."',
brand_name = '".esc($conn, $_POST['brand_name'])."',

ac_no = '".esc($conn, $_POST['ac_no'])."',
bank_name = '".esc($conn, $_POST['bank_name'])."',
ifsc_code = '".esc($conn, $_POST['ifsc_code'])."',
branch_name = '".esc($conn, $_POST['branch_name'])."',
micr_no = '".esc($conn, $_POST['micr_no'])."',
swift_code = '".esc($conn, $_POST['swift_code'])."',

gov_id = '".esc($conn, $_POST['gov_id'])."',
gov_id_file = '$gov_id_file',
authorized_signatory = '".esc($conn, $_POST['authorized_signatory'])."',
signature_date = '".esc($conn, $_POST['signature_date'])."',

created_on = NOW()
";

// ================= EXECUTE =================
if(mysqli_query($conn, $sql)){

    // ✅ Get last inserted vendor_id
    $vendor_id = mysqli_insert_id($conn);

    // ✅ company_id = vendor_id
    $company_id = $vendor_id;

    // ✅ Update company_id in DB
    mysqli_query($conn, "UPDATE vendors SET company_id = '$company_id' WHERE id = '$vendor_id'");

   

    // ================= RESPONSE =================
    echo json_encode([
        "status" => "success",
        "message" => "Vendor Registered Successfully",
        "vendor_id" => $vendor_id,
        "company_id" => $company_id
    ]);

}else{
    echo json_encode([
        "status" => "error",
        "message" => mysqli_error($conn)
    ]);
}
?>