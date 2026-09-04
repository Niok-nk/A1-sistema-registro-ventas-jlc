<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/audit_helper.php';

// Verificar autenticación y rol de administrador
$user = requireAuth();

// Solo administradores pueden cambiar el estado de ventas
if ($user['rol'] !== 'admin' && $user['rol'] !== 'administrador') {
    http_response_code(403);
    echo json_encode([
        'status' => 403,
        'message' => 'Acceso denegado. Solo administradores pueden cambiar estados de ventas.'
    ]);
    exit;
}

try {
    // Obtener datos del request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['estado'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 400,
            'message' => 'Faltan campos requeridos: id y estado'
        ]);
        exit;
    }
    
    $id = (int)$data['id'];
    $estado = trim($data['estado']);
    $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : null;
    
    // Validar estado
    $estadosValidos = ['pendiente', 'aprobada', 'rechazada'];
    if (!in_array($estado, $estadosValidos)) {
        http_response_code(400);
        echo json_encode([
            'status' => 400,
            'message' => 'Estado inválido. Debe ser: pendiente, aprobada o rechazada'
        ]);
        exit;
    }

    // Validar observaciones — solo permitidas si estado es rechazada
    $observacionesValidas = [
        'Número de serie incorrecto o inválido',
        'Factura sin código QR o CUFE (DIAN)',
        'Venta sin registro en la DIAN',
        'Fecha de venta inválida: no corresponde al mes en curso',
    ];
    if ($estado !== 'rechazada') {
        $observaciones = null; // Limpiar al no rechazar
    } elseif ($observaciones !== null && $observaciones !== '' && !in_array($observaciones, $observacionesValidas)) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => 'Observación inválida.']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener estado anterior antes de actualizar
    $checkSql = "SELECT id, estado, observaciones FROM ventas WHERE id = :id";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute(['id' => $id]);
    $ventaActual = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ventaActual) {
        http_response_code(404);
        echo json_encode([
            'status' => 404,
            'message' => 'Venta no encontrada'
        ]);
        exit;
    }
    
    // Actualizar estado y observaciones
    $sql = "UPDATE ventas SET estado = :estado, observaciones = :observaciones WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'id'            => $id,
        'estado'        => $estado,
        'observaciones' => $observaciones,
    ]);

    // Auditoría: log del cambio de estado
    logAudit(
        $conn,
        (int)$user['user_id'],
        'cambio_estado_venta',
        'ventas',
        $id,
        ['estado' => $ventaActual['estado'], 'observaciones' => $ventaActual['observaciones']],
        ['estado' => $estado, 'observaciones' => $observaciones]
    );
    
    http_response_code(200);
    echo json_encode([
        'status' => 200,
        'message' => 'Estado de venta actualizado exitosamente',
        'data' => [
            'id'            => $id,
            'estado'        => $estado,
            'observaciones' => $observaciones,
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error updating sale status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Error al actualizar estado de venta'
    ]);
}
