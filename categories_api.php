<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_clean();
header("Content-Type: application/json");

require_once "db.php";

$action = $_GET['action'] ?? "";

/* ================================
   PRIMARY CATEGORY
================================ */
if ($action == "primary") {

   $sql = "SELECT id, primary_categories_name 
           FROM primary_categories 
           ORDER BY sort_order ASC";

   $result = mysqli_query($conn, $sql);
   $data = [];

   while ($row = mysqli_fetch_assoc($result)) {
      $data[] = [
         "id"   => (int)$row['id'],
         "name" => $row['primary_categories_name']
      ];
   }

   echo json_encode(["status" => "success", "data" => $data]);
   exit;
}

/* ================================
   CATEGORY (BY PRIMARY ID)
================================ */
if ($action == "category") {

   $primary_id = intval($_GET['primary_id'] ?? 0);

   $res = mysqli_query(
      $conn,
      "SELECT primary_categories_name FROM primary_categories WHERE id='$primary_id'"
   );

   $row = mysqli_fetch_assoc($res);

   if (!$row) {
      echo json_encode(["status" => "error", "message" => "Invalid primary id"]);
      exit;
   }

   $primary_name = $row['primary_categories_name'];

   $stmt = $conn->prepare("
      SELECT id, category_name 
      FROM categories 
      WHERE category=? 
      ORDER BY sort_order ASC
   ");

   $stmt->bind_param("s", $primary_name);
   $stmt->execute();
   $result = $stmt->get_result();

   $data = [];

   while ($r = $result->fetch_assoc()) {
      $data[] = [
         "id"   => (int)$r['id'],
         "name" => $r['category_name']
      ];
   }

   echo json_encode(["status" => "success", "data" => $data]);
   exit;
}

/* ================================
   SUBCATEGORY (BY CATEGORY ID)
================================ */
if ($action == "subcategory") {

   $category_id = intval($_GET['category_id'] ?? 0);

   $res = mysqli_query(
      $conn,
      "SELECT category_name FROM categories WHERE id='$category_id'"
   );

   $row = mysqli_fetch_assoc($res);

   if (!$row) {
      echo json_encode(["status" => "error", "message" => "Invalid category"]);
      exit;
   }

   $category_name = $row['category_name'];

   $stmt = $conn->prepare("
      SELECT id, subcategory_name 
      FROM subcategories 
      WHERE category=? 
      ORDER BY sort_order ASC
   ");

   $stmt->bind_param("s", $category_name);
   $stmt->execute();
   $result = $stmt->get_result();

   $data = [];

   while ($r = $result->fetch_assoc()) {
      $data[] = [
         "id"   => (int)$r['id'],
         "name" => $r['subcategory_name']
      ];
   }

   echo json_encode(["status" => "success", "data" => $data]);
   exit;
}

/* ================================
   CHILD CATEGORY
================================ */
if ($action == "childcategory") {

   $subcategory_id = intval($_GET['subcategory_id'] ?? 0);

   $stmt = $conn->prepare("
      SELECT id, child_category 
      FROM child_categories
      WHERE subcategory=? 
      ORDER BY sort_order ASC
   ");

   $stmt->bind_param("i", $subcategory_id);
   $stmt->execute();
   $result = $stmt->get_result();

   $data = [];

   while ($row = $result->fetch_assoc()) {
      $data[] = [
         "id"   => (int)$row['id'],
         "name" => $row['child_category']
      ];
   }

   echo json_encode(["status" => "success", "data" => $data]);
   exit;
}

/* ================================
   UNIT MEASUREMENT (FIXED)
================================ */
if ($action == "unitmeasurement") {

   $sql = "SELECT DISTINCT unit_measurment, symbol 
           FROM um 
           ORDER BY unit_measurment ASC";

   $result = mysqli_query($conn, $sql);

   $data = [];
   $i = 1;

   while ($row = mysqli_fetch_assoc($result)) {
      $data[] = [
         "id"     => $i++,
         "name"   => $row['unit_measurment'],
         "symbol" => $row['symbol']   // ✅ ADD THIS
      ];
   }

   echo json_encode(["status" => "success", "data" => $data]);
   exit;
}

/* ================================
   INVALID ACTION
================================ */
echo json_encode([
   "status" => "error",
   "message" => "Invalid Action"
]);
?>