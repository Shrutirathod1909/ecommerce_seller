<?php
header("Content-Type: application/json");
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$action = $data['action'] ?? '';
$vendor_id = $data['vendor_id'] ?? '';

/* ================= GET ================= */

if($action == "get"){

    if(empty($vendor_id)){
        echo json_encode(["status"=>"error","message"=>"Vendor ID missing"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM vendors WHERE id=?");
    $stmt->bind_param("s", $vendor_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode(["status"=>"success","data"=>$row]);
}


/* ================= UPDATE ================= */

if($action == "update"){

    if(empty($vendor_id)){
        echo json_encode(["status"=>"error","message"=>"Vendor ID missing"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE vendors SET
        vendor_name=?,
        vendor_code=?,
        business_type=?,
        pancard_no=?,
        contactable_person=?,
        designation=?,
        phone=?,
        alternate_phone=?,
        email_id=?,
        alternate_email=?,
        street=?,
        city=?,
        state=?,
        country=?,
        pincode=?
        WHERE id=?");

    $stmt->bind_param(
        "ssssssssssssssss",
        $data['vendor_name'],
        $data['vendor_code'],
        $data['business_type'],
        $data['pancard_no'],
        $data['contactable_person'],
        $data['designation'],
        $data['phone'],
        $data['alternate_phone'],   // NEW
        $data['email_id'],
        $data['alternate_email'],   // NEW
        $data['street'],
        $data['city'],
        $data['state'],
        $data['country'],
        $data['pincode'],
        $vendor_id
    );

    if($stmt->execute()){
        echo json_encode([
            "status"=>"success",
            "message"=>"Profile Updated Successfully"
        ]);
    } else {
        echo json_encode([
            "status"=>"error",
            "message"=>$stmt->error
        ]);
    }
}
?>