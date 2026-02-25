<?php
// ==========================
// INICIAR SESIÓN
// ==========================
session_start();

// Configuración de errores (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================
// REDIRECCIÓN SI YA LOGUEADO
// ==========================
if (isset($_SESSION['ID_Operador'])) {

    $rutas = [
        1 => '/modulos/dashboard_adminGeneral.php',
        2 => '/modulos/dashboard_secre.php',
        3 => '/modulos/dashboard_auxAdmin.php',
        4 => '/control_administrativo/public/AdministracionControl',
        5 => '/control_administrativo/public/SecretariaControl',
        6 => '/control_administrativo/public/AuxiliarControl',
    ];

    if (isset($_SESSION['Rol']) && isset($rutas[$_SESSION['Rol']])) {
        header('Location: ' . $rutas[$_SESSION['Rol']]);
        exit;
    }
}

// ==========================
// CONEXIÓN
// ==========================
require_once __DIR__ . '/../includes/config.php';

$error = '';
$usuario = '';

// ==========================
// GENERAR TOKEN CSRF
// ==========================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================
// PROCESAR LOGIN
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verificar token CSRF
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Token CSRF inválido. Recarga la página.';
    } else {

        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if (empty($usuario) || empty($contrasena)) {
            $error = 'Todos los campos son obligatorios.';
        } else {

            try {
                $db = new Database();
                $con = $db->conectar();

                $sql = "SELECT ID_Operador, Contrasena_Hash, ID_Rol, Nombre
                        FROM operadores
                        WHERE LOWER(TRIM(Usuario)) = LOWER(?)
                        AND Activo = 1
                        LIMIT 1";

                $stmt = $con->prepare($sql);
                $stmt->execute([$usuario]);
                $operador = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$operador) {
                    $error = 'Usuario no encontrado.';
                } elseif (!password_verify($contrasena, $operador['Contrasena_Hash'])) {
                    $error = 'Contraseña incorrecta.';
                } else {

                    // ==========================
                    // LOGIN EXITOSO
                    // ==========================
                    session_regenerate_id(true);

                    $_SESSION['ID_Operador'] = $operador['ID_Operador'];
                    $_SESSION['Rol'] = $operador['ID_Rol'];
                    $_SESSION['Nombre'] = $operador['Nombre'];

                    // Actualizar sesión en BD
                    $sid = session_id();
                    $upd = $con->prepare("
                        UPDATE operadores
                        SET current_session_id = ?,
                            last_activity = NOW(),
                            Ultimo_Acceso = NOW()
                        WHERE ID_Operador = ?
                    ");
                    $upd->execute([$sid, $operador['ID_Operador']]);

                    // Redirigir según rol
                    $rutas = [
                        1 => '/modulos/dashboard_adminGeneral.php',
                        2 => '/modulos/dashboard_secre.php',
                        3 => '/modulos/dashboard_auxAdmin.php',
                        4 => '/control_administrativo/public/AdministracionControl',
                        5 => '/control_administrativo/public/SecretariaControl',
                        6 => '/control_administrativo/public/AuxiliarControl',
                    ];

                    header('Location: ' . ($rutas[$operador['ID_Rol']] ?? 'panel.php'));
                    exit;
                }

            } catch (PDOException $e) {
                $error = 'Error interno del sistema.';
                error_log("Error login: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Plántulas Agrodex</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            height: 100vh;
            background: linear-gradient(135deg, #e8f5e9, #a5d6a7);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-card {
            background: white;
            padding: 40px;
            width: 350px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .login-title {
            text-align: center;
            color: #2e7d32;
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #43a047, #2e7d32);
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>

    <form class="login-card" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" autocomplete="off">

        <h1 class="login-title">Plántulas Agrodex</h1>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

        <label>Usuario</label>
        <input type="text" name="usuario" class="form-control" required value="<?= htmlspecialchars($usuario); ?>"
            autocomplete="username">

        <label>Contraseña</label>
        <input type="password" name="contrasena" class="form-control" required autocomplete="current-password">

        <button type="submit" class="btn-login">
            INGRESAR AL PANEL
        </button>

    </form>

</body>

</html>