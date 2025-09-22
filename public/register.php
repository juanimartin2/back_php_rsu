<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

// Solo aceptar POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit;
}

// Leer datos del body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["nombre"], $data["email"], $data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos"]);
    exit;
}

$nombre = trim($data["nombre"]);
$email = trim($data["email"]);
$passwordPlano = $data["password"];

// Validaciones simples
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Email inválido"]);
    exit;
}

if (strlen($passwordPlano) < 6) {
    http_response_code(400);
    echo json_encode(["error" => "La contraseña debe tener al menos 6 caracteres"]);
    exit;
}

// Hashear contraseña
$hash = password_hash($passwordPlano, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre,email,password) VALUES (?,?,?)");
    $stmt->execute([$nombre, $email, $hash]);

    echo json_encode([
        "success" => true,
        "message" => "Usuario registrado con éxito",
        "user" => [
            "id" => $pdo->lastInsertId(),
            "nombre" => $nombre,
            "email" => $email
        ]
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === "23000") { // violación de clave única
        http_response_code(409);
        echo json_encode(["error" => "El email ya está registrado"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error en el servidor: " . $e->getMessage()]);
    }
}
