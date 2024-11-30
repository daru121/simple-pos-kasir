<?php
// backend/api/users.php
include '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Register a new user
    $data = json_decode(file_get_contents("php://input"));
    $name = $data->name;
    $email = $data->email;
    $password = password_hash($data->password, PASSWORD_DEFAULT);
    $role = $data->role;
    $status = $data->status;

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $email, $password, $role, $status])) {
        echo json_encode(['message' => 'User registered successfully']);
    } else {
        echo json_encode(['message' => 'User registration failed']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all users
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($users);
}
?>