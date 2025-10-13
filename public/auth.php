<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$cuit = $data["cuit"];
$password = $data["password"];

if (!isset($data["cuit"], $data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos"]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nom_usu, cuit_usu, pass_usu FROM usuarios WHERE cuit_usu = ?");
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(["error" => "Error al preparar la consulta"]);
        exit;
    }
    $stmt->execute([$cuit]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user !== false && password_verify($password, $user["pass_usu"])) {
        // Usuario válido
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $user["id"],
                "nombre" => $user["nom_usu"],
                "cuit" => $user["cuit_usu"]
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
