<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

include "db.php";

// ---------------- METHOD ----------------
$method = $_SERVER['REQUEST_METHOD'];

// =====================================================
// ===================== GET (FETCH) ====================
// =====================================================
if ($method === 'GET') {

    $vendor_id = $_GET['vendor_id'] ?? "";

    if ($vendor_id == "") {
        echo json_encode([
            "status" => "error",
            "message" => "vendor_id required"
        ]);
        exit;
    }

    $res = mysqli_query($conn, "
        SELECT 
            vendor_code,
            gov_id_file,
            vendor_img,
            file_agreement,
            commission_agreement,
            rights_agreement,
            delivery_agreement
        FROM vendors 
        WHERE id='".intval($vendor_id)."'
    ");

    $row = mysqli_fetch_assoc($res);

    if (!$row) {
        echo json_encode([
            "status" => "error",
            "message" => "Vendor not found"
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "type" => "GET",
        "data" => [
            "vendor_code" => $row['vendor_code'],
            "gov_id_file" => json_decode($row['gov_id_file'], true) ?? (object)[],
            "selfie" => $row['vendor_img'],
            "file_agreement" => $row['file_agreement'],
            "commission_agreement" => $row['commission_agreement'],
            "rights_agreement" => $row['rights_agreement'],
            "delivery_agreement" => $row['delivery_agreement']
        ]
    ]);
    exit;
}

// =====================================================
// ===================== POST (UPLOAD) ==================
// =====================================================
if ($method === 'POST') {

    $vendor_id = $_POST['vendor_id'] ?? "";

    if ($vendor_id == "") {
        echo json_encode([
            "status" => "error",
            "message" => "vendor_id required"
        ]);
        exit;
    }

    // ---------------- GET VENDOR ----------------
    $res = mysqli_query($conn, "SELECT vendor_code FROM vendors WHERE id='".intval($vendor_id)."'");
    $row = mysqli_fetch_assoc($res);

    $vendor_code = $row['vendor_code'] ?? "";

    if ($vendor_code == "") {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid vendor"
        ]);
        exit;
    }

    // ---------------- FOLDER ----------------
    $uploadDir = "vendor/" . $vendor_code . "/";

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // ---------------- ALLOWED ----------------
    $allowedExt = ['jpg','jpeg','png','pdf'];

    function uploadFile($key, $name)
    {
        global $uploadDir, $allowedExt;

        if (!isset($_FILES[$key]) || $_FILES[$key]['name'] == "") {
            return "";
        }

        $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            return "INVALID_FILE_TYPE";
        }

        if ($_FILES[$key]['error'] != 0) {
            return "UPLOAD_ERROR_" . $_FILES[$key]['error'];
        }

        $fileName = $name . "." . $ext;
        $targetPath = $uploadDir . $fileName;

        if (file_exists($targetPath)) {
            unlink($targetPath);
        }

        if (move_uploaded_file($_FILES[$key]['tmp_name'], $targetPath)) {
            return $targetPath;
        }

        return "UPLOAD_FAILED";
    }

    // ---------------- UPLOAD ----------------
    $pan = uploadFile("pan", "pan");
    $bank = uploadFile("bank", "bank");
    $selfie = uploadFile("selfie", "selfie");

    $file_agreement = uploadFile("file_agreement", "general_agreement");
    $commission_agreement = uploadFile("commission_agreement", "commission_agreement");
    $rights_agreement = uploadFile("rights_agreement", "rights_agreement");
    $delivery_agreement = uploadFile("delivery_agreement", "delivery_agreement");

    // ---------------- BUILD QUERY ----------------
    $fields = [];
    $gov_files = [];

    // ✅ FIXED LOGIC HERE
    if (!empty($pan) && strpos($pan, "UPLOAD") === false && $pan != "INVALID_FILE_TYPE") {
        $gov_files['pan'] = $pan;
    }

    if (!empty($bank) && strpos($bank, "UPLOAD") === false && $bank != "INVALID_FILE_TYPE") {
        $gov_files['bank'] = $bank;
    }

    if (!empty($gov_files)) {
        $fields[] = "gov_id_file='".mysqli_real_escape_string($conn, json_encode($gov_files))."'";
    }

    if (!empty($selfie) && strpos($selfie, "UPLOAD") === false) {
        $fields[] = "vendor_img='$selfie'";
    }

    if (!empty($file_agreement) && strpos($file_agreement, "UPLOAD") === false) {
        $fields[] = "file_agreement='$file_agreement'";
    }

    if (!empty($commission_agreement) && strpos($commission_agreement, "UPLOAD") === false) {
        $fields[] = "commission_agreement='$commission_agreement'";
    }

    if (!empty($rights_agreement) && strpos($rights_agreement, "UPLOAD") === false) {
        $fields[] = "rights_agreement='$rights_agreement'";
    }

    if (!empty($delivery_agreement) && strpos($delivery_agreement, "UPLOAD") === false) {
        $fields[] = "delivery_agreement='$delivery_agreement'";
    }

    // ❌ NOTHING TO UPDATE
    if (empty($fields)) {
        echo json_encode([
            "status" => "error",
            "message" => "No valid files uploaded",
            "debug" => [
                "pan" => $pan,
                "bank" => $bank
            ]
        ]);
        exit;
    }

    // ---------------- UPDATE ----------------
    $sql = "UPDATE vendors SET " . implode(", ", $fields) . " WHERE id='".intval($vendor_id)."'";

    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            "status" => "success",
            "type" => "POST",
            "message" => "KYC Uploaded Successfully",
            "vendor_code" => $vendor_code,
            "gov_id_file" => !empty($gov_files) ? $gov_files : (object)[]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => mysqli_error($conn)
        ]);
    }

    exit;
}
?>