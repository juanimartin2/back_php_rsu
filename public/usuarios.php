<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../config/database.php";

$method = $_SERVER['REQUEST_METHOD'];

// Manejar preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Método GET - Obtener usuarios
if ($method === 'GET') {
    try {
        // Si hay ID en la URL, obtener usuario específico
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.nom_usu,
                    u.cuit_usu,
                    u.rol_id,
                    r.nombre AS rol,
                    u.activo,
                    u.fecha_creacion
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                http_response_code(404);
                echo json_encode(["error" => "Usuario no encontrado"]);
                exit;
            }
            
            // Obtener ámbitos del usuario
            $stmtAmbitos = $pdo->prepare("
                SELECT a.id, a.nombre
                FROM ambitos a
                INNER JOIN usuarios_ambitos ua ON a.id = ua.ambito_id
                WHERE ua.usuario_id = ?
            ");
            $stmtAmbitos->execute([$id]);
            $ambitos = $stmtAmbitos->fetchAll(PDO::FETCH_ASSOC);
            
            $usuario['ambitos'] = $ambitos;
            $usuario['ambitos_ids'] = array_column($ambitos, 'id');
            
            http_response_code(200);
            echo json_encode($usuario);
            
        } else {
            // Obtener todos los usuarios
            $stmt = $pdo->query("
                SELECT 
                    u.id,
                    u.nom_usu,
                    u.cuit_usu,
                    r.nombre AS rol,
                    r.id AS rol_id,
                    u.activo,
                    u.fecha_creacion
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                ORDER BY u.id DESC
            ");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Para cada usuario, obtener sus ámbitos
            foreach ($usuarios as &$usuario) {
                $stmtAmbitos = $pdo->prepare("
                    SELECT a.id, a.nombre
                    FROM ambitos a
                    INNER JOIN usuarios_ambitos ua ON a.id = ua.ambito_id
                    WHERE ua.usuario_id = ?
                ");
                $stmtAmbitos->execute([$usuario['id']]);
                $ambitos = $stmtAmbitos->fetchAll(PDO::FETCH_ASSOC);
                
                $usuario['ambitos'] = $ambitos;
                $usuario['ambitos_ids'] = array_column($ambitos, 'id');
            }
            
            http_response_code(200);
            echo json_encode($usuarios);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error en el servidor"]);
        error_log("Error GET usuarios: " . $e->getMessage());
    }
}

// Método POST - Crear nuevo usuario
elseif ($method === 'POST') {
    // Redirigir a register.php
    http_response_code(200);
    echo json_encode(["message" => "Vaya al menu de registro para crear usuarios"]);
}

// Método PUT - Actualizar usuario
elseif ($method === 'PUT') {
    try {
        // Obtener ID de la URL
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "ID de usuario requerido"]);
            exit;
        }
        
        $id = (int)$_GET['id'];
        
        // Leer datos del body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "JSON inválido"]);
            exit;
        }
        
        // Validar que el usuario existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(["error" => "Usuario no encontrado"]);
            exit;
        }
        
        // Validar datos requeridos
        if (!isset($data['nom_usu'], $data['rol_id'], $data['ambitos'])) {
            http_response_code(400);
            echo json_encode(["error" => "Faltan datos requeridos: nom_usu, rol_id, ambitos"]);
            exit;
        }
        
        $nombre = trim($data['nom_usu']);
        $rolId = (int)$data['rol_id'];
        $ambitos = $data['ambitos'];
        $cuit = isset($data['cuit_usu']) ? str_replace(['-', ' '], '', trim($data['cuit_usu'])) : null;
        
        // Validar ámbitos
        if (!is_array($ambitos) || empty($ambitos)) {
            http_response_code(400);
            echo json_encode(["error" => "Debe seleccionar al menos un ámbito"]);
            exit;
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Actualizar datos básicos del usuario
        if ($cuit) {
            $stmt = $pdo->prepare("
                UPDATE usuarios 
                SET nom_usu = ?, rol_id = ?, cuit_usu = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $rolId, $cuit, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE usuarios 
                SET nom_usu = ?, rol_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $rolId, $id]);
        }
        
        // Si hay contraseña nueva, actualizarla
        if (isset($data['pass_usu']) && !empty($data['pass_usu'])) {
            if (strlen($data['pass_usu']) < 8) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(["error" => "La contraseña debe tener al menos 8 caracteres"]);
                exit;
            }
            
            $hash = password_hash($data['pass_usu'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE usuarios SET pass_usu = ? WHERE id = ?");
            $stmt->execute([$hash, $id]);
        }
        
        // Actualizar ámbitos (eliminar existentes y volver a insertar)
        $stmt = $pdo->prepare("DELETE FROM usuarios_ambitos WHERE usuario_id = ?");
        $stmt->execute([$id]);
        
        $stmtAmbito = $pdo->prepare("INSERT INTO usuarios_ambitos (usuario_id, ambito_id) VALUES (?, ?)");
        foreach ($ambitos as $ambitoId) {
            $stmtAmbito->execute([$id, (int)$ambitoId]);
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        // Obtener usuario actualizado
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nom_usu,
                u.cuit_usu,
                r.nombre AS rol,
                u.activo
            FROM usuarios u
            INNER JOIN roles r ON u.rol_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Usuario actualizado exitosamente",
            "user" => $usuario
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["error" => "Error en el servidor"]);
        error_log("Error PUT usuarios: " . $e->getMessage());
    }
}

// Método DELETE - Eliminar usuario
elseif ($method === 'DELETE') {
    try {
        // Obtener ID de la URL
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "ID de usuario requerido"]);
            exit;
        }
        
        $id = (int)$_GET['id'];
        
        // Validar que el usuario existe
        $stmt = $pdo->prepare("SELECT id, nom_usu FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            http_response_code(404);
            echo json_encode(["error" => "Usuario no encontrado"]);
            exit;
        }
        
        // No eliminar al propio usuario
        if ($usuario['id'] === $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(["error" => "No puedes eliminar tu propio usuario"]);
            exit;
        }
        
        // Eliminar usuario (elimina relaciones)
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Usuario eliminado exitosamente",
            "deleted_user" => $usuario['nom_usu']
        ]);
        
    } catch (PDOException $e) {
        // Si falla por FK constraint
        if ($e->getCode() === "23000") {
            http_response_code(409);
            echo json_encode(["error" => "No se puede eliminar el usuario porque tiene registros asociados"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Error en el servidor"]);
            error_log("Error DELETE usuarios: " . $e->getMessage());
        }
    }
}

// Método PATCH - Cambiar estado activo/inactivo
elseif ($method === 'PATCH') {
    try {
        // Obtener ID de la URL
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "ID de usuario requerido"]);
            exit;
        }
        
        $id = (int)$_GET['id'];
        
        // Leer datos del body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!isset($data['activo'])) {
            http_response_code(400);
            echo json_encode(["error" => "Campo 'activo' requerido"]);
            exit;
        }
        
        $activo = (bool)$data['activo'];
        
        // Actualizar estado
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
        $stmt->execute([$activo, $id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Usuario no encontrado"]);
            exit;
        }
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => $activo ? "Usuario activado" : "Usuario desactivado"
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error en el servidor"]);
        error_log("Error PATCH usuarios: " . $e->getMessage());
    }
}

// Método no permitido
else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
?>