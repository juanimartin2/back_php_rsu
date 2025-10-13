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

if (!isset($data["nom_usu"], $data["cuit_usu"], $data["pass_usu"])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos"]);
    exit;
}

$nombre = trim($data["nom_usu"]);
$cuit = trim($data["cuit_usu"]);
$passwordPlano = $data["pass_usu"];

// Validaciones simples
if (!preg_match("/^[0-9]{11}$/", $cuit)) {
    http_response_code(400);
    echo json_encode(["error" => "Cuit inválido"]);
    exit;
}

if (strlen($passwordPlano) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "La contraseña debe tener al menos 8 caracteres"]);
    exit;
}

// Hashear contraseña
$hash = password_hash($passwordPlano, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("INSERT INTO usuarios (nom_usu,cuit_usu,pass_usu) VALUES (?,?,?)");
    $stmt->execute([$nombre, $cuit, $hash]);

    echo json_encode([
        "success" => true,
        "message" => "Usuario registrado con éxito",
        "user" => [
            "id" => $pdo->lastInsertId(),
            "nom_usu" => $nombre,
            "cuit_usu" => $cuit
        ]
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === "23000") { // cuit duplicado
        http_response_code(409);
        echo json_encode(["error" => "El CUIT ya está registrado"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error en el servidor: " . $e->getMessage()]);
    }
}
 