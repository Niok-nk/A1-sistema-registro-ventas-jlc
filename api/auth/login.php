<?php
require_once __DIR__ . '/../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Rate limiting por IP (Login) ─────────────────────────────────────────────
// Máximo 10 intentos por IP en una ventana de 15 minutos
(function () {
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipHash   = hash('sha256', $ip); // No guardar IPs crudas
    $limit    = 10;
    $windowSec = 900; // 15 minutos

    try {
        require_once __DIR__ . '/../config/database.php';
        $db   = Database::getInstance();
        $conn = $db->getConnection();

        // Crear tabla si no existe (se ejecuta una sola vez)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_login (
                ip_hash   TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )
        ");

        $since = time() - $windowSec;

        // Limpiar registros viejos
        $conn->prepare("DELETE FROM rate_limit_login WHERE created_at < :since")
             ->execute([':since' => $since]);

        // Contar intentos recientes de esta IP
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_limit_login WHERE ip_hash = :h AND created_at >= :since");
        $stmt->execute([':h' => $ipHash, ':since' => $since]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $limit) {
            http_response_code(429);
            echo json_encode([
                'status'  => 429,
                'message' => 'Demasiados intentos fallidos. Por seguridad, espera 15 minutos antes de intentar nuevamente.',
            ]);
            exit;
        }

        // Se registra el intento. Si el login es exitoso, podríamos borrarlo, pero
        // para simplificar y evitar fuerza bruta, contamos cada intento (exitoso o no).
        $conn->prepare("INSERT INTO rate_limit_login (ip_hash, created_at) VALUES (:h, :t)")
             ->execute([':h' => $ipHash, ':t' => time()]);

    } catch (Exception $e) {
        // Si falla el rate limiting, registrar pero dejar pasar el login
        error_log('rate_limit_login error: ' . $e->getMessage());
    }
})();
// ────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../controllers/AuthController.php';

$result = null;

try {
    $auth = new AuthController();

    // Obtener datos del cuerpo de la solicitud JSON
    $data = json_decode(file_get_contents("php://input"), true);

    // Fallback para x-www-form-urlencoded
    if (is_null($data)) {
        $data = $_POST;
    }

    $result = $auth->login($data);
} catch (Throwable $e) {
    error_log("Login fatal error: " . $e->getMessage());
    $result = ['status' => 500, 'message' => 'Error interno del servidor. Por favor contacte al administrador.'];
}

http_response_code($result['status']);
echo json_encode($result);
