<?php
/**
 * API Endpoint: Listar ventas
 * Method: GET
 * Auth: JWT de sesión requerido por header Authorization: Bearer
 *
 * NOTA sobre URLs de archivos:
 * Este endpoint devuelve solo el nombre del archivo en `foto_factura`.
 * Para obtener una URL firmada de acceso al archivo, el frontend debe llamar a:
 *   POST /api/files/signed_url.php  { "file": "<foto_factura>", "type": "factura" }
 * y recibirá una URL válida 5 minutos (?ftoken=...) sin exponer el JWT de sesión en la URL.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

// Autenticación requerida
$user = requireAuth();

try {
    $db   = Database::getInstance();
    $conn = $db->getConnection();

    // Consulta — Admin ve todas las ventas, Asesor solo ve las suyas
    $sql = "SELECT v.id, v.numero_factura, v.fecha_venta, v.estado, v.observaciones, v.numero_serie, v.producto_id, v.foto_factura, v.created_at,
                   (SELECT a.created_at FROM auditoria a
                    WHERE a.tabla_afectada = 'ventas' AND a.registro_id = v.id AND a.accion = 'cambio_estado_venta'
                    ORDER BY a.id DESC LIMIT 1) AS fecha_cambio_estado,
                   v.asesor_id as usuario_id,
                   p.modelo as modelo_producto, p.codigo as codigo_producto, p.descripcion as desc_producto,
                   u.nombre as nombre_asesor, u.apellido as apellido_asesor, u.cedula as cedula_asesor,
                   u.nombre_distribuidor, u.llave_breb, u.ciudad_residencia, u.departamento
            FROM ventas v
            JOIN productos_jlc p ON v.producto_id = p.id
            JOIN usuarios u ON v.asesor_id = u.id";

    // Si el usuario NO es admin, filtrar solo sus ventas
    $params = [];
    if ($user['rol'] !== 'admin' && $user['rol'] !== 'administrador') {
        $sql .= " WHERE v.asesor_id = :asesor_id";
        $params[':asesor_id'] = $user['user_id'];
    }

    $sql .= " ORDER BY v.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar foto_factura: devolver solo el basename del archivo.
    // El frontend obtiene la URL de acceso llamando a POST /api/files/signed_url.php.
    // Esto evita embeber el JWT de sesión en las URLs de la respuesta.
    foreach ($ventas as &$venta) {
        if (!empty($venta['foto_factura'])) {
            $venta['foto_factura'] = basename($venta['foto_factura']);
        }
    }
    unset($venta); // Romper referencia

    http_response_code(200);
    echo json_encode(['status' => 200, 'data' => $ventas]);
} catch (PDOException $e) {
    error_log("Error listando ventas: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Error al obtener historial']);
}
