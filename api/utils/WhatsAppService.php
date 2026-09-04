<?php
/**
 * WhatsApp Cloud API Service
 * Envía mensajes de plantilla a través de la API de WhatsApp Business (Meta Graph API).
 *
 * Credenciales (desde .env, nunca en código ni en logs):
 *   - WHATSAPP_TOKEN            Token de acceso (Bearer)
 *   - WHATSAPP_PHONE_NUMBER_ID  ID del número de teléfono
 *   - WHATSAPP_API_VERSION      Versión de la API (opcional, default v25.0)
 */

class WhatsAppService
{
    private string $token;
    private string $phoneNumberId;
    private string $apiVersion;

    public function __construct()
    {
        $this->token         = (string)(getenv('WHATSAPP_TOKEN') ?: '');
        $this->phoneNumberId = (string)(getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '');
        $this->apiVersion    = (string)(getenv('WHATSAPP_API_VERSION') ?: 'v25.0');
    }

    /**
     * Indica si las credenciales mínimas están configuradas.
     */
    private function isConfigured(): bool
    {
        return $this->token !== '' && $this->phoneNumberId !== '';
    }

    /**
     * Envía un mensaje de plantilla (template message).
     *
     * @param string $to           Número de destino (acepta formatos locales o internacionales).
     * @param string $templateName Nombre de la plantilla aprobada en Meta.
     * @param string $languageCode Código de idioma (ej: 'es', 'en').
     * @param array  $parameters   Valores para los parámetros del cuerpo (en orden).
     * @return array ['ok' => bool, 'message_id' => ?string, 'error' => ?string]
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode, array $parameters): array
    {
        if (!$this->isConfigured()) {
            error_log('WhatsApp: servicio no configurado (faltan WHATSAPP_TOKEN o WHATSAPP_PHONE_NUMBER_ID).');
            return ['ok' => false, 'error' => 'WhatsApp no configurado'];
        }

        $phone = $this->normalizePhone($to);
        if ($phone === null) {
            error_log('WhatsApp: número de teléfono inválido.');
            return ['ok' => false, 'error' => 'Número de teléfono inválido'];
        }

        $bodyParams = [];
        foreach ($parameters as $param) {
            $bodyParams[] = [
                'type' => 'text',
                'text' => $this->sanitizeParameter($param),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $languageCode],
                'components' => [
                    ['type' => 'body', 'parameters' => $bodyParams],
                ],
            ],
        ];

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId
        );

        return $this->post($url, $payload);
    }

    /**
     * Realiza el POST a la API. Usa cURL si está disponible y
     * file_get_contents (stream context) como respaldo.
     *
     * @return array ['ok' => bool, 'message_id' => ?string, 'error' => ?string]
     */
    private function post(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['ok' => false, 'error' => 'Error al codificar el mensaje'];
        }

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
        ];

        if (function_exists('curl_init')) {
            return $this->postWithCurl($url, $body, $headers);
        }
        return $this->postWithStream($url, $body, $headers);
    }

    private function postWithCurl(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            // No se loguea el token ni credenciales.
            error_log('WhatsApp cURL error: ' . $curlErr);
            return ['ok' => false, 'error' => 'Error de conexión con la API de WhatsApp'];
        }

        return $this->parseResponse($httpCode, $response);
    }

    private function postWithStream(string $url, string $body, array $headers): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers) . "\r\n",
                'content'       => $body,
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $err = error_get_last();
            error_log('WhatsApp HTTP error: ' . ($err['message'] ?? 'desconocido'));
            return ['ok' => false, 'error' => 'Error de conexión con la API de WhatsApp'];
        }

        return $this->parseResponse($this->getLastHttpStatus(), $response);
    }

    /**
     * Obtiene el código de estado HTTP de la última respuesta.
     * Compatible con PHP 8.5+ (http_get_last_response_headers) y versiones anteriores.
     */
    private function getLastHttpStatus(): int
    {
        if (function_exists('http_get_last_response_headers')) {
            $headers = http_get_last_response_headers();
        } else {
            // PHP < 8.5: leer $http_response_header indirectamente para evitar
            // la deprecación de compilación en versiones recientes.
            $name    = 'http_response_header';
            $headers = isset($$name) ? $$name : [];
        }

        foreach ((array) $headers as $line) {
            if (is_string($line) && preg_match('/\s(\d{3})\s/', $line, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    private function parseResponse(int $httpCode, string $response): array
    {
        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($data['messages'][0]['id'])) {
            return ['ok' => true, 'message_id' => $data['messages'][0]['id']];
        }

        $msg  = $data['error']['message'] ?? null;
        $code = $data['error']['code'] ?? $httpCode;
        // Solo se registra el mensaje de error y el código, nunca el token ni el payload completo.
        error_log('WhatsApp API error: ' . ($msg ?? ('HTTP ' . $httpCode)) . ' (code ' . $code . ')');
        return ['ok' => false, 'error' => $msg ?? ('HTTP ' . $httpCode)];
    }

    /**
     * Normaliza un número a formato internacional sin '+'.
     * Acepta: '+57 3001234567', '573001234567', '3001234567' (móvil colombiano).
     */
    private function normalizePhone(string $number): ?string
    {
        $digits = preg_replace('/\D/', '', $number);
        if ($digits === '' || strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        // Número local colombiano de 10 dígitos (móvil empieza en 3): anteponer 57.
        if (strlen($digits) === 10 && $digits[0] === '3') {
            return '57' . $digits;
        }

        return $digits;
    }

    /**
     * Sanitiza un parámetro de plantilla (siempre string, longitud acotada).
     */
    private function sanitizeParameter($value): string
    {
        $str = (string)$value;
        // Meta limita el texto de cada parámetro; truncar para evitar rechazos.
        return mb_substr($str, 0, 1024);
    }
}
