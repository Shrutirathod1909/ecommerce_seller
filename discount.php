<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

/* ---------- INPUTS ---------- */

$productid      = intval($data["productid"] ?? 0);
$disc_type      = $data["disc_type"] ?? '';
$disc_amt       = floatval($data["disc_amt"] ?? 0);
$discount_title = $data["discount_title"] ?? '';
$description    = $data["description"] ?? '';
$created_by     = $data["created_by"] ?? 'admin';

/* ---------- DATES ---------- */

$disc_start_date = !empty($data["disc_start_date"])
    ? date("Y-m-d", strtotime($data["disc_start_date"]))
    : null;

$disc_end_date = !empty($data["disc_end_date"])
    ? date("Y-m-d", strtotime($data["disc_end_date"]))
    : null;

/* ---------- VALIDATION ---------- */

if ($productid <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid productid"
    ]);
    exit;
}

/* ---------- START TRANSACTION ---------- */

$conn->begin_transaction();

try {

    /* =========================
       1. UPDATE PRODUCTS TABLE
    ========================== */

    $sql1 = "UPDATE products SET
        modified_on = NOW()
        WHERE productid = ?";

    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("i", $productid);
    $stmt1->execute();


    /* ==========================================
       2. UPDATE PRODUCT DETAIL DESCRIPTION TABLE
    =========================================== */

    $sql2 = "UPDATE product_detail_description SET
        modified_on = NOW()
        WHERE product_id = ?";

    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $productid);
    $stmt2->execute();


    /* =========================
       3. INSERT INTO DISCOUNT TABLE
    ========================== */

    $insert_sql = "INSERT INTO discount (
        discount_title,
        description,
        discount_type,
        discount_value,
        applicable_from,
        applicable_to,
        created_by,
        active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Y')";

    $stmt3 = $conn->prepare($insert_sql);

    $stmt3->bind_param(
        "sssisss",
        $discount_title,
        $description,
        $disc_type,
        $disc_amt,
        $disc_start_date,
        $disc_end_date,
        $created_by
    );

    $stmt3->execute();


    /* ---------- COMMIT ---------- */

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Discount applied successfully"
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>