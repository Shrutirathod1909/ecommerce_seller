<?php
header("Content-Type: application/json");
require_once "db.php";

$target_dir = "uploads/";

// create folder if not exists
if(!file_exists($target_dir)){
    mkdir($target_dir,0777,true);
}

$productid = $_POST['productid'] ?? '';
$imageIndex = intval($_POST['image_index'] ?? 1);

// allow only image1 to image12
if($imageIndex < 1 || $imageIndex > 12){
    echo json_encode([
        "status"=>"error",
        "message"=>"Invalid image index"
    ]);
    exit;
}

$column = "image".$imageIndex;

if(isset($_FILES['image'])){

    // allowed extensions
    $allowed = ['jpg','jpeg','png','webp'];

    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

    if(!in_array($ext,$allowed)){
        echo json_encode([
            "status"=>"error",
            "message"=>"Invalid image type"
        ]);
        exit;
    }

    // generate unique filename
    $image_name = uniqid().".".$ext;

    $target_file = $target_dir.$image_name;

    if(move_uploaded_file($_FILES['image']['tmp_name'],$target_file)){

        $sql = "UPDATE products SET $column=? WHERE productid=?";
        $stmt = $conn->prepare($sql);

        $stmt->bind_param("si",$image_name,$productid);

        if($stmt->execute()){

            echo json_encode([
                "status"=>"success",
                "image"=>$image_name,
                "column"=>$column
            ]);

        }else{

            echo json_encode([
                "status"=>"error",
                "message"=>"Database update failed"
            ]);

        }

    }else{

        echo json_encode([
            "status"=>"error",
            "message"=>"Image upload failed"
        ]);

    }

}else{

    echo json_encode([
        "status"=>"error",
        "message"=>"No image received"
    ]);

}

$conn->close();
?>