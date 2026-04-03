<?php
header("Content-Type: application/json");
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data["action"] ?? "";

/* ================= GET ================= */
if ($action == "get") {

    $productid = intval($data["productid"] ?? 0);

    if ($productid <= 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid product ID"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT disc_type, disc_amt, disc_start_date, disc_end_date
        FROM products
        WHERE productid = ?
    ");

    $stmt->bind_param("i", $productid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            "status" => "success",
            "data" => $row
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No discount found"
        ]);
    }
    exit;
}

/* ================= SAVE ================= */
if ($action == "save") {

    $productid = intval($data["productid"] ?? 0);
    $disc_type = strtolower($data["disc_type"] ?? '');
    $disc_amt  = floatval($data["disc_amt"] ?? 0);
    $start     = $data["disc_start_date"] ?? '';
    $end       = $data["disc_end_date"] ?? '';

    /* ================= PRICE FIX ================= */

    // ✅ First try Flutter भेजा हुआ price
    $basePrice = floatval($data["base_price"] ?? 0);

    // ✅ fallback (old param support)
    if ($basePrice <= 0) {
        $basePrice = floatval($data["original_price"] ?? 0);
    }

    // ❌ last fallback (DB)
    if ($basePrice <= 0) {

        $sql = "SELECT cad_price, mrp_price 
                FROM product_detail_description 
                WHERE product_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $productid);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $basePrice = floatval($row["cad_price"]) > 0
                ? floatval($row["cad_price"])
                : floatval($row["mrp_price"]);
        }
    }

    if ($basePrice <= 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid base price"
        ]);
        exit;
    }

    /* ================= CALCULATION ================= */

    if ($disc_type == "percentage") {
        $discount = ($basePrice * $disc_amt) / 100;
    } else {
        $discount = $disc_amt;
    }

    $final = $basePrice - $discount;

    if ($final < 0) $final = 0;

    /* ================= UPDATE PRICE ================= */

    $stmt1 = $conn->prepare("
        UPDATE product_detail_description
        SET sale_price = ?, modified_on = NOW()
        WHERE product_id = ?
    ");
    $stmt1->bind_param("di", $final, $productid);
    $stmt1->execute();

    /* ================= SAVE DISCOUNT ================= */

    $stmt2 = $conn->prepare("
        UPDATE products
        SET disc_type = ?, 
            disc_amt = ?, 
            disc_start_date = ?, 
            disc_end_date = ?, 
            modified_on = NOW()
        WHERE productid = ?
    ");
    $stmt2->bind_param("sdssi",
        $disc_type, $disc_amt, $start, $end, $productid
    );
    $stmt2->execute();

    echo json_encode([
        "status" => "success",
        "message" => "Discount applied successfully",
        "sale_price" => $final
    ]);
    exit;
}

/* ================= DEFAULT ================= */
echo json_encode([
    "status" => "error",
    "message" => "Invalid action"
]);

$conn->close();
?>