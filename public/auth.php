<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../src/validar_cuit.php";

// Solo acepta POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido. Use POST"]);
    exit;
}

// Leo datos del body
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Valida JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["error" => "JSON inválido"]);
    exit;
}

// Valida campos requeridos
if (!isset($data["cuit"], $data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos requeridos: cuit y password"]);
    exit;
}

$cuit = trim($data["cuit"]);
$password = $data["password"];

// Valida que no estén vacíos
if (empty($cuit) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "CUIT y contraseña no pueden estar vacíos"]);
    exit;
}

// Valida formato y dígito verificador de CUIT
$validacionCuit = validarCUIT($cuit);

if (!$validacionCuit["valido"]) {
    http_response_code(400);
    echo json_encode(["error" => $validacionCuit["mensaje"]]);
    exit;
}

$cuitNormalizado = $validacionCuit["cuit_normalizado"];

try {
    // Busca usuario con su rol
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.nom_usu,
            u.cuit_usu,
            u.pass_usu,
            u.activo,
            r.nombre AS rol,
            r.id AS rol_id
        FROM usuarios u
        INNER JOIN roles r ON u.rol_id = r.id
        WHERE u.cuit_usu = ?
        LIMIT 1
    ");
    
    $stmt->execute([$cuitNormalizado]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verifica si el usuario existe
    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "Credenciales incorrectas"]);
        exit;
    }
    
    // Verifica si el usuario está activo
    if (!$user["activo"]) {
        http_response_code(403);
        echo json_encode(["error" => "Usuario desactivado. Contacte al administrador"]);
        exit;
    }
    
    // Verifica contraseña
    if (!password_verify($password, $user["pass_usu"])) {
        http_response_code(401);
        echo json_encode(["error" => "Credenciales incorrectas"]);
        exit;
    }

    // Obtiene permisos del usuario
    $stmtPermisos = $pdo->prepare("
        SELECT p.nombre
        FROM permisos p
        INNER JOIN roles_permisos rp ON p.id = rp.permiso_id
        WHERE rp.rol_id = ?
        ORDER BY p.nombre
    ");
    $stmtPermisos->execute([$user["rol_id"]]);
    $permisos = $stmtPermisos->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener ámbitos del usuario
    $stmtAmbitos = $pdo->prepare("
        SELECT a.id, a.nombre
        FROM ambitos a
        INNER JOIN usuarios_ambitos ua ON a.id = ua.ambito_id
        WHERE ua.usuario_id = ?
        ORDER BY a.nombre
    ");
    $stmtAmbitos->execute([$user["id"]]);
    $ambitos = $stmtAmbitos->fetchAll(PDO::FETCH_ASSOC);
    
    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Login exitoso",
        "user" => [
            "id" => $user["id"],
            "nombre" => $user["nom_usu"],
            "cuit" => $user["cuit_usu"],
            "rol" => $user["rol"],
            "permisos" => $permisos,
            "ambitos" => $ambitos
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error en el servidor"]);
    error_log("Error de BD en login: " . $e->getMessage());
}
?>