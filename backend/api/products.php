<?php
// backend/api/products.php
include '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new product
    $data = json_decode(file_get_contents("php://input"));
    $name = $data->name;
    $category = $data->category;
    $price = $data->price;
    $stock = $data->stock;

    $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$name, $category, $price, $stock])) {
        echo json_encode(['message' => 'Product added successfully']);
    } else {
        echo json_encode(['message' => 'Product addition failed']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all products
    $stmt = $pdo->query("SELECT * FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);
}
?>