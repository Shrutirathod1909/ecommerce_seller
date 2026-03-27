    <?php
    error_reporting(E_ERROR | E_PARSE);
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    date_default_timezone_set("Asia/Kolkata");
    include "db.php";

    // ================= ACTION =================
    $action = $_POST['action'] ?? '';

    // ================= ESCAPE FUNCTION =================
    function esc($conn, $value)
    {
        return mysqli_real_escape_string($conn, $value ?? '');
    }

    function normalize($v)
    {
        $v = strtolower(trim($v));
        return preg_replace('/[^a-z ]/', '', $v);
    }

    if ($action == "validate_pincode_city") {

        $pincode = $_POST['pincode'] ?? '';
        $city = $_POST['city'] ?? '';

        if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
            echo json_encode(["status" => "error", "message" => "Invalid Pincode"]);
            exit;
        }

        $postOffices = getPincodeData($pincode);

        if (!$postOffices) {
            echo json_encode(["status" => "error", "message" => "Invalid Pincode"]);
            exit;
        }

        $city = strtolower(trim($city));
        $match = false;

        foreach ($postOffices as $po) {
            if (
                str_contains(strtolower($po['District']), $city) ||
                str_contains(strtolower($po['Name']), $city)
            ) {
                $match = true;
                break;
            }
        }

        if (!$match) {
            echo json_encode(["status" => "error", "message" => "City does not match Pincode"]);
            exit;
        }

        echo json_encode(["status" => "success", "message" => "Valid"]);
        exit;
    }
    // ================= GST FUNCTIONS =================
    function formatGST($gst)
    {
        return strtoupper(trim($gst));
    }

    function isValidGST($gst)
    {
        return preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gst);
    }

    function getStateCode($gst)
    {
        return substr($gst, 0, 2);
    }

    function isValidPAN($pan)
    {
        return preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan);
    }

    if ($action == "get_gst_details") {

        $gst = formatGST($_POST['gst_no'] ?? '');

        if (strlen($gst) != 15) {
            echo json_encode(["status" => "error", "message" => "Invalid GST"]);
            exit;
        }

        $state_code = substr($gst, 0, 2);
        $pan = substr($gst, 2, 10);

        echo json_encode([
            "status" => "success",
            "pan" => $pan,
            "state_name" => $gst_states[$state_code] ?? ""
        ]);
        exit;

    }


    function generateVendorCode($conn) {
    do {
        $code = "VEN" . rand(10000, 99999);

        $check = mysqli_query($conn, "SELECT id FROM vendors WHERE vendor_code='$code'");
    } while (mysqli_num_rows($check) > 0);

    return $code;
}






    // ================= PINCODE VALIDATION (CURL) =================
    function getPincodeData($pincode)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.postalpincode.in/pincode/" . $pincode);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return false;

        $data = json_decode($response, true);

        if (
            !$data ||
            $data[0]['Status'] != "Success" ||
            empty($data[0]['PostOffice'])
        ) {
            return false;
        }

        return $data[0]['PostOffice'];
    }

    // ================= GST STATE LIST =================
    $gst_states = [
        "01" => "Jammu & Kashmir",
        "02" => "Himachal Pradesh",
        "03" => "Punjab",
        "04" => "Chandigarh",
        "05" => "Uttarakhand",
        "06" => "Haryana",
        "07" => "Delhi",
        "08" => "Rajasthan",
        "09" => "Uttar Pradesh",
        "10" => "Bihar",
        "11" => "Sikkim",
        "12" => "Arunachal Pradesh",
        "13" => "Nagaland",
        "14" => "Manipur",
        "15" => "Mizoram",
        "16" => "Tripura",
        "17" => "Meghalaya",
        "18" => "Assam",
        "19" => "West Bengal",
        "20" => "Jharkhand",
        "21" => "Odisha",
        "22" => "Chhattisgarh",
        "23" => "Madhya Pradesh",
        "24" => "Gujarat",
        "25" => "Daman & Diu",
        "26" => "Dadra & Nagar Haveli",
        "27" => "Maharashtra",
        "28" => "Andhra Pradesh (Old)",
        "29" => "Karnataka",
        "30" => "Goa",
        "31" => "Lakshadweep",
        "32" => "Kerala",
        "33" => "Tamil Nadu",
        "34" => "Puducherry",
        "35" => "Andaman & Nicobar",
        "36" => "Telangana",
        "37" => "Andhra Pradesh",
        "38" => "Ladakh",
        "97" => "Other Territory",
        "99" => "Centre Jurisdiction"
    ];



    // ================= INPUT =================
    $email = $_POST['email_id'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $gst = formatGST($_POST['gst_no'] ?? '');
    $pancard = strtoupper(trim($_POST['pancard_no'] ?? ''));
    $username = $_POST['username'] ?? '';
    $pincode = $_POST['pincode'] ?? '';
    $city = $_POST['city'] ?? '';
    $user_state = $_POST['state'] ?? '';
    $vendor_code = generateVendorCode($conn);

    // ================= BASIC VALIDATION =================
    if (empty($email) || empty($phone) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Required fields missing"]);
        exit;
    }
    if (empty($username)) {
        echo json_encode(["status" => "error", "message" => "Username required"]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Invalid email"]);
        exit;
    }

    if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
        echo json_encode(["status" => "error", "message" => "Invalid phone"]);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(["status" => "error", "message" => "Weak password"]);
        exit;
    }

    // ================= PINCODE VALIDATION =================
    $pin_state = "";
    $cityMatch = false;

    if (!empty($pincode) && !empty($city)) {

        if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
            echo json_encode(["status" => "error", "message" => "Invalid Pincode"]);
            exit;
        }

        $postOffices = getPincodeData($pincode);

        if (!$postOffices) {
            echo json_encode(["status" => "error", "message" => "Invalid Pincode Data"]);
            exit;
        }


        $userCity = strtolower(trim($city));

        foreach ($postOffices as $po) {

            $district = strtolower($po['District'] ?? '');
            $division = strtolower($po['Division'] ?? '');
            $name     = strtolower($po['Name'] ?? '');

            if (
                str_contains($district, $userCity) ||
                str_contains($userCity, $district) ||
                str_contains($division, $userCity) ||
                str_contains($name, $userCity)
            ) {
                $cityMatch = true;
                $pin_state = $po['State'];
                break;
            }
        }

        // fallback match
        if (!$cityMatch) {
            foreach ($postOffices as $po) {

                $district = normalize($po['District'] ?? '');

                if (
                    strpos($userCity, $district) !== false ||
                    strpos($district, $userCity) !== false
                ) {

                    $cityMatch = true;
                    $pin_state = $po['State'];
                    break;
                }
            }
        }

        if (!$cityMatch) {
            echo json_encode(["status" => "error", "message" => "City does not match Pincode"]);
            exit;
        }
    }


    // ================= UNIQUE CHECK =================
    $checkSql = "SELECT id FROM vendors 
    WHERE email_id = '" . esc($conn, $email) . "' 
    OR phone = '" . esc($conn, $phone) . "'
    OR username = '" . esc($conn, $username) . "'";

    $checkRes = mysqli_query($conn, $checkSql);

    if (mysqli_num_rows($checkRes) > 0) {
        echo json_encode(["status" => "error", "message" => "Email, Phone or Username already exists"]);
        exit;
    }

    // ================= GST VALIDATION =================
    $gst_state_name = "";

    if (!empty($gst)) {

        if (strlen($gst) != 15) {
            echo json_encode(["status" => "error", "message" => "GST must be 15 characters"]);
            exit;
        }

        if (!isValidGST($gst)) {
            echo json_encode(["status" => "error", "message" => "Invalid GST format"]);
            exit;
        }

        $gstCheck = mysqli_query($conn, "SELECT id FROM vendors WHERE gst_no='" . esc($conn, $gst) . "'");
        if (mysqli_num_rows($gstCheck) > 0) {
            echo json_encode(["status" => "error", "message" => "GST already registered"]);
            exit;
        }

        $gst_state_code = getStateCode($gst);
        $gst_state_name = $gst_states[$gst_state_code] ?? "";

        $pan_from_gst = substr($gst, 2, 10);

        if (empty($pancard)) {
            $pancard = $pan_from_gst;
        }

        // GST vs PINCODE
        if (!empty($pin_state) && strtolower($pin_state) != strtolower($gst_state_name)) {
            echo json_encode([
                "status" => "error",
                "message" => "GST state and Pincode state mismatch"
            ]);
            exit;
        }
    }

    // ================= PAN VALIDATION =================
    if (!empty($pancard)) {

        if (!isValidPAN($pancard)) {
            echo json_encode(["status" => "error", "message" => "Invalid PAN"]);
            exit;
        }

        if (!empty($gst)) {
            if (strtoupper(substr($gst, 2, 10)) != strtoupper($pancard)) {
                echo json_encode(["status" => "error", "message" => "PAN does not match GST"]);
                exit;
            }
        }
    }

    // ================= FINAL STATE DECISION =================
    $final_state = "";

    if (!empty($gst_state_name)) {
        $final_state = $gst_state_name;
    } else if (!empty($pin_state)) {
        $final_state = $pin_state;
    }

    // OPTIONAL USER STATE CHECK
    if (!empty($user_state) && !empty($final_state)) {
        if (strtolower($user_state) != strtolower($final_state)) {
            echo json_encode([
                "status" => "error",
                "message" => "State does not match GST/Pincode state"
            ]);
            exit;
        }
    }

    // ================= FILE UPLOAD =================
   function uploadFile($fileKey, $vendor_code, $folder = "vendor/") {

    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['name'] != "") {

        $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) return "";
        if ($_FILES[$fileKey]['size'] > 2 * 1024 * 1024) return "";

        if (!is_dir($folder)) mkdir($folder, 0777, true);

        $fileName = $vendor_code . "_" . time() . "_" . rand(1000, 9999) . "." . $ext;
        $target = $folder . $fileName;

        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target)) {
            return $fileName;
        }
    }
    return "";
}
   $company_logo_file = uploadFile("company_logo", $vendor_code);
$gov_id = uploadFile("gov_id_file", $vendor_code);
$gst_certificate = uploadFile("gst_certificate", $vendor_code);

    $gov_id_file = json_encode([
        "gov_id" => $gov_id,
        "gst_certificate" => $gst_certificate
    ]);

    // ================= INSERT =================
    $sql = "INSERT INTO vendors SET
        vendor_code = '".esc($conn, $vendor_code)."',
    vendor_name = '".esc($conn, $_POST['vendor_name'])."',
    username = '".esc($conn, $username)."',
    email_id = '".esc($conn, $email)."',
    phone = '".esc($conn, $phone)."',
    password = '".md5($password)."',
    company_name = '".esc($conn, $_POST['company_name'])."',
    vendor_img = '".esc($conn, $company_logo_file)."',
    address = '".esc($conn, $_POST['address'])."',
    roomno = '".esc($conn, $_POST['roomno'])."',
    street = '".esc($conn, $_POST['street'])."',
    landmark = '".esc($conn, $_POST['landmark'])."',
    city = '".esc($conn, $city)."',
    state = '".esc($conn, $final_state)."',
    pincode = '".esc($conn, $pincode)."',
    country = '".esc($conn, $_POST['country'])."',
    business_type = '".esc($conn, $_POST['business_type'])."',
    business_type_other = '".esc($conn, $_POST['business_type_other'])."',
    gst_no = '".esc($conn, $gst)."',
    pancard_no = '".esc($conn, $pancard)."',
    brand_name = '".esc($conn, $_POST['brand_name'])."',
    ac_no = '".esc($conn, $_POST['ac_no'])."',
    bank_name = '".esc($conn, $_POST['bank_name'])."',
    ifsc_code = '".esc($conn, $_POST['ifsc_code'])."',
    branch_name = '".esc($conn, $_POST['branch_name'])."',
    micr_no = '".esc($conn, $_POST['micr_no'])."',
    swift_code = '".esc($conn, $_POST['swift_code'])."',
    gov_id = '".esc($conn, $_POST['gov_id'])."',
    gov_id_file = '".esc($conn, $gov_id_file)."',
    authorized_signatory = '".esc($conn, $_POST['authorized_signatory'])."',
    signature_date = '".esc($conn, $_POST['signature_date'])."',
    vendor_type = 'seller', 
    approved = 'no',

    hide = 'N',
    created_on = NOW()";
    // ================= EXECUTE =================
    if (mysqli_query($conn, $sql)) {

        $vendor_id = mysqli_insert_id($conn);
        $company_id = $vendor_id;

        mysqli_query($conn, "UPDATE vendors SET company_id='$company_id' WHERE id='$vendor_id'");

        echo json_encode([
            "status" => "success",
            "message" => "Vendor Registered Successfully",
            "vendor_id" => $vendor_id,
            "company_id" => $company_id,
             "vendor_code" => $vendor_code,
            "time" => date("Y-m-d H:i:s")
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => mysqli_error($conn)
        ]);
    }
