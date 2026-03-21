<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once "db.php";

/* ================= CONFIG ================= */

// Absolute folder path (VERY IMPORTANT)
$target_dir = "uploads/";

// Create folder if not exists
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

/* ================= INPUT ================= */

$productid = $_POST['productid'] ?? '';
$imageIndex = intval($_POST['image_index'] ?? 1);

// Validate product ID
if (!$productid) {
    echo json_encode([
        "status" => "error",
        "message" => "Product ID missing"
    ]);
    exit;
}

// Allow only image1 to image12
if ($imageIndex < 1 || $imageIndex > 12) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid image index"
    ]);
    exit;
}

$column = "image" . $imageIndex;

/* ================= IMAGE CHECK ================= */

if (!isset($_FILES['image'])) {
    echo json_encode([
        "status" => "error",
        "message" => "No image received"
    ]);
    exit;
}

// Allowed extensions
$allowed = ['jpg', 'jpeg', 'png', 'webp'];

$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid image type"
    ]);
    exit;
}

/* ================= SAVE IMAGE ================= */

// Generate unique filename
$image_name = uniqid("img_") . "." . $ext;

// Full path
$target_file = $target_dir . $image_name;

// Debug (optional)
error_log("Uploading file to: " . $target_file);

// Move file
if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
    echo json_encode([
        "status" => "error",
        "message" => "Image upload failed"
    ]);
    exit;
}

/* ================= UPDATE DATABASE ================= */

$sql = "UPDATE products SET $column=? WHERE productid=?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
    exit;
}

$stmt->bind_param("si", $image_name, $productid);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Database update failed"
    ]);
    exit;
}

/* ================= SUCCESS RESPONSE ================= */

echo json_encode([
    "status" => "success",
    "image" => UPLOAD_URL . $image_name, // ✅ FULL URL
    "column" => $column,
    "message" => "Image uploaded successfully"
]);

$conn->close();
?>