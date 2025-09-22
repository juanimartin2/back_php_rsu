<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["email"], $data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos"]);
    exit;
}

$email = $data["email"];
$password = $data["password"];

try {
    $stmt = $pdo->prepare("SELECT id, nombre, email, password FROM usuarios WHERE email = ?");
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(["error" => "Error al preparar la consulta"]);
        exit;
    }
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user !== false && password_verify($password, $user["password"])) {
        // Usuario válido
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $user["id"],
                "nombre" => $user["nombre"],
                "email" => $user["email"]
            ]
        ]);
    } else {
        // Usuario inválido        
        http_response_code(401);
        echo json_encode(["error" => "Credenciales incorrectas"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error en el servidor: " . $e->getMessage()]);
}
