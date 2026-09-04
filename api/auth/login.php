<?php
require_once __DIR__ . '/../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    $result = ['status' => 500, 'message' => 'DB Error: ' . $e->getMessage()];
}

http_response_code($result['status']);
echo json_encode($result);
