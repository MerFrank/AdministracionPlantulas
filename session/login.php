<?php
// Iniciar sesión ANTES de cualquier otra operación
session_start();

// Configuración de errores (después de session_start)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar si ya está logueado (ahora sí podrá detectar la sesión)
// Si tienes información de rol en la sesión, puedes redirigir según el rol
if (isset($_SESSION['ID_Operador'])) {
    if (isset($_SESSION['Rol'])) {
        $rutas = [
            1 => 'http://localhost/control_administrativo/public/configuracion',
            2 => 'http://localhost/control_administrativo/public/bitacora',
            3 => 'http://localhost/control_administrativo/public/mi-rendimiento',
        ];
        if (isset($rutas[$_SESSION['Rol']])) {
            header('Location: ' . $rutas[$_SESSION['Rol']]);
            exit;
        }
    }
    exit;
}



require_once __DIR__ . '/../includes/config.php';
//require_once __DIR__ . '/../session/session_manager.php';

$error = '';
$usuario = '';

// Generar token CSRF solo una vez
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = function_exists('random_bytes')
        ? bin2hex(random_bytes(32))
        : md5(uniqid(mt_rand(), true));
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Token CSRF inválido. Por favor recarga la página e intenta nuevamente.';
    } else {
        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if (empty($usuario)) {
            $error = 'El usuario es requerido';
        } elseif (empty($contrasena)) {
            $error = 'La contraseña es requerida';
        } else {
            try {
                $db = new Database();
                $con = $db->conectar();

                $sql = "SELECT `ID_Operador`, `Contrasena_Hash`, `ID_Rol`, `Nombre` 
                        FROM `operadores` 
                        WHERE LOWER(TRIM(`Usuario`)) = LOWER(?) AND `Activo` = 1 LIMIT 1";
                $stmt = $con->prepare($sql);
                $stmt->execute([$usuario]);
                $operador = $stmt->fetch();

                if (!$operador) {
                    $error = 'Usuario no encontrado o inactivo';
                } elseif (!password_verify($contrasena, $operador['Contrasena_Hash'])) {
                    $error = 'Contraseña incorrecta';
                } else {
                    // Autenticación exitosa
                    session_regenerate_id(true);

                    $_SESSION['ID_Operador'] = $operador['ID_Operador'];
                    $_SESSION['Rol'] = $operador['ID_Rol'];
                    $_SESSION['Nombre'] = $operador['Nombre'];

                    // Actualizar BD
                    $sid = session_id();
                    $upd = $con->prepare("UPDATE `operadores` 
                                         SET `current_session_id` = ?,
                                             `last_activity` = NOW(),
                                             `Ultimo_Acceso` = NOW()
                                         WHERE `ID_Operador` = ?");
                    $upd->execute([$sid, $operador['ID_Operador']]);

                    // Redirigir según rol
                    $rutas = [
                        1 => 'http://localhost/control_administrativo/public/configuracion',
                        2 => 'http://localhost/control_administrativo/public/bitacora',
                        3 => 'http://localhost/control_administrativo/public/mi-rendimiento',
                    ];

                    // Importante: Usamos el ID_Rol que viene de la base de datos ($operador)
                    header('Location: ' . ($rutas[$operador['ID_Rol']] ?? 'panel.php'));
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Error en el sistema. Por favor intente más tarde.';
                error_log("Error de login: " . $e->getMessage());
            }
        }
    }
}

// Variables para el encabezado
$titulo = "Login";
$encabezado = "Login";
$subtitulo = "Panel de inicio de sesión";

// Incluir la cabecera (ruta relativa al archivo actual)

$titulo = "Login - Agrodex";
require(__DIR__ . '/../includes/header.php'); 
?>

<main class="login-container">
    <div class="background-elements">
        <div class="leaf-left"></div>
        <div class="leaf-right"></div>
    </div>

    <form class="login-card" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="logo-3d">
            <div class="cube">
                <div class="face front">Agrodex</div>
                <div class="face back"></div>
                <div class="face right"></div>
                <div class="face left"></div>
                <div class="face top"></div>
                <div class="face bottom"></div>
            </div>
        </div>

        <h1 class="login-title">Plántulas Agrodex</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2" style="font-size: 0.85rem;">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label fw-bold text-success">Usuario</label>
            <input type="text" name="usuario" class="form-control" required autofocus oninput="this.value = this.value.toLowerCase();">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold text-success">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" class="form-control" required>
        </div>

       
        <button type="submit" class="btn-login">INGRESAR AL PANEL</button>
    </form>
</main>

<script>
    function togglePassword() {
        const input = document.getElementById("contrasena");
        input.type = input.type === "password" ? "text" : "password";
    }

</script>

</body>
</html>