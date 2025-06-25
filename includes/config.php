<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'clientes_ventas';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function conectar() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch(PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            die("Error al conectar con la base de datos");
        }
        return $this->conn;
    }
}

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function sanitizar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

function mostrarMensaje() {
    if (!empty($_SESSION['mensaje'])) {
        echo '<div class="alert alert-info">'.$_SESSION['mensaje'].'</div>';
        unset($_SESSION['mensaje']);
    }
}

// Ejecutar solo si se accede directamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    $db = new Database();
    $conexion = $db->conectar();
    echo "✅ Conexión exitosa a la base de datos.";
}

