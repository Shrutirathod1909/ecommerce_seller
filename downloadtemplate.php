<?php

$file = __DIR__ . "/Product_template.xlsx"; // ✅ correct file path

if (!file_exists($file)) {
    die("File not found: " . $file);
}

header("Content-Description: File Transfer");
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=" . basename($file));
header("Content-Length: " . filesize($file));
header("Cache-Control: no-cache");

readfile($file);
exit;