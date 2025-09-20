<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../config/database.php";

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === "/api/usuarios") {
    try {
        $stmt = $pdo->query("SELECT id, nombre FROM usuarios");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($usuarios);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error en la consulta: " . $e->getMessage()]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Ruta no encontrada"]);
}
