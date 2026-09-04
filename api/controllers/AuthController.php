<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/JWT.php';

class AuthController {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }

    public function login($data) {
        // Validar datos de entrada
        if (!isset($data['cedula']) || !isset($data['password'])) {
            return ['status' => 400, 'message' => 'Faltan credenciales (cédula o contraseña)'];
        }

        $cedula = trim($data['cedula']);
        $password = $data['password'];

        try {
            // Buscar usuario
            $query = "SELECT id, cedula, password, rol, nombre, apellido, activo, estado_aprobacion FROM usuarios WHERE cedula = :cedula LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':cedula', $cedula);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Siempre ejecutar password_verify para que tarde igual si el usuario no existe
            // Esto previene timing attacks para enumerar cédulas
            $hash = $user ? $user['password'] : '$2y$10$invalidHashParaQueTardeIgualxxxxxxxxxxxxxxxxxxxxxx';
            $password_valid = password_verify($password, $hash);

            if ($user && $password_valid) {
                    if ($user['activo'] == 0) {
                        return ['status' => 403, 'message' => 'Usuario inactivo. Contacte al administrador.'];
                    }
                    
                    if ($user['estado_aprobacion'] !== 'aprobado') {
                        $mensaje = '';
                        if ($user['estado_aprobacion'] === 'pendiente') {
                            $mensaje = 'Tu cuenta está pendiente de aprobación por un administrador.';
                        } else if ($user['estado_aprobacion'] === 'rechazado') {
                            $mensaje = 'Tu solicitud de registro fue rechazada. Contacta al administrador.';
                        }
                        return ['status' => 403, 'message' => $mensaje];
                    }

                    // Generar Token
                    $token_payload = [
                        'user_id' => $user['id'],
                        'cedula'  => $user['cedula'],
                        'rol'     => $user['rol'],
                        'nombre'  => $user['nombre']
                    ];
                    
                    $jwt = JWT::generate($token_payload);

                    unset($user['password']);

                    // Emitir cookie HttpOnly — JS nunca puede leerla (protección XSS)
                    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    setcookie('auth_token', $jwt, [
                        'expires'  => time() + 86400,   // 24h igual que el JWT
                        'path'     => '/',
                        'secure'   => $isSecure,         // Solo HTTPS en producción
                        'httponly' => true,              // JS no puede leerla
                        'samesite' => 'Lax',            // Protección CSRF básica
                    ]);

                    // Registrar sesión en tabla sesiones (token_hash SHA-256 + expiración 24 h)
                    try {
                        $tokenHash  = hash('sha256', $jwt);
                        $expiresAt  = date('Y-m-d H:i:s', time() + 86400);
                        $sesionStmt = $this->conn->prepare(
                            "INSERT INTO sesiones (usuario_id, token_hash, expires_at)
                             VALUES (:uid, :hash, :exp)"
                        );
                        $sesionStmt->execute([
                            ':uid'  => $user['id'],
                            ':hash' => $tokenHash,
                            ':exp'  => $expiresAt,
                        ]);
                    } catch (Exception $se) {
                        error_log("Error registrando sesión: " . $se->getMessage());
                    }

                    // Se devuelve token en JSON para compatibilidad con frontend Bearer
                    // Y también se emite la cookie HttpOnly como capa de seguridad adicional
                    return [
                        'status'  => 200,
                        'message' => 'Login exitoso',
                        'token'   => $jwt,
                        'user'    => $user
                    ];
            }

            return ['status' => 401, 'message' => 'Credenciales inválidas'];

        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            return ['status' => 500, 'message' => 'Error del servidor procesando login'];
        }
    }
}
