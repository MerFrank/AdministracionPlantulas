<?php
require_once(__DIR__ . '/../includes/config.php');

// Funciones
function generarUsuario($nombre, $apellido_p, $con) {
    $base = strtolower(trim($nombre)) . '.' . strtolower(trim($apellido_p));
    $usuario = $base;
    $i = 1;

    $stmt = $con->prepare("SELECT * FROM operadores WHERE Usuario = ?");
    while (true) {
        $stmt->bindParam(1, $usuario, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if (count($result) === 0) {
            break;
        }

        $usuario = $base . $i;
        $i++;
    }
    return $usuario;
}

function generarContrasena($longitud = 10) {
    $caracteres = "1234567890abcdefghijklmñnopqrstuvwxyzABCDEFGHIJKLMNÑOPQRSTUVWXYZ.-_*/=[]{}#@~&()?¿";
    return substr(str_shuffle($caracteres), 0, $longitud);
}

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Consutar roles para asignar al usuario
$roles = $con->query("SELECT ID_Rol, nombreRol FROM roles ORDER BY nombreRol")->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Datos del formulario
        $nombre     = $_POST['nombre'] ?? '';
        $apellido_p = $_POST['apellido_p'] ?? '';
        $apellido_m = $_POST['apellido_m'] ?? '';
        $puesto     = $_POST['puesto'] ?? '';
        $fecha_ing  = $_POST['fecha_ingreso'] ?? '';
        $correo     = $_POST['correo'] ?? null;
        $activo     = $_POST['activo'] ?? 1;
        $id_rol     = $_POST['ID_Rol'] ?? '';

        // Generar usuario y contraseña
        $usuario    = generarUsuario($nombre, $apellido_p, $con);
        $contra     = generarContrasena();
        $contra_hash= password_hash($contra, PASSWORD_BCRYPT);

        // Construir array de datos
        $datos = [
            'Nombre'           => $nombre,
            'Apellido_P'       => $apellido_p,
            'Apellido_M'       => $apellido_m,
            'Puesto'           => $puesto,
            'Fecha_Ingreso'    => $fecha_ing,
            'Correo_Electronico'=> $correo,
            'Usuario'          => $usuario,
            'Contrasena_Hash'  => $contra_hash,
            'Activo'           => $activo,
            'ID_Rol'           => $id_rol
        ];

        // Validaciones
        if (empty($nombre)) throw new Exception("El nombre es requerido");
        if (empty($apellido_p)) throw new Exception("El Apellido Paterno es requerido");
        if (empty($apellido_m)) throw new Exception("El Apellido Materno es requerido");
        if (empty($puesto)) throw new Exception("El Puesto es requerido");

        // SQL
        $sql = "INSERT INTO operadores
            (Nombre, Apellido_P, Apellido_M, Puesto, Fecha_Ingreso, Correo_Electronico, Fecha_Registro, Usuario, Contrasena_Hash, Activo, ID_Rol) 
            VALUES (:Nombre, :Apellido_P, :Apellido_M, :Puesto, :Fecha_Ingreso, :Correo_Electronico, current_timestamp(), :Usuario, :Contrasena_Hash, :Activo, :ID_Rol)";

        $stmt = $con->prepare($sql);
        $stmt->execute($datos);

        if ($stmt->rowCount() > 0) {
            echo "<script>
                alert('Usuario registrado correctamente\\nUsuario: {$usuario}\\nContraseña: {$contra}');
            </script>";
        } else {
            throw new Exception("Error al registrar el cliente");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        echo "<div class='alert alert-danger'>$error</div>";
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

        <div class="mb-3">
            <label class="form-label">Roles de los usuarios <span class="text-danger">*</span></label>
            <select class="form-select" name="ID_Rol" required>
                <option value="">Seleccionar un rol ...</option>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= htmlspecialchars($rol['ID_Rol']) ?>">
                        <?= htmlspecialchars($rol['nombreRol']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Registrar Operador</button>
    </form>
</main>

<?php require_once(__DIR__ . '/../includes/footer.php'); ?>