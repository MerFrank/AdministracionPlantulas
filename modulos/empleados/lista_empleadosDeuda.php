<?php
require_once __DIR__ . '/../../includes/config.php';
require_once(__DIR__ . '/../../includes/validacion_session.php');


// Crear instancia de Database y obtener conexión PDO
$database = new Database();
$pdo = $database->conectar();

// Verificar si hay conexión a la base de datos
try {
  if (!$pdo) {
    throw new Exception("No hay conexión a la base de datos");
  }
  // Test simple de conexión
  $pdo->query("SELECT 1");
} catch (Exception $e) {
  die("Error de conexión a la base de datos: " . $e->getMessage());
}

$prestamos = $pdo->query(
    "SELECT
        pe.id_prestamo,
        pe.montoDescuento,
        CONCAT(
            e.nombre,
            ' ',
            e.apellido_paterno,
            ' ',
            COALESCE(e.apellido_materno, '')
        ) AS nombre_completo,
        CONCAT(
            cb.nombre
        ) AS cuenta,
        pe.saldo,
        pe.montoPrestamo,
        pe.comentarios,
        pe.fecha_prestamo,
        CONCAT(o.Nombre) AS operador
    FROM
        prestamos_empleados pe
    LEFT JOIN empleados e ON
        e.id_empleado = pe.id_empleado
    LEFT JOIN cuentas_bancarias cb ON
        cb.id_cuenta = pe.id_cuenta
    LEFT JOIN operadores o ON
	pe.ID_Operador = o.ID_Operador
    WHERE
        pe.activo = 1;"
)->fetchAll();


// Variables para el encabezado
$titulo = "Prestamos a Empleados";
$encabezado = "Gestión de Deuda";
$subtitulo = "Lista de empleados con prestamos";


$ruta = "dashboard_empleados.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>

<main class="container mt-4">
  <div class="class=card card-lista">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-people-fill me-2"></i>Prestamos</h2>
                    <div>
                        <a href="registro_prestamoEmpleados.php" class="btn btn-success btn-sm ms-2">
                            <i class="bi bi-plus-circle"></i> Nuevo
                        </a>
                    </div>
            </div>

            
        </div>

        <div class="table-responsive" id="tabla-empleados-container">
            <table class= "table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Num Prestamo</th>
                        <th>Empleado</th>
                        <th>Cuenta</th>
                        <th>Saldo</th>
                        <th>Monto del Prestamo</th>
                        <th>Descuento</th>
                        <th>Comentario</th>
                        <th>Fecha del prestamo</th>
                        <th>Operador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prestamos)): ?>
                        <tr>
                            <td colspan="11" class="text-center">No se encontraron empleados</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($prestamos as $prestamo): ?>
                            <tr>
                                <td><?= htmlspecialchars($prestamo['id_prestamo']) ?></td>
                                <td><?= htmlspecialchars($prestamo['nombre_completo'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prestamo['cuenta'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prestamo['saldo'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prestamo['montoPrestamo'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prestamo['montoDescuento'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prestamo['comentarios'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prestamo['fecha_prestamo'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prestamo['operador'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>
</main>

<?php require('../../includes/footer.php'); ?>