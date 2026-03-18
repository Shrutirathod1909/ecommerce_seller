<?php
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';

/* ================= GET GST DETAILS ================= */
if($action == "get") {
    $vendor_id = $data['vendor_id'] ?? 0;

    $stmt = $conn->prepare("SELECT gst_no, company_name, address FROM vendors
     WHERE id=?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()){
        // Return keys matching Flutter code
        echo json_encode([
            "status" => "success",
            "data" => [
                "gst_no" => $row["gst_no"],
                "company_name" => $row["company_name"],
                "address" => $row["address"]
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Vendor not found",
            "data" => [
                "gst_no" => "",
                "company_name" => "",
                "address" => ""
            ]
        ]);
    }
}

/* ================= UPDATE GST DETAILS ================= */
if($action == "update") {
    $vendor_id = $data['vendor_id'] ?? 0;
    $gst_no = $data['gst_no'] ?? '';
    $company_name = $data['company_name'] ?? '';
    $address = $data['address'] ?? '';

    // Check if vendor exists
    $check = $conn->prepare("SELECT id FROM vendors WHERE id=?");
    $check->bind_param("i", $vendor_id);
    $check->execute();
    $res = $check->get_result();

    if($res->num_rows > 0){
        // UPDATE
        $stmt = $conn->prepare("UPDATE vendors SET gst_no=?, company_name=?, address=? WHERE id=?");
        $stmt->bind_param("sssi", $gst_no, $company_name, $address, $vendor_id);
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "message" => "GST details updated successfully"
        ]);
    } else {
        // INSERT if vendor does not exist
        $stmt = $conn->prepare("INSERT INTO vendors (id, gst_no, company_name, address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $vendor_id, $gst_no, $company_name, $address);
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "message" => "GST details inserted successfully"
        ]);
    }
}
?>