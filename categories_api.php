<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_clean(); // 🔥 ADD THIS
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? '';

/* ================================
   PRIMARY CATEGORY
================================ */
if($action=="primary"){

   $sql="SELECT id, primary_categories_name 
         FROM primary_categories 
         ORDER BY sort_order ASC";

   $result=mysqli_query($conn,$sql);

   $data=[];

   while($row=mysqli_fetch_assoc($result)){
      $data[]=[
         "id"=>$row['id'],
         "name"=>$row['primary_categories_name']
      ];
   }

   echo json_encode([
      "status"=>"success",
      "data"=>$data
   ]);

   exit;
}

/* ================================
   CATEGORY (BY PRIMARY ID)
================================ */
if($action=="category"){

   $primary_id = intval($_GET['primary_id']);

   // STEP 1: get primary name
   $res = mysqli_query($conn, "SELECT primary_categories_name FROM primary_categories WHERE id='$primary_id'");
   $row = mysqli_fetch_assoc($res);

   $primary_name = $row['primary_categories_name'];

   // STEP 2: get categories using name
   $stmt=$conn->prepare("SELECT id, category_name 
                         FROM categories 
                         WHERE category=? 
                         ORDER BY sort_order ASC");

   $stmt->bind_param("s",$primary_name);
   $stmt->execute();
   $result=$stmt->get_result();

   $data=[];
   while($r=$result->fetch_assoc()){
      $data[]=[
         "id"=>$r['id'],
         "name"=>$r['category_name']
      ];
   }

   echo json_encode(["status"=>"success","data"=>$data]);
   exit;
}
/* ================================
   SUBCATEGORY (BY CATEGORY ID)
   ===============================*/
if($action=="subcategory"){

   $category_id = intval($_GET['category_id']);

   
   $res = mysqli_query($conn, "SELECT category_name FROM categories WHERE id='$category_id'");
   $row = mysqli_fetch_assoc($res);

   if(!$row){
      echo json_encode(["status"=>"error","message"=>"Invalid category"]);
      exit;
   }

   $category_name = $row['category_name'];

   $stmt = $conn->prepare("SELECT id, subcategory_name 
                           FROM subcategories 
                           WHERE category=? 
                           ORDER BY sort_order ASC");

   $stmt->bind_param("s",$category_name);
   $stmt->execute();
   $result = $stmt->get_result();

   $data = [];

   while($r = $result->fetch_assoc()){
      $data[] = [
         "id"=>$r['id'],
         "name"=>$r['subcategory_name']
      ];
   }

   echo json_encode([
      "status"=>"success",
      "data"=>$data
   ]);

   exit;
}

/* ================================
   CHILD CATEGORY (BY SUBCATEGORY ID)
================================ */
if ($action == "childcategory") {

   $subcategory_id = intval($_GET['subcategory_id'] ?? 0);

   $sql = "SELECT id, child_category 
           FROM child_categories
           WHERE subcategory=? 
           ORDER BY sort_order ASC";

   $stmt = $conn->prepare($sql);
   $stmt->bind_param("i", $subcategory_id);
   $stmt->execute();
   $result = $stmt->get_result();

   $data = [];

   while ($row = $result->fetch_assoc()) {
      $data[] = [
         "id"=>$row['id'],
         "name"=>$row['child_category']
      ];
   }

   echo json_encode(["status"=>"success","data"=>$data]);
   exit;
}

/* ================================
   UNIT MEASUREMENT
================================ */
if($action=="unitmeasurement"){

   $sql="SELECT DISTINCT unit_measurment 
         FROM um
         ORDER BY sort_order ASC";

   $result=mysqli_query($conn,$sql);
   $data=[];

   while($row=mysqli_fetch_assoc($result)){
      $data[]=[
         "name"=>$row['unit_measurment']
      ];
   }

   echo json_encode(["status"=>"success","data"=>$data]);
   exit;
}

/* ================================
   INVALID
================================ */
echo json_encode([
   "status"=>"error",
   "message"=>"Invalid Action"
]);
?>