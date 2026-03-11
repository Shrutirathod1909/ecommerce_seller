<?php

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$action = $data['action'];

if($action == "get"){

    $vendor_id = $data['vendor_id'];

    $q = mysqli_query($conn,"SELECT
    vendor_name,
    business_type,
    pancard_no,
    contactable_person,
    designation,
    phone,
    email_id,
    street,
    city,
    state,
    country,
    pincode
    FROM vendors WHERE id='$vendor_id'");

    $row = mysqli_fetch_assoc($q);

    echo json_encode([
        "status"=>"success",
        "data"=>$row
    ]);
}

if($action == "update"){

    $vendor_id = $data['vendor_id'];

    mysqli_query($conn,"UPDATE vendors SET

    vendor_name='".$data['vendor_name']."',
    business_type='".$data['business_type']."',
    pancard_no='".$data['pancard_no']."',
    contactable_person='".$data['contactable_person']."',
    designation='".$data['designation']."',
    phone='".$data['phone']."',
    email_id='".$data['email_id']."',
    street='".$data['street']."',
    city='".$data['city']."',
    state='".$data['state']."',
    country='".$data['country']."',
    pincode='".$data['pincode']."'

    WHERE id='$vendor_id'");

    echo json_encode([
        "status"=>"success",
        "message"=>"Profile Updated"
    ]);
}