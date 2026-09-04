<?php
require_once __DIR__ . '/../config/database.php';

class VentasController {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }

    public function crearVenta($data) {
        // Validar campos requeridos
        if (!isset($data['asesor_id']) || !isset($data['numero_factura']) || 
            !isset($data['producto_id']) || !isset($data['numero_serie']) || 
            !isset($data['fecha_venta']) || !isset($data['foto_factura']) || 
            empty($data['foto_factura'])) {
            return ['status' => 400, 'message' => 'Faltan datos requeridos. La foto de factura es obligatoria.'];
        }

        try {
            // Verificar si numero_factura ya existe (SQLite compatible)
            $check = $this->conn->prepare("SELECT id FROM ventas WHERE numero_factura = :numero_factura");
            $check->execute([':numero_factura' => $data['numero_factura']]);
            if ($check->fetch()) {
                 return ['status' => 409, 'message' => 'El número de factura ya existe'];
            }

            $sql = "INSERT INTO ventas (asesor_id, numero_factura, foto_factura, producto_id, numero_serie, fecha_venta, estado, created_at) 
                    VALUES (:asesor_id, :numero_factura, :foto_factura, :producto_id, :numero_serie, :fecha_venta, 'pendiente', :created_at)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':asesor_id' => $data['asesor_id'],
                ':numero_factura' => $data['numero_factura'],
                ':foto_factura' => $data['foto_factura'],
                ':producto_id' => $data['producto_id'],
                ':numero_serie' => $data['numero_serie'],
                ':fecha_venta' => $data['fecha_venta'],
                ':created_at' => $data['fecha_registro'] ?? date('Y-m-d H:i:s')
            ]);

            return ['status' => 201, 'message' => 'Venta registrada exitosamente'];

        } catch (PDOException $e) {
            error_log("Error creando venta: " . $e->getMessage());
            return ['status' => 500, 'message' => 'Error al registrar la venta'];
        }
    }
}
