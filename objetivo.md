# Sistema de Registro de Ventas JLC

## Documento de Arquitectura y Especificación Técnica

**Versión:** 1.0  
**Fecha:** Diciembre 2025  
**Cliente:** Distribuidores JLC  
**Alcance:** Sistema web para gestión de ventas de asesores

---

## 1. RESUMEN EJECUTIVO

### 1.1 Objetivo del Sistema

Desarrollar una aplicación web para registrar y gestionar las ventas realizadas por asesores de distribución JLC en Colombia, facilitando el seguimiento, control y generación de reportes de la actividad comercial.

### 1.2 Stack Tecnológico Seleccionado

**Frontend:**
- Astro (generación de sitios estáticos)
- JavaScript nativo para interactividad
- CSS

**Backend:**
- PHP 8.x (nativo en Hostinger)
- JWT (JSON Web Tokens) para autenticación
- PDO para operaciones de base de datos

**Base de Datos:**
- MySQL 8.0 (Producción en Hostinger)
- SQLite (Desarrollo local)
- Sistema híbrido con abstracción de capa de datos
- Gestión: phpMyAdmin (Hostinger) / SQLite Browser (Local)

**Infraestructura:**
- Hosting: Hostinger Colombia
- Versionado: GitHub
- Deploy: GitHub Actions (automático vía FTP)
- Almacenamiento de archivos: servidor Hostinger

### 1.3 Capacidad del Sistema

- **Usuarios concurrentes:** 100-150 sin optimizaciones adicionales
- **Tiempo de respuesta objetivo:** < 1 segundo
- **Disponibilidad:** 99.5% (con infraestructura Hostinger)
- **Almacenamiento de imágenes:** Según plan Hostinger

---

## 2. ARQUITECTURA DEL SISTEMA

### 2.1 Diagrama de Arquitectura

```
┌────────────────────────────────────────────────────────────┐
│                    USUARIOS (100 asesores)                 │
│                         Colombia 🇨🇴                        │
└──────────────────────┬─────────────────────────────────────┘
                       │ HTTPS
                       ▼
┌────────────────────────────────────────────────────────────┐
│                   HOSTINGER COLOMBIA                       │
│                                                            │
│  ┌──────────────────────────────────────────────────────┐ │
│  │              FRONTEND (Astro Static)                 │ │
│  │  • HTML/CSS/JS optimizado                            │ │
│  │  • Páginas pre-renderizadas                          │ │
│  │  • Assets comprimidos                                │ │
│  └───────────────────┬──────────────────────────────────┘ │
│                      │                                     │
│                      │ Fetch API                           │
│                      ▼                                     │
│  ┌──────────────────────────────────────────────────────┐ │
│  │                  BACKEND PHP                         │ │
│  │                                                      │ │
│  │  ┌─────────────────────────────────────────────┐   │ │
│  │  │         CAPA DE AUTENTICACIÓN               │   │ │
│  │  │  • JWT Token validation                     │   │ │
│  │  │  • Role-based access control                │   │ │
│  │  │  • Session management                       │   │ │
│  │  └─────────────────────────────────────────────┘   │ │
│  │                                                      │ │
│  │  ┌─────────────────────────────────────────────┐   │ │
│  │  │          LÓGICA DE NEGOCIO                  │   │ │
│  │  │  • Registro de usuarios                     │   │ │
│  │  │  • Gestión de ventas                        │   │ │
│  │  │  • Generación de reportes                   │   │ │
│  │  │  • Validación de datos                      │   │ │
│  │  └─────────────────────────────────────────────┘   │ │
│  │                                                      │ │
│  │  ┌─────────────────────────────────────────────┐   │ │
│  │  │          CAPA DE DATOS (PDO)                │   │ │
│  │  │  • Abstracción MySQL/SQLite                 │   │ │
│  │  │  • Prepared statements                      │   │ │
│  │  │  • Transaction management                   │   │ │
│  │  └──────────────────┬──────────────────────────┘   │ │
│  └─────────────────────┼──────────────────────────────┘ │
│                        │                                 │
│                        ▼                                 │
│  ┌──────────────────────────────────────────────────────┐ │
│  │              BASE DE DATOS MySQL/SQLite              │ │
│  │                                                      │ │
│  │  • Usuarios (asesores + admin)                      │ │
│  │  • Ventas (con fotos de facturas)                   │ │
│  │  • Productos JLC                                    │ │
│  │  • Sesiones                                         │ │
│  │  • Auditoría                                        │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  ┌──────────────────────────────────────────────────────┐ │
│  │            ALMACENAMIENTO DE ARCHIVOS                │ │
│  │  /uploads/facturas/                                 │ │
│  │  • Fotos de facturas (JPG, PNG, PDF)               │ │
│  │  • Máximo 5MB por archivo                          │ │
│  └──────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────┘
                           │
                           │ GitHub Actions
                           │ (Deploy automático)
                           ▼
                    ┌──────────────┐
                    │    GITHUB    │
                    │  Repository  │
                    └──────────────┘
```

### 2.2 Flujo de Autenticación

```
1. Usuario ingresa número de documento + contraseña
   ↓
2. Frontend envía credenciales a /api/auth/login.php
   ↓
3. Backend valida en BD (password_verify)
   ↓
4. Si válido: genera JWT con datos del usuario
   ↓
5. Frontend almacena JWT en localStorage
   ↓
6. Todas las peticiones incluyen: Authorization: Bearer {token}
   ↓
7. Middleware valida JWT en cada request
   ↓
8. Si token válido: permite acceso
   Si token inválido: retorna 401 Unauthorized
```

### 2.3 Estructura de Carpetas

```
jlc-ventas/
│
├── src/                          # Frontend Astro
│   ├── pages/                    # Páginas del sitio
│   ├── components/               # Componentes reutilizables
│   ├── layouts/                  # Plantillas de diseño
│   └── styles/                   # CSS global
│
├── api/                          # Backend PHP
│   ├── config/                   # Configuraciones
│   │   ├── database.php          # Conexión MySQL/SQLite
│   │   ├── jwt.php               # Manejo de tokens
│   │   └── constants.php         # Constantes del sistema
│   │
│   ├── middleware/               # Capas de seguridad
│   │   ├── auth.php              # Verificar autenticación
│   │   └── admin.php             # Verificar rol admin
│   │
│   ├── controllers/              # Lógica de negocio
│   │   ├── AuthController.php
│   │   ├── UsuarioController.php
│   │   └── VentaController.php
│   │
│   ├── models/                   # Modelos de datos
│   │   ├── Usuario.php
│   │   └── Venta.php
│   │
│   ├── utils/                    # Utilidades
│   │   ├── Validator.php         # Validación de datos
│   │   ├── FileUpload.php        # Manejo de archivos
│   │   └── ExcelExport.php       # Exportación de reportes
│   │
│   └── routes/                   # Endpoints API
│       ├── auth.php              # /api/auth/*
│       ├── usuarios.php          # /api/usuarios/*
│       ├── ventas.php            # /api/ventas/*
│       └── reportes.php          # /api/reportes/*
│
├── database/                     # Scripts de base de datos
│   ├── schema.sql                # Estructura MySQL
│   ├── schema.sqlite             # Estructura SQLite
│   ├── migrations/               # Migraciones versionadas
│   └── seeds/                    # Datos iniciales
│
├── uploads/                      # Archivos subidos
│   └── facturas/                 # Fotos de facturas
│
└── .github/workflows/            # Automatización
    └── deploy.yml                # Deploy a Hostinger
```

---

## 3. BASE DE DATOS

### 3.1 Modelo de Datos

**Tabla: usuarios**

```sql
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Autenticación
    numero_documento VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('asesor', 'administrador') DEFAULT 'asesor',
    activo BOOLEAN DEFAULT TRUE,
    
    -- Información Personal
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    tipo_documento ENUM('CC', 'CE', 'TI', 'Pasaporte') NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    ciudad_residencia VARCHAR(100) NOT NULL,
    departamento VARCHAR(100) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(150) UNIQUE NOT NULL,
    
    -- Información de Distribuidor
    nombre_distribuidor VARCHAR(200) NOT NULL,
    ciudad_punto_venta VARCHAR(100) NOT NULL,
    direccion_punto_venta VARCHAR(255),
    cargo VARCHAR(100) NOT NULL,
    antiguedad_meses INT NOT NULL,
    
    -- Información Financiera
    llave_breb VARCHAR(100) NOT NULL COMMENT 'Debe coincidir con nombre para pago de bonos',
    
    -- Políticas y Permisos
    acepta_tratamiento_datos BOOLEAN NOT NULL DEFAULT FALSE,
    acepta_contacto_comercial BOOLEAN NOT NULL DEFAULT FALSE,
    declara_info_verdadera BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_rol_activo (rol, activo),
    INDEX idx_distribuidor (nombre_distribuidor),
    INDEX idx_ciudad (ciudad_punto_venta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Observación Crítica sobre Llave BRE-B:**
- La llave BRE-B debe coincidir exactamente con el nombre del asesor
- Si no coincide, no se realizarán los pagos de bonos
- El sistema debe validar esta coincidencia antes de registrar

**Tabla: productos_jlc**

```sql
CREATE TABLE productos_jlc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modelo VARCHAR(100) UNIQUE NOT NULL,
    descripcion VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Tabla: ventas**

```sql
CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Referencias
    asesor_id INT NOT NULL,
    producto_id INT NOT NULL,
    
    -- Datos de la venta
    numero_factura VARCHAR(50) NOT NULL,
    foto_factura VARCHAR(255) NOT NULL COMMENT 'Path relativo al archivo',
    numero_serie VARCHAR(100) NOT NULL COMMENT 'Debe coincidir exactamente con el producto',
    fecha_venta DATE NOT NULL,
    
    -- Estado
    estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
    observaciones TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Claves foráneas
    FOREIGN KEY (asesor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos_jlc(id),
    
    -- Índices para búsquedas
    INDEX idx_asesor_fecha (asesor_id, fecha_venta DESC),
    INDEX idx_fecha (fecha_venta DESC),
    INDEX idx_numero_factura (numero_factura),
    INDEX idx_estado (estado),
    
    -- Restricción: un asesor no puede repetir el mismo número de factura
    UNIQUE KEY unique_asesor_factura (asesor_id, numero_factura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Observación Crítica sobre Número de Serie:**
- El número de serie debe ingresarse exactamente como aparece en el producto
- Cualquier variación invalidará la redención del bono
- El sistema debe validar formato y caracteres especiales

**Tabla: sesiones**

```sql
CREATE TABLE sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token_hash VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token_hash),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Tabla: auditoria**

```sql
CREATE TABLE auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50),
    registro_id INT,
    datos_anteriores TEXT COMMENT 'JSON',
    datos_nuevos TEXT COMMENT 'JSON',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_fecha (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Sistema Híbrido MySQL/SQLite

**Abstracción de Capa de Datos:**

```php
// database.php
class DatabaseFactory {
    public static function getConnection() {
        $env = getenv('APP_ENV') ?? 'development';
        
        if ($env === 'production') {
            return self::getMySQLConnection();
        } else {
            return self::getSQLiteConnection();
        }
    }
    
    private static function getMySQLConnection() {
        $host = getenv('DB_HOST');
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        
        return new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    private static function getSQLiteConnection() {
        return new PDO(
            'sqlite:' . __DIR__ . '/../../database/local.db',
            null,
            null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
```

**Consideraciones:**
- Usar tipos de datos compatibles entre MySQL y SQLite
- Evitar sintaxis específica de cada motor
- Probar migraciones en ambos sistemas
- Mantener esquemas sincronizados

### 3.3 Optimizaciones de Base de Datos

**Índices Críticos:**

```sql
-- Búsquedas frecuentes en historial de ventas
CREATE INDEX idx_ventas_busqueda ON ventas(numero_factura, numero_serie, fecha_venta);

-- Filtrado por rangos de fechas
CREATE INDEX idx_ventas_rango_fecha ON ventas(fecha_venta, estado);

-- Búsqueda por asesor en reportes
CREATE INDEX idx_ventas_asesor_completo ON ventas(asesor_id, fecha_venta, estado);

-- Exportación de reportes por distribuidor
CREATE INDEX idx_usuarios_distribuidor_ciudad ON usuarios(nombre_distribuidor, ciudad_punto_venta);
```

**Impacto:** Reduce queries de reportes de 3-5s → 200-500ms

---

## 4. FUNCIONALIDADES DEL SISTEMA

### 4.1 Módulo de Registro de Usuarios

**Página:** `/registro`

**Formulario Completo:**

```
┌─────────────────────────────────────────────────────────────┐
│  Registro de Asesor JLC                                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  SECCIÓN 1: INFORMACIÓN PERSONAL                            │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Nombre: [_________________________] *                      │
│  Apellido: [_______________________] *                      │
│                                                             │
│  Tipo de Documento: [▼ Seleccionar] *                      │
│  ├─ Cédula de Ciudadanía (CC)                              │
│  ├─ Cédula de Extranjería (CE)                             │
│  ├─ Tarjeta de Identidad (TI)                              │
│  └─ Pasaporte                                               │
│                                                             │
│  Número de Documento: [_______________] *                   │
│  (Este será tu usuario de acceso)                           │
│                                                             │
│  Fecha de Nacimiento: [📅 DD/MM/AAAA] *                    │
│                                                             │
│  Ciudad de Residencia: [_______________] *                  │
│  Departamento: [_______________________] *                  │
│                                                             │
│  WhatsApp: [+57 _________________] *                        │
│  Teléfono: [________________________]                       │
│                                                             │
│  Correo Electrónico: [_________________] *                  │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  SECCIÓN 2: INFORMACIÓN DE DISTRIBUIDOR                     │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Nombre del Distribuidor: [_______________] *               │
│  Ciudad del Punto de Venta: [_____________] *               │
│  Dirección Punto de Venta: [_______________]                │
│  (Opcional)                                                 │
│                                                             │
│  Cargo: [______________________________] *                  │
│                                                             │
│  Antigüedad en el Distribuidor:                             │
│  [___] meses *                                              │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  SECCIÓN 3: INFORMACIÓN FINANCIERA                          │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Método de Pago Preferido: [▼ Seleccionar] *               │
│  ├─ Nequi                                                   │
│  ├─ Daviplata                                               │
│  ├─ Bancolombia                                             │
│  └─ Otro                                                    │
│                                                             │
│  Llave BRE-B: [_________________________] *                 │
│  ⚠️ IMPORTANTE: La llave debe coincidir con tu nombre       │
│     completo o no se realizarán pagos de bonos              │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  SECCIÓN 4: ACEPTACIÓN DE POLÍTICAS                         │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  [✓] Acepto el tratamiento de datos personales *            │
│      [Ver política completa]                                │
│                                                             │
│  [✓] Acepto recibir contacto comercial *                    │
│                                                             │
│  [✓] Declaro que toda la información es verdadera *         │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Contraseña: [_________________________] *                  │
│  Confirmar Contraseña: [_______________] *                  │
│  (Mínimo 8 caracteres, al menos 1 número)                   │
│                                                             │
│  [Cancelar]  [Registrar Cuenta]                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Flujo de Registro:**

1. Usuario completa todas las secciones del formulario
2. Frontend valida en tiempo real:
   - Número de documento: formato válido según tipo
   - Email: formato estándar
   - WhatsApp: formato colombiano
   - Fecha nacimiento: mayor de 18 años
   - Llave BRE-B: advertencia de coincidencia con nombre
   - Contraseña: mínimo 8 caracteres, 1 número
   - Políticas: todas marcadas
3. Backend recibe datos en `/api/auth/register.php`:
   - Re-valida todos los campos
   - Verifica unicidad de número de documento
   - Verifica unicidad de correo
   - Hash de contraseña con `password_hash()`
   - Inserta en tabla `usuarios`
   - Genera JWT token
4. Usuario redirigido a dashboard según rol

**Validaciones Críticas:**

```javascript
// Frontend - Validación de Llave BRE-B
function validarLlaveBREB(nombre, apellido, llave) {
    const nombreCompleto = `${nombre} ${apellido}`.toLowerCase();
    const llaveNormalizada = llave.toLowerCase();
    
    if (nombreCompleto !== llaveNormalizada) {
        mostrarAdvertencia(
            "⚠️ La llave BRE-B no coincide con tu nombre completo. " +
            "Esto impedirá el pago de bonos."
        );
        return false;
    }
    return true;
}

// Backend - Validación de edad
function validarEdadMinima($fechaNacimiento) {
    $edad = (new DateTime())->diff(new DateTime($fechaNacimiento))->y;
    return $edad >= 18;
}
```

### 4.2 Módulo de Autenticación

**Login (Página: `/login`)**

```
┌──────────────────────────────────────────┐
│  Iniciar Sesión - JLC Ventas             │
├──────────────────────────────────────────┤
│                                          │
│  Número de Documento: [_______________]  │
│                                          │
│  Contraseña: [________________________]  │
│                                          │
│  [✓] Recordarme                          │
│                                          │
│  [Iniciar Sesión]                        │
│                                          │
│  ¿No tienes cuenta? [Registrarse]       │
│  [¿Olvidaste tu contraseña?]            │
│                                          │
└──────────────────────────────────────────┘
```

**Proceso de Login:**

1. Usuario ingresa número de documento y contraseña
2. POST a `/api/auth/login.php`
3. Backend valida contra tabla `usuarios`
4. Si válido: genera JWT (válido 8 horas)
5. Redirige según rol:
   - Asesor → `/dashboard/asesor`
   - Administrador → `/dashboard/admin`

**Seguridad:**
- Límite de 5 intentos fallidos por IP/hora
- Registro en auditoría de intentos fallidos
- Tokens con expiración automática

### 4.3 Dashboard de Asesor

**Página:** `/dashboard/asesor`

```
┌───────────────────────────────────────────────────────────────┐
│  🏠 Dashboard - Juan Pérez                      [Cerrar Sesión]│
├───────────────────────────────────────────────────────────────┤
│                                                               │
│  Distribuidor: JLC Pasto                                      │
│  Ventas este mes: 24        Ventas totales: 187              │
│                                                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │  [+ NUEVA VENTA]                                         │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                               │
│  ┌───────────────────────────────────────────────────────────┐
│  │  HISTORIAL DE MIS VENTAS                                 │
│  ├───────────────────────────────────────────────────────────┤
│  │                                                           │
│  │  Buscar: [_________________] 🔍                           │
│  │                                                           │
│  │  Filtros:                                                 │
│  │  Desde: [📅] Hasta: [📅]   Estado: [▼ Todos]            │
│  │  Producto: [▼ Todos]                                     │
│  │                                                           │
│  │  [Exportar SVG] [Exportar Excel] [Exportar PDF]         │
│  │                                                           │
│  ├───┬──────────┬─────────┬───────────┬────────┬───────────┤
│  │ # │  Fecha   │ Factura │ Producto  │ Serie  │ Acciones  │
│  ├───┼──────────┼─────────┼───────────┼────────┼───────────┤
│  │ 1 │15/12/24  │ F-12345 │ JLC-2024A │ SN1234 │[Ver foto] │
│  │ 2 │14/12/24  │ F-12344 │ JLC-2024B │ SN1233 │[Ver foto] │
│  │ 3 │13/12/24  │ F-12343 │ JLC-2024A │ SN1232 │[Ver foto] │
│  │...│   ...    │   ...   │    ...    │  ...   │    ...    │
│  └───┴──────────┴─────────┴───────────┴────────┴───────────┘
│                                                               │
│  Mostrando 1-20 de 187 registros    [1] [2] [3] ... [10]    │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

**Funcionalidades:**

- **Búsqueda:** Por número de factura, producto, número de serie
- **Filtros:** 
  - Rango de fechas (desde/hasta)
  - Estado (pendiente, aprobada, rechazada)
  - Producto JLC
- **Exportación:** SVG, Excel, PDF con sus propias ventas
- **Vista de foto:** Modal para ver imagen de factura en tamaño completo

### 4.4 Registro de Nueva Venta

**Página:** `/ventas/nueva`

```
┌──────────────────────────────────────────────────────────┐
│  Registrar Nueva Venta                   [← Volver]      │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  N° de Factura: [___________________________] *          │
│                                                          │
│  Foto de Factura: *                                      │
│  ┌────────────────────────────────────────────────────┐ │
│  │                                                    │ │
│  │         📁 Arrastrar archivo aquí                  │ │
│  │         o hacer clic para seleccionar              │ │
│  │                                                    │ │
│  │   Formatos aceptados: JPG, PNG, PDF               │ │
│  │   Tamaño máximo: 5MB                               │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  Producto JLC: [▼ Seleccionar Producto] *                │
│  ├─ JLC-2024-A1                                          │
│  ├─ JLC-2024-A2                                          │
│  ├─ JLC-2024-B1                                          │
│  └─ ... (catálogo completo)                              │
│                                                          │
│  N° de Serie: [___________________________] *            │
│  ⚠️ IMPORTANTE: Ingrese el número exactamente como       │
│     aparece en el producto. Cualquier variación          │
│     invalidará la redención del bono.                    │
│                                                          │
│  Fecha de Venta: [📅 DD/MM/AAAA] *                      │
│                                                          │
│  ─────────────────────────────────────────────────────  │
│                                                          │
│  [Cancelar]  [Registrar Venta]                          │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

**Proceso de Registro:**

1. Usuario completa formulario
2. Selecciona o arrastra archivo de factura
3. Frontend valida:
   - Todos los campos obligatorios completos
   - Formato de archivo (JPG, PNG, PDF)
   - Tamaño máximo 5MB
   - Formato de número de serie
4. POST multipart/form-data a `/api/ventas/crear.php`
5. Backend procesa:
   - Valida sesión del asesor
   - Verifica que número de factura no esté duplicado
   - Valida datos de la venta
   - Sube archivo a `/uploads/facturas/{asesor_id}/{timestamp}_{filename}`
   - Inserta registro en tabla `ventas`
   - Registra acción en auditoría
6. Frontend muestra confirmación
7. Redirige a historial de ventas

**Validaciones Backend:**

```php
// Validación de número de factura único por asesor
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM ventas 
    WHERE asesor_id = ? AND numero_factura = ?
");
$stmt->execute([$asesorId, $numeroFactura]);

if ($stmt->fetchColumn() > 0) {
    throw new Exception("Ya has registrado una venta con este número de factura");
}

// Validación de archivo
$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
$fileType = mime_content_type($_FILES['foto']['tmp_name']);

if (!in_array($fileType, $allowed)) {
    throw new Exception("Formato de archivo no permitido");
}

if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
    throw new Exception("El archivo excede el tamaño máximo de 5MB");
}

// Validación de número de serie (formato alfanumérico)
if (!preg_match('/^[A-Z0-9\-]+$/i', $numeroSerie)) {
    throw new Exception("Número de serie con formato inválido");
}

// Validación de fecha (no puede ser futura)
$fechaVenta = new DateTime($fechaVentaInput);
$hoy = new DateTime();

if ($fechaVenta > $hoy) {
    throw new Exception("La fecha de venta no puede ser futura");
}
```

### 4.5 Dashboard de Administrador

**Página:** `/dashboard/admin`

```
┌─────────────────────────────────────────────────────────────────┐
│  🏠 Dashboard Administrador              [Cerrar Sesión]        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ESTADÍSTICAS DEL SISTEMA                                       │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐ │
│  │   Total      │    Ventas    │    Ventas    │   Ventas     │ │
│  │  Asesores    │     Hoy      │   Este Mes   │   Totales    │ │
│  │     127      │      45      │    1,234     │   12,456     │ │
│  └──────────────┴──────────────┴──────────────┴──────────────┘ │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────────┐
│  │  PANEL DE FILTROS Y BÚSQUEDA                                │
│  ├─────────────────────────────────────────────────────────────┤
│  │                                                             │
│  │  Buscar por: [_____________________________] 🔍            │
│  │  (Asesor, N° Factura, N° Serie, Producto...)               │
│  │                                                             │
│  │  Rango de Fechas:                                           │
│  │  Desde: [📅 __/__/____] Hasta: [📅 __/__/____]             │
│  │                                                             │
│  │  Asesor: [▼ Todos los asesores]                            │
│  │  Distribuidor: [▼ Todos los distribuidores]                │
│  │  Ciudad: [▼ Todas las ciudades]                             │
│  │  Producto: [▼ Todos los productos]                          │
│  │  Estado: [▼ Todos los estados]                              │
│  │                                                             │
│  │  [Limpiar Filtros]  [Aplicar]                              │
│  │                                                             │
│  │  ───────────────────────────────────────────────────────── │
│  │                                                             │
│  │  EXPORTAR RESULTADOS:                                       │
│  │  [📊 Exportar SVG] [📗 Exportar Excel] [📄 Exportar PDF]   │
│  │                                                             │
│  └─────────────────────────────────────────────────────────────┘
│                                                                 │
│  ┌─────────────────────────────────────────────────────────────┐
│  │  HISTORIAL DE VENTAS (TODAS)                                │
│  ├─────────────────────────────────────────────────────────────┤
│  │                                                             │
│  │  [Gestionar Usuarios] [Ver Auditoría]                      │
│  │                                                             │
│  ├──┬────────┬─────────┬──────────────┬───────────┬──────────┤
│  │# │ Fecha  │ Asesor  │ Distribuidor │  Factura  │ Acciones │
│  ├──┼────────┼─────────┼──────────────┼───────────┼──────────┤
│  │1 │15/12/24│Juan P.  │JLC Pasto     │F-12345    │[Ver][📷]│
│  │2 │15/12/24│María G. │JLC Bogotá    │F-12346    │[Ver][📷]│
│  │3 │14/12/24│Carlos R.│JLC Cali      │F-12344    │[Ver][📷]│
│  │  │        │         │              │           │          │
│  │  │  (Tabla extendida con más columnas al hacer scroll)    │
│  │  │  - Cédula - Ciudad - WhatsApp - Producto - Serie       │
│  │  │                                                         │
│  └──┴────────┴─────────┴──────────────┴───────────┴──────────┘
│                                                                 │
│  Mostrando 1-50 de 12,456 registros  [1] [2] [3] ... [249]    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Funcionalidades Exclusivas de Administrador:**

1. **Vista Global de Ventas:**
   - Acceso a todas las ventas de todos los asesores
   - Filtros avanzados por múltiples criterios
   - Búsqueda en tiempo real

2. **Gestión de Usuarios:**
   - Ver lista completa de asesores
   - Activar/desactivar cuentas
   - Ver estadísticas por asesor
   - Acceso a información completa de contacto

3. **Reportes Completos:**
   - Exportación con todos los datos
   - Reportes por distribuidor
   - Reportes por ciudad/región
   - Reportes por producto

4. **Auditoría:**
   - Ver registro de acciones del sistema
   - Rastrear cambios en ventas
   - Monitorear actividad de usuarios

### 4.6 Generación de Reportes Excel

**Endpoint:** `GET /api/reportes/excel.php`

**Parámetros de Query:**

```
?fecha_desde=2024-01-01
&fecha_hasta=2024-12-31
&asesor_id=123
&distribuidor=JLC+Pasto
&ciudad=Pasto
&producto_id=5
&estado=aprobada
```

**Proceso de Generación:**

1. Usuario (admin o asesor) aplica filtros deseados
2. Presiona botón "Exportar Excel"
3. Frontend construye URL con parámetros
4. Backend recibe request:
   - Valida autenticación
   - Si es asesor: filtra solo sus ventas
   - Si es admin: aplica filtros solicitados
5. Construye query SQL con filtros
6. Utiliza PHPSpreadsheet para generar Excel
7. Retorna archivo para descarga

**Estructura del Excel:**

**Para Administrador (reporte completo):**

```
┌────────────────────────────────────────────────────────────────┐
│  REPORTE DE VENTAS JLC                                         │
│  Período: 01/01/2024 - 31/12/2024                             │
│  Generado: 17/12/2024 14:30                                    │
├──┬──────┬────────┬─────────┬──────────┬────────┬──────────────┤
│A │  B   │   C    │    D    │    E     │   F    │      G       │
├──┼──────┼────────┼─────────┼──────────┼────────┼──────────────┤
│ID│N° Doc│ Nombre │Apellido │  Ciudad  │WhatsApp│    Correo    │
├──┼──────┼────────┼─────────┼──────────┼────────┼──────────────┤
│  │      │        │         │Residencia│        │              │
├──┴──────┴────────┴─────────┴──────────┴────────┴──────────────┤

┌──────────────┬─────────────┬────────────┬──────────┬──────────┐
│      H       │      I      │     J      │    K     │    L     │
├──────────────┼─────────────┼────────────┼──────────┼──────────┤
│ Distribuidor │   Ciudad    │Llave BRE-B │N° Factura│ Producto │
├──────────────┼─────────────┼────────────┼──────────┼──────────┤
│              │Punto Venta  │            │          │          │
├──────────────┴─────────────┴────────────┴──────────┴──────────┤

┌───────────┬─────────────┬──────────┐
│     M     │      N      │    O     │
├───────────┼─────────────┼──────────┤
│ N° Serie  │ Fecha Venta │  Estado  │
├───────────┼─────────────┼──────────┤
│           │             │          │
└───────────┴─────────────┴──────────┘
```

**Columnas del Excel:**

- **A:** ID Asesor (Número de Documento)
- **B:** Número de Documento
- **C:** Nombre
- **D:** Apellido
- **E:** Ciudad Residencia
- **F:** WhatsApp
- **G:** Correo
- **H:** Nombre Distribuidor
- **I:** Ciudad Punto de Venta
- **J:** Llave BRE-B
- **K:** N° Factura
- **L:** Producto
- **M:** N° Serie
- **N:** Fecha Venta
- **O:** Estado

**Formato del Archivo:**

```php
// Configuración del Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Título del reporte
$sheet->setCellValue('A1', 'REPORTE DE VENTAS JLC');
$sheet->mergeCells('A1:O1');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => 'center']
]);

// Información del período
$sheet->setCellValue('A2', "Período: $fechaDesde - $fechaHasta");
$sheet->setCellValue('A3', "Generado: " . date('d/m/Y H:i'));

// Headers (fila 5)
$headers = [
    'ID Asesor', 'N° Documento', 'Nombre', 'Apellido', 
    'Ciudad Residencia', 'WhatsApp', 'Correo',
    'Distribuidor', 'Ciudad Punto Venta', 'Llave BRE-B',
    'N° Factura', 'Producto', 'N° Serie', 'Fecha Venta', 'Estado'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '5', $header);
    $sheet->getStyle($col . '5')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => 'solid',
            'startColor' => ['rgb' => 'E0E0E0']
        ]
    ]);
    $col++;
}

// Autoajustar columnas
foreach (range('A', 'O') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Habilitar filtros
$sheet->setAutoFilter('A5:O5');

// Nombre del archivo
$filename = 'ventas_jlc_' . date('Ymd_His') . '.xlsx';
```

**Para Asesor (reporte personal):**

El asesor obtiene un Excel similar pero solo con sus propias ventas, sin información de otros asesores.

### 4.7 Gestión de Usuarios (Administrador)

**Página:** `/admin/usuarios`

```
┌─────────────────────────────────────────────────────────────────┐
│  👥 Gestión de Usuarios                 [← Volver al Dashboard] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Buscar usuario: [_____________________________] 🔍             │
│                                                                 │
│  Filtros:                                                       │
│  Estado: [▼ Todos] Distribuidor: [▼ Todos] Ciudad: [▼ Todas]  │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────────┐
│  │                     LISTA DE ASESORES                        │
│  ├──┬──────────┬─────────────┬──────────────┬────────┬────────┤
│  │ID│  Nombre  │Distribuidor │   Contacto   │ Ventas │Acciones│
│  ├──┼──────────┼─────────────┼──────────────┼────────┼────────┤
│  │1 │Juan Pérez│JLC Pasto    │3001234567    │  187   │[Ver]   │
│  │  │CC 1234567│Pasto        │juan@email.com│        │[Editar]│
│  │  │          │             │              │        │[🟢]    │
│  ├──┼──────────┼─────────────┼──────────────┼────────┼────────┤
│  │2 │María G.  │JLC Bogotá   │3009876543    │  245   │[Ver]   │
│  │  │CC 9876543│Bogotá       │maria@mail.com│        │[Editar]│
│  │  │          │             │              │        │[🟢]    │
│  ├──┼──────────┼─────────────┼──────────────┼────────┼────────┤
│  │3 │Carlos R. │JLC Cali     │3105551234    │   98   │[Ver]   │
│  │  │CC 5551234│Cali         │carlos@mai.co │        │[Editar]│
│  │  │          │             │              │        │[🔴]    │
│  └──┴──────────┴─────────────┴──────────────┴────────┴────────┘
│                                                                 │
│  Mostrando 1-20 de 127 usuarios     [1] [2] [3] ... [7]       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

LEYENDA:
🟢 - Usuario Activo
🔴 - Usuario Inactivo
```

**Detalle de Usuario:**

```
┌─────────────────────────────────────────────────────────────────┐
│  Perfil de Usuario: Juan Pérez                    [✏️ Editar]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  INFORMACIÓN PERSONAL                                           │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Nombre Completo: Juan Pérez                                    │
│  Tipo Doc: CC          N° Documento: 1234567890                │
│  Fecha Nacimiento: 15/03/1990        Edad: 34 años             │
│  Ciudad: Pasto, Nariño                                          │
│  WhatsApp: +57 300 123 4567                                     │
│  Teléfono: +57 (2) 7231234                                      │
│  Correo: juan.perez@email.com                                   │
│                                                                 │
│  INFORMACIÓN LABORAL                                            │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Distribuidor: JLC Pasto                                        │
│  Ciudad Punto de Venta: Pasto                                   │
│  Dirección: Calle 18 # 25-45                                    │
│  Cargo: Asesor Comercial Senior                                 │
│  Antigüedad: 24 meses                                           │
│                                                                 │
│  INFORMACIÓN FINANCIERA                                         │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Método de Pago: Nequi                                          │
│  Llave BRE-B: Juan Pérez  ✓ (Coincide)                         │
│                                                                 │
│  ESTADÍSTICAS                                                   │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Ventas Totales: 187                                            │
│  Ventas Este Mes: 24                                            │
│  Última Venta: 15/12/2024                                       │
│  Fecha Registro: 10/01/2023                                     │
│  Último Acceso: 17/12/2024 14:25                                │
│                                                                 │
│  ESTADO DE LA CUENTA                                            │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Estado: 🟢 Activo                                              │
│                                                                 │
│  [Ver Historial de Ventas]  [Desactivar Usuario]               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Acciones del Administrador:**

- Ver perfil completo del asesor
- Editar información (excepto número de documento)
- Activar/Desactivar cuenta
- Ver historial completo de ventas del asesor
- Resetear contraseña (envío por correo)
- Exportar datos del asesor

---

## 5. SEGURIDAD

### 5.1 Autenticación y Autorización

**JWT (JSON Web Tokens)**

Estructura del token:

```json
{
  "iss": "jlc-ventas",
  "aud": "jlc-users",
  "iat": 1703001600,
  "exp": 1703030400,
  "data": {
    "usuario_id": 123,
    "numero_documento": "1234567890",
    "rol": "asesor",
    "nombre": "Juan Pérez"
  }
}
```

**Características:**
- Algoritmo: HS256 (HMAC-SHA256)
- Validez: 8 horas
- Renovación automática al 50% de expiración
- Almacenamiento: localStorage (frontend)
- Transmisión: Header `Authorization: Bearer {token}`

**Implementación PHP:**

```php
// jwt.php
class JWTHandler {
    private static $secret;
    
    public static function generate($userData) {
        $header = base64_encode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]));
        
        $payload = base64_encode(json_encode([
            'iss' => 'jlc-ventas',
            'aud' => 'jlc-users',
            'iat' => time(),
            'exp' => time() + (8 * 3600), // 8 horas
            'data' => $userData
        ]));
        
        $signature = hash_hmac('sha256', 
            "$header.$payload", 
            self::getSecret(), 
            true
        );
        $signature = base64_encode($signature);
        
        return "$header.$payload.$signature";
    }
    
    public static function validate($token) {
        list($header, $payload, $signature) = explode('.', $token);
        
        $validSignature = hash_hmac('sha256',
            "$header.$payload",
            self::getSecret(),
            true
        );
        $validSignature = base64_encode($validSignature);
        
        if ($signature !== $validSignature) {
            throw new Exception('Token inválido');
        }
        
        $payloadData = json_decode(base64_decode($payload), true);
        
        if ($payloadData['exp'] < time()) {
            throw new Exception('Token expirado');
        }
        
        return $payloadData['data'];
    }
    
    private static function getSecret() {
        if (!self::$secret) {
            self::$secret = getenv('JWT_SECRET');
        }
        return self::$secret;
    }
}
```

**Control de Acceso Basado en Roles (RBAC)**

```php
// middleware/auth.php
function requireAuth() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no proporcionado']);
        exit;
    }
    
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    
    try {
        $userData = JWTHandler::validate($token);
        return $userData;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// middleware/admin.php
function requireAdmin() {
    $userData = requireAuth();
    
    if ($userData['rol'] !== 'administrador') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }
    
    return $userData;
}
```

**Matriz de Permisos:**

| Funcionalidad | Asesor | Administrador |
|---------------|--------|---------------|
| Ver propias ventas | ✅ | ✅ |
| Ver todas las ventas | ❌ | ✅ |
| Registrar venta | ✅ | ✅ |
| Editar propia venta | ✅* | ✅ |
| Eliminar venta | ❌ | ✅ |
| Ver perfil propio | ✅ | ✅ |
| Editar perfil propio | ✅** | ✅ |
| Ver otros perfiles | ❌ | ✅ |
| Gestionar usuarios | ❌ | ✅ |
| Exportar propias ventas | ✅ | ✅ |
| Exportar todas las ventas | ❌ | ✅ |
| Ver auditoría | ❌ | ✅ |

\* Solo dentro de 24 horas de registro  
\** Excepto número de documento y rol

### 5.2 Protección de Datos

**Contraseñas:**

```php
// Registro de usuario
$password = $_POST['password'];
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

// Almacenar $hash en base de datos

// Validación en login
$inputPassword = $_POST['password'];
$storedHash = $row['password']; // Desde BD

if (password_verify($inputPassword, $storedHash)) {
    // Contraseña correcta
    // Generar JWT
} else {
    // Contraseña incorrecta
    registrarIntentoFallido($numeroDocumento);
}
```

**Características:**
- Algoritmo: bcrypt
- Cost factor: 10 (1024 iteraciones)
- Nunca se almacenan en texto plano
- Nunca se transmiten en logs o respuestas API
- Rehashing automático si cost factor cambia

**SQL Injection Prevention:**

```php
// ❌ INCORRECTO - Vulnerable
$cedula = $_POST['cedula'];
$query = "SELECT * FROM usuarios WHERE numero_documento = '$cedula'";
$result = $pdo->query($query);

// ✅ CORRECTO - Seguro
$cedula = $_POST['cedula'];
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE numero_documento = ?");
$stmt->execute([$cedula]);
$result = $stmt->fetch();

// ✅ CORRECTO - Named parameters
$stmt = $pdo->prepare("
    SELECT * FROM usuarios 
    WHERE numero_documento = :cedula AND activo = :activo
");
$stmt->execute([
    'cedula' => $cedula,
    'activo' => true
]);
```

**Regla de Oro:** 100% de queries usan prepared statements

**XSS (Cross-Site Scripting) Prevention:**

```php
// Sanitización de outputs
function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Uso en respuestas JSON
$response = [
    'nombre' => sanitizeOutput($usuario['nombre']),
    'correo' => sanitizeOutput($usuario['correo'])
];

echo json_encode($response);
```

**Content Security Policy (CSP):**

```php
// Agregar en todas las páginas HTML
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data: https:; " .
    "font-src 'self'; " .
    "connect-src 'self';"
);
```

**CSRF (Cross-Site Request Forgery) Prevention:**

```php
// Generar token CSRF
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Incluir en formularios
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// Validar en backend
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die('Token CSRF inválido');
}
```

**File Upload Security:**

```php
function validarArchivoFactura($file) {
    // 1. Validar que el archivo existe
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir archivo');
    }
    
    // 2. Validar tamaño (5MB máximo)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('El archivo excede el tamaño máximo de 5MB');
    }
    
    // 3. Validar MIME type real (no confiar en extensión)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'application/pdf'
    ];
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Formato de archivo no permitido');
    }
    
    // 4. Validar extensión (doble verificación)
    $extension = strtolower(pathinfo($
