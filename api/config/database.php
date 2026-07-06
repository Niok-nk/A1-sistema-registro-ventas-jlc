<?php
/**
 * Database Connection Class
 * Sistema JLC - Gestión de conexión a MySQL
 * 
 * Patrón Singleton para reutilizar conexión
 * Lee variables de entorno desde .env
 * Connection pooling habilitado
 */

require_once __DIR__ . '/constants.php';

class Database {
    // Instancia única (Singleton)
    private static $instance = null;
    
    // Conexión PDO
    private $conn;
    
    // Configuración de conexión
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    
    /**
     * Constructor privado (Singleton)
     * Carga variables de entorno y establece conexión
     */
    private function __construct() {
        $this->loadEnv();
        $this->loadConfig();
        $this->connect();
    }
    
    /**
     * Cargar archivo .env
     * Busca en múltiples ubicaciones posibles
     */
    private function loadEnv() {
        // Ubicaciones posibles del archivo .env
        $possible_paths = [
            __DIR__ . '/../../.env',                     // Raíz del proyecto
            $_SERVER['DOCUMENT_ROOT'] . '/.env',         // Document root (Hostinger)
            dirname($_SERVER['DOCUMENT_ROOT']) . '/.env', // Un nivel arriba
            '/home/' . get_current_user() . '/.env'      // Home del usuario (por si acaso)
        ];
        
        $env_loaded = false;
        
        foreach ($possible_paths as $env_path) {
            if (file_exists($env_path) && is_readable($env_path)) {
                $this->parseEnvFile($env_path);
                $env_loaded = true;
                error_log("INFO: .env loaded from: " . $env_path);
                break;
            }
        }
        
        // Advertencia si no se encontró .env (usar defaults)
        if (!$env_loaded) {
            error_log("WARNING: .env file not found. Using default values.");
        }
    }
    
    /**
     * Parsear contenido del archivo .env
     * 
     * @param string $path Ruta al archivo .env
     */
    private function parseEnvFile($path) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            error_log("ERROR: Could not read .env file at: " . $path);
            return;
        }
        
        foreach ($lines as $line) {
            // Trim whitespace
            $line = trim($line);
            
            // Ignorar líneas vacías y comentarios
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Verificar que tenga '='
            if (strpos($line, '=') === false) {
                continue;
            }
            
            // Separar nombre y valor
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remover comillas si existen
            $value = trim($value, '"\'');
            
            // Establecer variable de entorno solo si no existe
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    
    /**
     * Cargar configuración desde variables de entorno
     * Usa valores por defecto si no están definidos
     */
    private function loadConfig() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'jlc_ventas';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        
        // Debug en desarrollo (comentar en producción)
        if (getenv('ENVIRONMENT') === 'development') {
            error_log("DEBUG DB Config - Host: {$this->host}, DB: {$this->db_name}, User: {$this->username}");
        }
    }
    
    /**
     * Establecer conexión a la base de datos
     * 
     * @throws Exception Si no puede conectar
     */
    private function connect() {
        $this->conn = null;
        
        try {
            $driver = getenv('DB_CONNECTION') ?: 'mysql';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Connection pooling
            ];

            if ($driver === 'sqlite') {
                $db_file = __DIR__ . '/../../database/database.sqlite';
                
                // Create directory if not exists
                if (!file_exists(dirname($db_file))) {
                    mkdir(dirname($db_file), 0755, true);
                }
                
                $dsn = "sqlite:" . $db_file;
                // SQLite specific options
                unset($options[PDO::ATTR_PERSISTENT]); // Persistent not recommended for SQLite file locks
                
                $this->conn = new PDO($dsn, null, null, $options);
                
                // Enable foreign keys for SQLite
                $this->conn->exec("PRAGMA foreign_keys = ON");
                
            } else {
                // MySQL (Default)
                $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
                $options[1002] = "SET NAMES {$this->charset}"; // PDO::MYSQL_ATTR_INIT_COMMAND
                
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
                $this->conn->exec("SET time_zone = '-05:00'");
            }
            
            // Log de éxito (solo en desarrollo)
            if (getenv('ENVIRONMENT') === 'development') {
                error_log("INFO: Database connection established successfully via {$driver}");
            }
            
        } catch(PDOException $exception) {
            // Log detallado del error
            error_log("DATABASE CONNECTION ERROR: " . $exception->getMessage());
            
            // En desarrollo, lanzar el error real para debugging
            if (getenv('ENVIRONMENT') === 'development' || !getenv('ENVIRONMENT')) {
                throw new Exception("Connection failed: " . $exception->getMessage());
            }

            // Lanzar excepción genérica (no exponer detalles al usuario)
            throw new Exception("Error de conexión a la base de datos. Por favor contacte al administrador.");
        }
    }
    
    /**
     * Obtener instancia única de Database (Singleton)
     * 
     * @return Database Instancia única
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener conexión PDO activa
     * Verifica que la conexión esté activa, reconecta si es necesario
     * 
     * @return PDO Conexión a base de datos
     */
    public function getConnection() {
        try {
            // Verificar si la conexión está activa
            if ($this->conn !== null) {
                $this->conn->query('SELECT 1');
            } else {
                // Si no hay conexión, crear una nueva
                $this->connect();
            }
        } catch (PDOException $e) {
            // Si la conexión se perdió, reconectar
            error_log("Connection lost, reconnecting...");
            $this->connect();
        }
        
        return $this->conn;
    }
    
    /**
     * Verificar si la base de datos está accesible
     * Útil para health checks
     * 
     * @return bool True si la conexión funciona
     */
    public function testConnection() {
        try {
            $db = $this->getConnection();
            $stmt = $db->query('SELECT 1');
            return $stmt !== false;
        } catch (Exception $e) {
            error_log("Database test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener información de la base de datos
     * Útil para debugging
     * 
     * @return array Información de la BD
     */
    public function getDatabaseInfo() {
        try {
            $db = $this->getConnection();
            
            $version = $db->query('SELECT VERSION()')->fetchColumn();
            $database = $db->query('SELECT DATABASE()')->fetchColumn();
            
            return [
                'connected' => true,
                'version' => $version,
                'database' => $database,
                'charset' => $this->charset,
                'host' => $this->host
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cerrar conexión explícitamente (opcional)
     * PHP cierra automáticamente al terminar el script
     */
    public function closeConnection() {
        $this->conn = null;
        error_log("INFO: Database connection closed");
    }
    
    /**
     * Prevenir clonación de la instancia (Singleton)
     */
    private function __clone() {
        throw new Exception("Cannot clone singleton Database instance");
    }
    
    /**
     * Prevenir deserialización de la instancia (Singleton)
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton Database instance");
    }
    
    /**
     * Destructor - se ejecuta al finalizar el script
     */
    public function __destruct() {
        // PDO cierra automáticamente, pero podemos hacer cleanup adicional
        if ($this->conn !== null && getenv('ENVIRONMENT') === 'development') {
            error_log("INFO: Database instance destroyed");
        }
    }
}