<?php
/**
 * API Endpoint: Restablecer contraseña de usuario
 * Solo accesible por administradores.
 * Genera una contraseña aleatoria de 15 caracteres y la devuelve en texto plano.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 405, 'message' => 'Método no permitido']);
    exit();
}

/**
 * Genera una contraseña aleatoria criptográficamente segura.
 * Garantiza al menos un carácter de cada grupo para mayor fortaleza.
 * 
 * @param int $length Longitud de la contraseña
 * @return string Contraseña generada
 */
function generateSecurePassword(int $length = 15): string {
    $uppercase  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';  // Sin I, O para evitar confusión visual
    $lowercase  = 'abcdefghjkmnpqrstuvwxyz';   // Sin i, l, o para evitar confusión visual
    $digits     = '23456789';                   // Sin 0, 1 para evitar confusión visual
    $symbols    = '!@#$%&*';
    $all        = $uppercase . $lowercase . $digits . $symbols;

    // Garantizar al menos un carácter de cada grupo
    $password  = $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $digits[random_int(0, strlen($digits) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];

    // Rellenar el resto de forma aleatoria
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    // Mezclar para que los caracteres garantizados no queden siempre al inicio
    return str_shuffle($password);
}

try {
    // Verificar autenticación y que el solicitante sea administrador
    $decoded      = requireAuth();
    $requesterId  = $decoded['user_id'] ?? null;
    $requesterRol = $decoded['rol'] ?? null;

    if ($requesterRol !== 'administrador') {
        http_response_code(403);
        echo json_encode(['status' => 403, 'message' => 'Acceso denegado. Solo administradores pueden restablecer contraseñas.']);
        exit();
    }

    // Leer y validar el cuerpo de la petición
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!$data || !isset($data['userId'])) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => 'Se requiere el campo userId.']);
        exit();
    }

    $targetUserId = (int) $data['userId'];

    // Validar que el userId sea positivo
    if ($targetUserId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => 'userId inválido.']);
        exit();
    }

    // SEGURIDAD: Un admin no puede restablecer su propia contraseña desde este endpoint
    if ($targetUserId === (int) $requesterId) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => 'No puedes restablecer tu propia contraseña desde este panel.']);
        exit();
    }

    $db = Database::getInstance()->getConnection();

    // Verificar que el usuario objetivo existe
    $stmt = $db->prepare("SELECT id, nombre, apellido, rol FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $targetUserId, PDO::PARAM_INT);
    $stmt->execute();
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'message' => 'Usuario no encontrado.']);
        exit();
    }

    // Generar y hashear la nueva contraseña
    $newPasswordPlain = generateSecurePassword(15);
    $newPasswordHash  = password_hash($newPasswordPlain, PASSWORD_BCRYPT, ['cost' => 12]);

    // Actualizar la contraseña en la base de datos
    $stmt = $db->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
    $stmt->bindParam(':password', $newPasswordHash, PDO::PARAM_STR);
    $stmt->bindParam(':id', $targetUserId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(500);
        echo json_encode(['status' => 500, 'message' => 'No se pudo actualizar la contraseña. Inténtalo de nuevo.']);
        exit();
    }

    // Registrar en auditoría (si existe la tabla)
    try {
        $accion = "Restableció la contraseña del usuario {$targetUser['nombre']} {$targetUser['apellido']} (ID: {$targetUserId})";
        $stmtAudit = $db->prepare(
            "INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) 
             VALUES (:admin_id, :accion, 'usuarios', :user_id)"
        );
        $stmtAudit->bindParam(':admin_id', $requesterId, PDO::PARAM_INT);
        $stmtAudit->bindParam(':accion',   $accion,     PDO::PARAM_STR);
        $stmtAudit->bindParam(':user_id',  $targetUserId, PDO::PARAM_INT);
        $stmtAudit->execute();
    } catch (PDOException $e) {
        // La auditoría es opcional — no interrumpir el flujo si la tabla no existe
        error_log("Advertencia: No se pudo registrar en auditoría: " . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'status'  => 200,
        'message' => "Contraseña de {$targetUser['nombre']} {$targetUser['apellido']} restablecida correctamente.",
        'data'    => [
            'password' => $newPasswordPlain,
            'userName' => "{$targetUser['nombre']} {$targetUser['apellido']}",
        ],
    ]);

} catch (PDOException $e) {
    error_log("Error DB en reset_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Error en el servidor. Intenta nuevamente.']);
} catch (Exception $e) {
    error_log("Error en reset_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
}
