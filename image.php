<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once "db.php";

/* ================= CONFIG ================= */

// ✅ CORRECT PATH (IMPORTANT FIX)
$target_dir = $_SERVER['DOCUMENT_ROOT'] . "/uatcms/productgallery/";
$base_path  = "productgallery/";

// create folder if not exists
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

/* ================= INPUT ================= */

$productid  = $_POST['productid'] ?? '';
$imageIndex = intval($_POST['image_index'] ?? 0);

/* ================= VALIDATION ================= */

if (!$productid) {
    echo json_encode([
        "status" => "error",
        "message" => "Product ID missing"
    ]);
    exit;
}

/* ========================================================= */
/* ================= 🔥 FETCH IMAGES ======================= */
/* ========================================================= */

if (!isset($_FILES['image'])) {

    $sql = "SELECT 
        image1, image2, image3, image4, image5, image6,
        image7, image8, image9, image10, image11, image12
        FROM products WHERE productid=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productid);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $images = [];

        for ($i = 1; $i <= 12; $i++) {
            $col = "image" . $i;

            if (!empty($row[$col])) {
                $images[] = IMGPATH . $row[$col];
            }
        }

        echo json_encode([
            "status" => "success",
            "images" => $images
        ]);

    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No product found"
        ]);
    }

    exit;
}

/* ========================================================= */
/* ================= 🔥 UPLOAD IMAGE ======================= */
/* ========================================================= */

if ($imageIndex < 1 || $imageIndex > 12) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid image index (1-12 allowed)"
    ]);
    exit;
}

$column = "image" . $imageIndex;

/* ================= MIME CHECK ================= */

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['image']['tmp_name']);

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

/* ================= EXTENSION ================= */

switch ($mime) {
    case 'image/jpeg': $ext = 'jpg'; break;
    case 'image/png':  $ext = 'png'; break;
    case 'image/webp': $ext = 'webp'; break;
    case 'image/gif':  $ext = 'gif'; break;
    case 'image/heic': $ext = 'heic'; break;
    case 'image/heif': $ext = 'heif'; break;
    case 'image/avif': $ext = 'avif'; break;
    default: $ext = 'jpg';
}

/* ================= SAVE FILE ================= */

$image_name  = uniqid("img_") . "." . $ext;
$target_file = $target_dir . $image_name;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
    echo json_encode([
        "status" => "error",
        "message" => "Upload failed"
    ]);
    exit;
}

/* ================= DB PATH ================= */

$db_path = $base_path . $image_name;

/* ================= UPDATE DB ================= */

$sql = "UPDATE products SET $column=? WHERE productid=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $db_path, $productid);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Database update failed"
    ]);
    exit;
}

/* ================= SUCCESS ================= */

echo json_encode([
    "status" => "success",
    "message" => "Image uploaded successfully",
    "image" => IMGPATH . $db_path,
    "column" => $column
]);

$conn->close();
?>