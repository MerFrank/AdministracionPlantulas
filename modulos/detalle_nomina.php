<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// Variables para el encabezado
$titulo = "Nómina";
$encabezado = "Generar Nómina";
$subtitulo = "Genere la nómina para un período específico";
$active_page = "nomina";

// Obtener el último período procesado
$ultimo_periodo = null;
$proximo_periodo = date('Y-m');
$empleados = [];

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener el último período de nómina
    $sql = "SELECT MAX(id_periodo) as ultimo_periodo FROM nominas";
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $ultimo_periodo = $result['ultimo_periodo'];
    
    // Calcular el próximo período
    if ($ultimo_periodo) {
        // Asumiendo que id_periodo está en formato YYYY-MM
        $fecha = new DateTime($ultimo_periodo . '-01');
        $fecha->modify('+1 month');
        $proximo_periodo = $fecha->format('Y-m');
    }
    
    // Obtener empleados activos con sus asignaciones
    $sql = "SELECT e.id_empleado, e.nombre, e.apellido_paterno, e.apellido_materno,
                   ep.id_asignacion, ep.sueldo_diario, ep.dias_laborales,
                   p.nombre as puesto
            FROM empleados e
            INNER JOIN empleado_puesto ep ON e.id_empleado = ep.id_empleado
            INNER JOIN puestos p ON ep.id_puesto = p.id_puesto
            WHERE e.activo = 1 AND ep.fecha_fin IS NULL
            ORDER BY e.apellido_paterno, e.apellido_materno, e.nombre";
    
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

// Procesar la generación de nómina
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodo = $_POST['periodo'];
    $dias_trabajados = $_POST['dias_trabajados'];
    
    try {
        $con->beginTransaction();
        
        $total_nomina = 0;
        
        // Procesar cada empleado
        foreach ($empleados as $empleado) {
            $id_empleado = $empleado['id_empleado'];
            $dias = $dias_trabajados[$id_empleado] ?? 0;
            
            if ($dias > 0) {
                // Calcular sueldo base
                $sueldo_base = $empleado['sueldo_diario'] * $dias;
                
                // Insertar en TU tabla nominas (una fila por empleado)
                $sql_nomina = "INSERT INTO nominas 
                              (id_periodo, id_empleado, dias_trabajados, sueldo_bruto, sueldo_neto, estatus, fecha_creacion) 
                              VALUES (?, ?, ?, ?, ?, 'generada', CURDATE())";
                $stmt_nomina = $con->prepare($sql_nomina);
                $stmt_nomina->execute([
                    $periodo, 
                    $id_empleado, 
                    $dias, 
                    $sueldo_base,
                    $sueldo_base // sueldo_neto inicialmente igual al bruto
                ]);
                
                $total_nomina += $sueldo_base;
            }
        }
        
        $con->commit();
        
        $_SESSION['success_message'] = "Nómina generada correctamente para el período $periodo";
        header("Location: detalle_nomina.php?periodo=$periodo");
        exit();
        
    } catch (PDOException $e) {
        $con->rollBack();
        $_SESSION['error_message'] = "Error al generar nómina: " . $e->getMessage();
        header("Location: generar_nomina.php");
        exit();
    }
}
?>

<main class="container py-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0"><?= htmlspecialchars($encabezado) ?></h2>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <form method="POST" class="needs-validation" novalidate>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="periodo" class="form-label">Período de Nómina</label>
                        <input type="month" class="form-control" id="periodo" name="periodo" 
                               value="<?= htmlspecialchars($proximo_periodo) ?>" required>
                        <div class="form-text">Seleccione el mes y año para la nómina</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Información del Período</label>
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <p class="mb-0">
                                    <?php if ($ultimo_periodo): ?>
                                        Última nómina: <strong><?= date('F Y', strtotime($ultimo_periodo . '-01')) ?></strong>
                                    <?php else: ?>
                                        Primera nómina del sistema
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mb-3">Empleados a Incluir en la Nómina</h4>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Puesto</th>
                                <th>Sueldo Diario</th>
                                <th>Días Laborales</th>
                                <th>Días a Pagar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empleados as $empleado): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno'] . ' ' . $empleado['apellido_materno']) ?>
                                        <input type="hidden" name="empleados[]" value="<?= $empleado['id_empleado'] ?>">
                                    </td>
                                    <td><?= htmlspecialchars($empleado['puesto']) ?></td>
                                    <td>$<?= number_format($empleado['sueldo_diario'], 2) ?></td>
                                    <td><?= htmlspecialchars($empleado['dias_laborales']) ?></td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               name="dias_trabajados[<?= $empleado['id_empleado'] ?>]" 
                                               min="0" max="31" value="0" required>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-cash-coin"></i> Generar Nómina
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once(__DIR__ . '/../../includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación del formulario
    const form = document.querySelector('.needs-validation');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});
</script>