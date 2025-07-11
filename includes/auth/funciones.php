<?php
/**
 * Archivo de funciones de autenticación y utilidades
 * Versión mejorada con conexión a BD segura y funciones optimizadas
 */

// Verificar que config.php ya fue incluido
if (!defined('BASE_URL')) {
    die('Error: Configuración no cargada. Se requiere config.php');
}

/**
 * Muestra mensajes flash almacenados en la sesión
 * Soporta mensajes de éxito, error y advertencia
 */
function mostrarMensajes() {
    // Mensajes de éxito
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($_SESSION['success_message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['success_message']);
    }
    
    // Mensajes de error
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($_SESSION['error_message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['error_message']);
    }
    
    // Mostrar también errores directos si existen
    if (!empty($GLOBALS['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($GLOBALS['error']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
}

/**
 * Redirige a una ruta específica
 */
function redirigir($ruta = '', $absoluta = true) {
    if ($absoluta) {
        $url = BASE_URL . '/' . ltrim($ruta, '/');
    } else {
        $url = '/' . ltrim($ruta, '/');
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL) && $absoluta) {
        error_log("Intento de redirección a URL inválida: $url");
        die("Error en redirección");
    }
    
    header("Location: $url");
    exit();
}

/**
 * Genera y devuelve un token CSRF
 */
function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF
 */
function verificarTokenCSRF($token) {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Verificar tiempo de vida del token (opcional)
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
        unset($_SESSION['csrf_token']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Genera hash de contraseña segura
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifica una contraseña contra su hash
 */
function verificarPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Sanitiza input para prevenir XSS
 */
function sanitizarInput($input, $allow_html = false) {
    if (is_array($input)) {
        return array_map('sanitizarInput', $input);
    }
    return $allow_html ? trim($input) : htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera un token seguro
 */
function generarToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Verifica si hay una sesión activa de usuario
 */
function estaLogueado() {
    return isset($_SESSION['usuario_id']);
}

/**
 * Autentica un usuario con email y contraseña
 * @param string $email Email del usuario
 * @param string $password Contraseña sin hash
 * @param PDO|null $pdo (Opcional) Conexión PDO existente
 * @return bool True si la autenticación es exitosa
 */
function autenticarUsuario($email, $password, $pdo = null) {
    // Si no se pasa conexión, crear una nueva
    if ($pdo === null) {
        $db = new Database();
        $pdo = $db->conectar();
    }

    try {
        $stmt = $pdo->prepare("SELECT id, password, rol FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password'])) {
            // Iniciar sesión si no está activa
            if (session_status() === PHP_SESSION_NONE) {
                iniciarSesionSegura();
            }
            
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_rol'] = $usuario['rol'];
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Error en autenticación: " . $e->getMessage());
        return false;
    }
}

/**
 * Cierra la sesión actual
 */
function cerrarSesion() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// Inicialización segura de sesión
if (session_status() === PHP_SESSION_NONE && !defined('SESSION_INITIALIZED')) {
    require_once __DIR__ . '/sesion.php';
    iniciarSesionSegura();
    define('SESSION_INITIALIZED', true);
}
?>