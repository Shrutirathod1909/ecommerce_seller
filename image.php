<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once "db.php";

/* ================= CONFIG ================= */

// ✅ ABSOLUTE PATH (VERY IMPORTANT)
$target_dir = __DIR__ . "/uploads/";

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

// Validate index (1 to 12)
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

// ✅ MIME TYPE CHECK (SECURE)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['image']['tmp_name']);

// Allowed image types
$allowedMime = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/heic',
    'image/heif',
    'image/avif'
];

if (!in_array($mime, $allowedMime)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid image type: " . $mime
    ]);
    exit;
}

/* ================= EXTENSION FIX ================= */

switch ($mime) {
    case 'image/jpeg': $ext = 'jpg'; break;
    case 'image/png': $ext = 'png'; break;
    case 'image/webp': $ext = 'webp'; break;
    case 'image/gif': $ext = 'gif'; break;
    case 'image/heic': $ext = 'heic'; break;
    case 'image/heif': $ext = 'heif'; break;
    case 'image/avif': $ext = 'avif'; break;
    default: $ext = 'jpg';
}

/* ================= SAVE IMAGE ================= */

// Generate unique filename
$image_name = uniqid("img_") . "." . $ext;

// Full path (SERVER)
$target_file = $target_dir . $image_name;

// Move uploaded file
if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
    echo json_encode([
        "status" => "error",
        "message" => "Image upload failed",
        "path" => $target_file
    ]);
    exit;
}

/* ================= DATABASE UPDATE ================= */

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
    "image" => UPLOAD_URL . $image_name, // FULL URL
    "column" => $column,
    "message" => "Image uploaded successfully"
]);

$conn->close();
?>