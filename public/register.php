<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../src/validar_cuit.php";

// Solo aceptar POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido. Use POST"]);
    exit;
}

// Leer datos del body
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Validar JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["error" => "JSON inválido"]);
    exit;
}

// Validar campos requeridos
if (!isset($data["nom_usu"], $data["cuit_usu"], $data["pass_usu"], $data["rol_id"], $data["ambitos"])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos requeridos: nom_usu, cuit_usu, pass_usu, rol_id, ambitos"]);
    exit;
}

$nombre = trim($data["nom_usu"]);
$cuit = trim($data["cuit_usu"]);
$password = $data["pass_usu"];
$rolId = (int)$data["rol_id"];
$ambitos = $data["ambitos"];

// Validar que no estén vacíos
if (empty($nombre) || empty($cuit) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Nombre, CUIT y contraseña no pueden estar vacíos"]);
    exit;
}

// Validar formato y dígito verificador de CUIT
$validacionCuit = validarCUIT($cuit);

if (!$validacionCuit["valido"]) {
    http_response_code(400);
    echo json_encode(["error" => $validacionCuit["mensaje"]]);
    exit;
}

$cuitNormalizado = $validacionCuit["cuit_normalizado"];

// Valida longitud de contraseña
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "La contraseña debe tener al menos 8 caracteres"]);
    exit;
}

// Valida rol_id
if ($rolId < 1 || $rolId > 4) {
    http_response_code(400);
    echo json_encode(["error" => "Rol inválido. Debe ser entre 1 y 4"]);
    exit;
}

// Valida ámbitos
if (!is_array($ambitos) || empty($ambitos)) {
    http_response_code(400);
    echo json_encode(["error" => "Debe seleccionar al menos un ámbito"]);
    exit;
}

// Valida que los ámbitos sean IDs válidos (1-4)
foreach ($ambitos as $ambitoId) {
    if (!is_numeric($ambitoId) || $ambitoId < 1 || $ambitoId > 4) {
        http_response_code(400);
        echo json_encode(["error" => "ID de ámbito inválido: {$ambitoId}"]);
        exit;
    }
}

// Hashea contraseña
$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    // Inicia transacción
    $pdo->beginTransaction();
    
    // Inserta usuario
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nom_usu, cuit_usu, pass_usu, rol_id) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$nombre, $cuitNormalizado, $hash, $rolId]);
    
    $usuarioId = $pdo->lastInsertId();
    
    // Insertar ámbitos
    $stmtAmbito = $pdo->prepare("
        INSERT INTO usuarios_ambitos (usuario_id, ambito_id) 
        VALUES (?, ?)
    ");
    
    foreach ($ambitos as $ambitoId) {
        $stmtAmbito->execute([$usuarioId, (int)$ambitoId]);
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    // Obtener nombre del rol
    $stmtRol = $pdo->prepare("SELECT nombre FROM roles WHERE id = ?");
    $stmtRol->execute([$rolId]);
    $rol = $stmtRol->fetch(PDO::FETCH_COLUMN);
    
    // Obtener nombres de ámbitos
    $placeholders = implode(',', array_fill(0, count($ambitos), '?'));
    $stmtAmbitosNombres = $pdo->prepare("SELECT nombre FROM ambitos WHERE id IN ({$placeholders})");
    $stmtAmbitosNombres->execute($ambitos);
    $ambitosNombres = $stmtAmbitosNombres->fetchAll(PDO::FETCH_COLUMN);
    
    // Respuesta exitosa
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Usuario registrado con éxito",
        "user" => [
            "id" => $usuarioId,
            "nombre" => $nombre,
            "cuit" => $cuitNormalizado,
            "rol" => $rol,
            "ambitos" => $ambitosNombres
        ]
    ]);
    
} catch (PDOException $e) {
    // Revertir transacción en caso de error
    $pdo->rollBack();
    
    // CUIT duplicado
    if ($e->getCode() === "23000") {
        http_response_code(409);
        echo json_encode(["error" => "El CUIT ya está registrado"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error en el servidor"]);
        error_log("Error de BD en registro: " . $e->getMessage());
    }
}
?>