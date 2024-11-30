<?php
// backend/index.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST");

$request = $_SERVER['REQUEST_URI'];

if (strpos($request, '/api/users') !== false) {
    include 'api/users.php';
} elseif (strpos($request, '/api/products') !== false) {
    include 'api/products.php';
} else {
    echo json_encode(['message' => 'API endpoint not found']);
}
?>