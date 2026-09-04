<?php
require_once __DIR__ . '/../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Rate limiting por IP ─────────────────────────────────────────────────────
// Máximo 5 registros por IP en una ventana de 10 minutos
(function () {
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipHash   = hash('sha256', $ip); // No guardar IPs crudas
    $limit    = 5;
    $windowSec = 600; // 10 minutos

    try {
        require_once __DIR__ . '/../config/database.php';
        $db   = Database::getInstance();
        $conn = $db->getConnection();

        // Crear tabla si no existe (se ejecuta una sola vez)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_registro (
                ip_hash   TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )
        ");

        $since = time() - $windowSec;

        // Limpiar registros viejos
        $conn->prepare("DELETE FROM rate_limit_registro WHERE created_at < :since")
             ->execute([':since' => $since]);

        // Contar intentos recientes de esta IP
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_limit_registro WHERE ip_hash = :h AND created_at >= :since");
        $stmt->execute([':h' => $ipHash, ':since' => $since]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $limit) {
            http_response_code(429);
            echo json_encode([
                'status'  => 429,
                'message' => 'Demasiadas solicitudes. Intenta de nuevo en unos minutos.',
            ]);
            exit;
        }

        // Registrar este intento
        $conn->prepare("INSERT INTO rate_limit_registro (ip_hash, created_at) VALUES (:h, :t)")
             ->execute([':h' => $ipHash, ':t' => time()]);

    } catch (Exception $e) {
        // Si falla el rate limiting, se deja pasar (no bloquear el registro por error interno)
        error_log('rate_limit_registro error: ' . $e->getMessage());
    }
})();
// ────────────────────────────────────────────────────────────────────────────

// Detectar cuando PHP trunca el POST por exceder post_max_size
// En ese caso $_POST y $_FILES quedan vacíos pero Content-Length está presente
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMaxSize   = (int)ini_get('post_max_size') * 1024 * 1024;
if ($contentLength > 0 && $postMaxSize > 0 && empty($_POST) && empty($_FILES) && $contentLength > $postMaxSize) {
    http_response_code(413);
    $maxMB = round($postMaxSize / 1024 / 1024, 0);
    echo json_encode([
        'status'  => 413,
        'message' => "Los archivos adjuntos superan el límite del servidor ({$maxMB} MB). Por favor reduzca el tamaño de los archivos e intente de nuevo."
    ]);
    exit;
}

require_once __DIR__ . '/../controllers/UsuarioController.php';

// Soporta multipart/form-data (con archivo) y application/json (sin archivo)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'multipart/form-data') !== false) {
    $data = $_POST;
    $boolFields = [
        'acepta_tratamiento_datos', 'acepta_contacto_comercial', 'declara_info_verdadera',
        'declara_naturaleza_comercial', 'reconoce_no_salario', 'declara_no_subordinacion',
        'declara_relacion_autonoma', 'acepta_liberalidades', 'asume_obligaciones_tributarias',
        'declara_no_contrato', 'acepta_terminos_programa'
    ];
    foreach ($boolFields as $field) {
        if (isset($data[$field])) {
            $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
        }
    }
} else {
    $data = json_decode(file_get_contents("php://input"), true);
    if (is_null($data)) {
        $data = $_POST;
    }
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'No se recibieron datos.']);
    exit;
}

// ── Helper para subir un archivo de certificado ──────────────────────────────
function subirCertificado(string $inputName): ?string {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // campo opcional
    }

    $file = $_FILES[$inputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'];
        $msg  = ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE)
            ? 'El archivo supera el tamaño máximo permitido.'
            : 'Error al subir el archivo. Código: ' . $code;
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => $msg]);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => "El archivo '$inputName' supera el tamaño máximo de 5 MB."]);
        exit;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);

    if (!in_array($realMime, $allowedMimes)) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => "Formato no permitido para '$inputName' (solo JPG, PNG o PDF). Detectado: $realMime"]);
        exit;
    }

    $extMap   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $ext      = $extMap[$realMime];
    $filename = $inputName . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $uploadDir = __DIR__ . '/../../uploads/certificados/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        http_response_code(500);
        echo json_encode(['status' => 500, 'message' => "No se pudo guardar el archivo '$inputName'."]);
        exit;
    }

    return $filename;
}

// ── Certificado Nequi (obligatorio) ─────────────────────────────────────────
if (!isset($_FILES['certificado']) || $_FILES['certificado']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'El certificado de cuenta Nequi es obligatorio.']);
    exit;
}
$data['certificado']     = subirCertificado('certificado');

// ── Certificado RUT (obligatorio) ───────────────────────────────────────────
if (!isset($_FILES['certificado_rut']) || $_FILES['certificado_rut']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'El certificado RUT es obligatorio.']);
    exit;
}
$data['certificado_rut'] = subirCertificado('certificado_rut');

$controller = new UsuarioController();
$result     = $controller->registrar($data);

http_response_code($result['status']);
echo json_encode($result);
