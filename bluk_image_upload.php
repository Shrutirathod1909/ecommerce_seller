<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db.php"; // DB connection

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['status' => false, 'msg' => 'Invalid request']);
    exit;
}

$product_id = intval($_POST['product_id'] ?? $_POST['productid'] ?? 0);
$created_by = $_POST['created_by'] ?? 'admin';

if (!$product_id || !isset($_FILES['image'])) {
    echo json_encode(['status' => false, 'msg' => 'Missing product_id or images']);
    exit;
}

$target_dir = $_SERVER['DOCUMENT_ROOT'] . "/uatcms/productgallery/";
$base_path  = "productgallery/";

if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$uploaded = [];
$files = $_FILES['image'];

if (!is_array($files['name'])) {
    $files = [
        'name' => [$files['name']],
        'tmp_name' => [$files['tmp_name']],
        'type' => [$files['type']],
        'error' => [$files['error']],
        'size' => [$files['size']]
    ];
}

// Get current images in DB to find empty columns
$res = $conn->query("SELECT image1,image2,image3,image4,image5,image6,image7,image8,image9,image10,image11,image12 FROM products WHERE productid=$product_id");
$current = $res->fetch_assoc();

foreach ($files['name'] as $i => $name) {
    if ($files['error'][$i] != 0) continue;

    $tmp = $files['tmp_name'][$i];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;

    $filename = uniqid('img_') . '.' . $ext;
    $target = $target_dir . $filename;

    if (move_uploaded_file($tmp, $target)) {
        $db_path = $base_path . $filename;

        // Find first empty image column
        for ($col = 1; $col <= 12; $col++) {
            $field = "image$col";
            if (empty($current[$field])) {
                $conn->query("UPDATE products SET $field='$db_path' WHERE productid=$product_id");
                $current[$field] = $db_path; // mark as filled
                break;
            }
        }

        $uploaded[] = $filename;
    }
}

echo json_encode([
    'status' => !empty($uploaded),
    'msg'    => !empty($uploaded) ? 'Images uploaded successfully' : 'No images uploaded',
    'files'  => $uploaded
]);

$conn->close();
?>