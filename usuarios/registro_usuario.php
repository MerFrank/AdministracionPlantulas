<?php
require_once(__DIR__ . '/../includes/config.php');

// Funciones
function generarUsuario($nombre, $apellido_p, $conn) {
    $base = strtolower(trim($nombre)) . '.' . strtolower(trim($apellido_p));
    $usuario = $base;
    $i = 1;

    // Consulta preparada para verificar usuario existente
    $stmt = $conn->prepare("SELECT * FROM operadores WHERE Usuario = ?");
    while (true) {
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            break;
        }
        
        $usuario = $base . $i;
        $i++;
    }
    $stmt->close();

    return $usuario;
}

function generarContrasena($longitud = 10) {
    $caracteres = "1234567890abcdefghijklmñnopqrstuvwxyzABCDEFGHIJKLMNÑOPQRSTUVWXYZ.-_*/=[]{}#@|~¬&()?¿";
    return substr(str_shuffle($caracteres), 0, $longitud);
}

// AJAX
$roles = $con->query("SELECT ID_Rol, nombreRol FROM roles ORDER BY nombre")->fetchAll();


// Procesar formulario
$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y sanitizar datosr
    $nombre = trim($_POST['nombre']);
    $apellido_p = trim($_POST['apellido_p']);
    $apellido_m = trim($_POST['apellido_m']);
    $area = trim($_POST['area_produccion']);
    $puesto = trim($_POST['puesto']);
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $correo = trim($_POST['correo']);
    

    // Validaciones básicas
    if (empty($nombre) || empty($apellido_p) || empty($puesto) || empty($fecha_ingreso)) {
        $mensaje = "<p style='color: red;'>❌ Error: Campos obligatorios faltantes</p>";
    } else {
        $usuario = generarUsuario($nombre, $apellido_p, $conn);
        $contrasena = generarContrasena();
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);

        // Consulta preparada para evitar inyección SQL
        $sql = "INSERT INTO operadores (
            Nombre, Apellido_P, Apellido_M, Area_Produccion, Puesto, Fecha_Ingreso,
            Correo_Electronico, Usuario, Contrasena_Hash, Activo, ID_Rol, Fecha_Registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, CURDATE())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssi", $nombre, $apellido_p, $apellido_m, $area, 
                         $puesto, $fecha_ingreso, $correo, $usuario, $hash, $id_rol);

        if ($stmt->execute()) {
            $mensaje = "<div style='color: green;'>
                          <h3>✅ Operador registrado correctamente</h3>
                          <p><strong>Usuario:</strong> $usuario</p>
                          <p><strong>Contraseña:</strong> $contrasena</p>
                        </div>";
        } else {
            $mensaje = "<p style='color: red;'>❌ Error al registrar operador: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

// Configuración de la página
$titulo = "Registro de Operador";
$encabezado = "Registro de Operador";
$ruta = "panel_usuarios.php";
$texto_boton = "";

// Incluir el header
require_once(__DIR__ . '/../includes/header.php');
?>

<main class="container py-4">
    <?php if (!empty($mensaje)) echo $mensaje; ?>
    
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Nombre:</label>
            <input type="text" class="form-control" name="nombre" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Apellido Paterno:</label>
            <input type="text" class="form-control" name="apellido_p" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Apellido Materno:</label>
            <input type="text" class="form-control" name="apellido_m" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Área de Producción:</label>
            <input type="text" class="form-control" name="area_produccion">
        </div>

        <div class="mb-3">
            <label class="form-label">Puesto:</label>
            <input type="text" class="form-control" name="puesto" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha de Ingreso:</label>
            <input type="date" class="form-control" name="fecha_ingreso" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Correo Electrónico:</label>
            <input type="email" class="form-control" name="correo" placeholder="Opcional">
        </div>

        <!-- Campo oculto para el rol (puedes cambiarlo por un select si necesitas) -->
        <input type="hidden" name="id_rol" value="2">

        <button type="submit" class="btn btn-primary">Registrar Operador</button>
    </form>
</main>

<?php require_once(__DIR__ . '/../includes/footer.php'); ?>