<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../config/database.php";

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Endpoint: Lista usuarios
if ($path === "/api/usuarios" && $method === "GET") {
    try {
        $stmt = $pdo->query("
            SELECT 
                u.id, 
                u.nom_usu, 
                u.cuit_usu,
                r.nombre AS rol,
                u.activo
            FROM usuarios u
            INNER JOIN roles r ON u.rol_id = r.id
            ORDER BY u.id
        ");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($usuarios);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error en la consulta"]);
        error_log("Error en /api/usuarios: " . $e->getMessage());
    }
}

// Endpoint: Lista roles
elseif ($path === "/api/roles" && $method === "GET") {
    try {
        $stmt = $pdo->query("SELECT id, nombre, descripcion FROM roles ORDER BY id");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($roles);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error en la consulta"]);
        error_log("Error en /api/roles: " . $e->getMessage());
    }
}

// Endpoint: Lista ámbitos
elseif ($path === "/api/ambitos" && $method === "GET") {
    try {
        $stmt = $pdo->query("SELECT id, nombre FROM ambitos WHERE activo = 1 ORDER BY nombre");
        $ambitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($ambitos);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error en la consulta"]);
        error_log("Error en /api/ambitos: " . $e->getMessage());
    }
}

// Endpoint: CRUD de usuarios
elseif (preg_match('/^\/usuarios/', $path)) {
    require_once __DIR__ . "/usuarios.php";
}

// Ruta no encontrada
else {
    http_response_code(404);
    echo json_encode(["error" => "Ruta no encontrada"]);
}
?>