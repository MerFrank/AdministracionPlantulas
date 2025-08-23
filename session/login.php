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
    // Luego verificar el rol y redirigir
    if (isset($_SESSION['Rol'])) {
        $rutas = [
            1 => '/AdministracionPlantulas/modulos/dashboard_adminGeneral.php',
            2 => '/AdministracionPlantulas/modulos/dashboard_secre.php',
            3 => '/AdministracionPlantulas/modulos/dashboard_auxAdmin.php',
        ];
        if (isset($rutas[$_SESSION['Rol']])) {
            header('Location: ' . $rutas[$_SESSION['Rol']]);
            exit;
        }
    }
    exit;
}



require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../session/session_manager.php';

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
                        1 => '/AdministracionPlantulas/modulos/dashboard_adminGeneral.php',
                        2 => '/AdministracionPlantulas/modulos/dashboard_secre.php',
                        3 => '/AdministracionPlantulas/modulos/dashboard_auxAdmin.php',
                    ];
                    
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
$ruta = "";
$texto_boton = "";
require( __DIR__ . '/../includes/header.php');
?>

<main class="login-container">
    
<form class="login-card" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">  
    <h1 class="login-title">Bienvenid@ a Plantulas Agrodex</h1>
    
      <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    
        <div class="input-group">
            <label for="usuario">Usuario</label>
            <input type="text"
        id="usuario"
        name="usuario"
        value="<?= htmlspecialchars($usuario) ?>"
        required
        autofocus
        oninput="this.value = this.value.toLowerCase();"
        style="text-transform: lowercase;">
        </div>
    
        <div class="input-group">
        <label for="contrasena">Contraseña</label>
        <input type="password" id="contrasena" name="contrasena" required>
        </div>
        <div class="input-group mostrar-pass">
        <label>
            <input type="checkbox" onclick="togglePassword()"> Mostrar contraseña
        </label>
        </div>
         <button type="submit" class="btn-login">Ingresar</button>
    </form>

</main>

<?php require_once(__DIR__ . '/../includes/footer.php'); ?>

<script>
function togglePassword() {
  const input = document.getElementById("contrasena");
  input.type = input.type === "password" ? "text" : "password";
}
</script>
     

