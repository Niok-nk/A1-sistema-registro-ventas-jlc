<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/audit_helper.php';
require_once __DIR__ . '/../utils/WhatsAppService.php';

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
        'Factura duplicada',
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
    
    // Obtener estado anterior antes de actualizar + datos del asesor para notificación
    $checkSql = "SELECT v.id, v.estado, v.observaciones, v.numero_factura, v.numero_serie,
                        u.whatsapp AS asesor_whatsapp, u.nombre AS asesor_nombre, u.apellido AS asesor_apellido
                 FROM ventas v
                 JOIN usuarios u ON u.id = v.asesor_id
                 WHERE v.id = :id";
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

    // Determinar si el estado realmente cambia (para no notificar si solo se edita la observación)
    $estadoCambio = ($ventaActual['estado'] !== $estado);
    
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

    // Notificación por WhatsApp al asesor cuando el estado cambia.
    // Es no bloqueante: si falla, no afecta la actualización del estado.
    $whatsappResult = null;
    if ($estadoCambio && !empty($ventaActual['asesor_whatsapp'])) {
        try {
            $whatsapp = new WhatsAppService();
            $nombreCliente = trim(($ventaActual['asesor_nombre'] ?? '') . ' ' . ($ventaActual['asesor_apellido'] ?? ''));
            $whatsappResult = $whatsapp->sendTemplateMessage(
                $ventaActual['asesor_whatsapp'],
                'm01_srv_cambioestadoventas',
                'en',
                [
                    'nombre_cliente' => $nombreCliente,
                    'numero_factura' => $ventaActual['numero_factura'] ?? '',
                    'numero_serie'   => $ventaActual['numero_serie'] ?? '',
                    'nuevo_estado'   => $estado,
                ]
            );
            error_log('WhatsApp notificación - to: ' . $ventaActual['asesor_whatsapp'] . ' | ok: ' . var_export($whatsappResult['ok'] ?? false, true) . ' | error: ' . ($whatsappResult['error'] ?? ''));
        } catch (Throwable $e) {
            error_log('Error enviando notificación WhatsApp: ' . $e->getMessage());
            $whatsappResult = ['ok' => false, 'error' => $e->getMessage()];
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'status' => 200,
        'message' => 'Estado de venta actualizado exitosamente',
        'data' => [
            'id'            => $id,
            'estado'        => $estado,
            'observaciones' => $observaciones,
            'whatsapp'      => $whatsappResult,
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
