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


$cuentas_bancarias = [];
try {
  $stmt_cuentas = $pdo->prepare("
        SELECT id_cuenta, nombre, banco, numero 
        FROM cuentas_bancarias 
        WHERE activo = 1
        ORDER BY nombre
    ");
  $stmt_cuentas->execute();
  $cuentas_bancarias = $stmt_cuentas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Error al obtener cuentas bancarias: " . $e->getMessage());
  $cuentas_bancarias = [];
}

$lista_empleados = [];
try {
  $stmt_empleados = $pdo->prepare("
    SELECT 
        e.id_empleado,
        CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, '')) as nombre_completo,
        ep.sueldo_diario,
        ep.dias_laborales
        FROM empleados e
        LEFT JOIN empleado_puesto ep ON e.id_empleado = ep.id_empleado AND ep.fecha_fin IS NULL
        WHERE activo = 1 AND ep.sueldo_diario IS NOT NULL
    ");
  $stmt_empleados->execute();
  $lista_empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Error al obtener  empleado: " . $e->getMessage());
  $lista_empleados = [];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $pdo->beginTransaction();
    //Validar Datos
    if (empty($_POST['id_empleado'])) {
      throw new Exception("No se selecciono empleado");
    }

    if (empty($_POST['monto_presta']) || $_POST['monto_presta'] <= 0) {
      throw new Exception("Ingrese un monto válido");
    }

    if (empty($_POST['monto_descuento']) || $_POST['monto_descuento'] <= 0) {
      throw new Exception("Ingrese un monto válido");
    }

    if (empty($_POST['id_cuenta'])) {
      throw new Exception("Seleccione una cuenta bancaria");
    }

    $empleadoSelect = $_POST['id_empleado'];
    $empleado = array_filter($lista_empleados, fn($e) => $e['id_empleado'] == $empleadoSelect);

    $sueldo_diario = reset($empleado)['sueldo_diario'] ?? 0;

    $diasLaborablesString = reset($empleado)['dias_laborales'];
    $diasLaborablesArray = explode(',', $diasLaborablesString);
    $diasLaborables = count($diasLaborablesArray);
    $sueldoSemanal = $sueldo_diario * $diasLaborables;

    $maximoDescuento = $sueldoSemanal / 2;

    if ($_POST['monto_descuento'] > $maximoDescuento) {
      throw new Exception("El descuento no puede ser mayor a la mitad del sueldo semanal ");
    }

    $ID_Operador = $_SESSION['ID_Operador'] ?? 0;
    $id_cuenta = (int) $_POST['id_cuenta'];
    $montoPrestamo = (float) $_POST['monto_presta'];
    $montoDescuento = (float) $_POST['monto_descuento'];
    $comentarios = $_POST['comentarios'] ?? '';

    $stmt_cuenta = $pdo->prepare("
      SELECT saldo_actual
      FROM cuentas_bancarias
      WHERE id_cuenta = :id_cuenta
    ");

    $stmt_cuenta->execute([
      ':id_cuenta' => $id_cuenta
    ]);

    $monto_cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);

    $saldoCuenta = (float) $monto_cuenta['saldo_actual'] ;

    if ( $saldoCuenta == 0 || $montoPrestamo > $saldoCuenta ) {
      throw new Exception("La cuenta no cuenta con saldo suficiente");
    }

    $stmt = $pdo->prepare("
            INSERT INTO prestamos_empleados (
                id_empleado, id_cuenta, montoPrestamo, montoDescuento, comentarios, fecha_prestamo, ID_Operador, activo
            ) VALUE (
                ?, ?, ?, ?, ?, NOW(), ?, 1
              )
        ");

    $stmt->execute([
      $empleadoSelect,
      $id_cuenta,
      $montoPrestamo,
      $montoDescuento,
      $comentarios,
      $ID_Operador
    ]);

    $stmt_update_cuenta = $pdo->prepare("
      UPDATE cuentas_bancarias 
      SET saldo_actual = saldo_actual - :monto 
      WHERE id_cuenta = :id_cuenta
    ");

    $stmt_update_cuenta->execute([
      ':monto' => $montoPrestamo,
      ':id_cuenta' => $id_cuenta
    ]);

    $pdo->commit();

    $success = "Prestamo registrado correctamente. ";

  } catch (Exception $e) {
    $pdo->rollBack();
    $error = $e->getMessage();
  }
}



// Variables para el encabezado
$titulo = "Prestamos a Empleados";
$encabezado = "Gestión de Deuda";
$subtitulo = "Lista de empleados con prestamos";


$ruta = "dashboard_empleados.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>

<main class="container mt-4">
  <div class="card shadow">
    <div class="card-header bg-primary text-white">
      <h2><i class="bi bi-cash-coin"></i> Registrar Prestamo a Empleado</h2>
    </div>

    <div class="card-body">
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="post" id="prestamoForm">
        <div class="row g-3">
          <!-- Selección de empleado -->
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Empleado <span class="text-danger">*</span></label>
              <select class="form-select" name="id_empleado" id="selectEmpleado" required>
                <option value="">Seleccione un empleado...</option>
                <?php foreach ($lista_empleados as $empleado): ?>
                  <option value="<?= $empleado['id_empleado'] ?>">
                    <?= htmlspecialchars($empleado['nombre_completo'] . ' - ' . '$' . $empleado['sueldo_diario']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Monto del prestamo -->
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Monto del prestamo <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" name="monto_presta" id="monto_presta" step="0.01" min="0.01"
                  required>
              </div>
            </div>
          </div>


          <!-- Cuenta bancaria -->
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Cuenta bancaria <span class="text-danger">*</span></label>
              <select class="form-select" name="id_cuenta" required>
                <option value="">Seleccione una cuenta...</option>
                <?php foreach ($cuentas_bancarias as $cuenta): ?>
                  <option value="<?= $cuenta['id_cuenta'] ?>">
                    <?= htmlspecialchars($cuenta['banco'] . ' - ' . $cuenta['nombre'] . ' (' . $cuenta['numero'] . ')') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Monto del descuento  -->
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Monto del descuento <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" name="monto_descuento" id="monto_descuento" step="0.01"
                  min="0.01" required>
              </div>
            </div>
          </div>

          <!-- Comentarios -->
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label">Comentarios (opcional)</label>
              <textarea class="form-control" name="comentarios" rows="2"></textarea>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="dashboard_empleados.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Cancelar
          </a>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle"></i> Registrar Prestamo
          </button>
        </div>
      </form>

    </div>
  </div>
</main>

<?php require('../../includes/footer.php'); ?>