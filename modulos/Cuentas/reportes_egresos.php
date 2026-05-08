<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once __DIR__ . '/../../includes/config.php';
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = new Database();
    $pdo = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$egresos = $pdo->query(
    'SELECT 
        fecha,
        te.nombre,
        p.id_proveedor,
        s.id_sucursal,
        cb.id_cuenta,
        monto,
        concepto,
        metodo_pago,
        comprobante,
        observaciones
     FROM egresos e
     LEFT JOIN tipos_egreso te ON
     e.id_tipo_egreso = te.id_tipo
     LEFT JOIN proveedores p ON
     e.id_proveedor = p.id_proveedor
     LEFT JOIN sucursales s ON
     e.id_sucursal = s.id_sucursal
     LEFT JOIN cuentas_bancarias cb ON
     e.id_cuenta = cb.id_cuenta

')->fetchAll();


$titulo = 'Reportes Egresos';
$encabezado = 'Reportes Egresos';

$ruta = "lista_reportes.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';

?>
<main class="container mt-4">
  <div class="class=card card-lista">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-currency-dollar me-2"></i>Egresos</h2>
                    <div>
                        <a href="../Egresos/Registro_egreso.php" class="btn btn-success btn-sm ms-2">
                            <i class="bi bi-plus-circle"></i> Nuevo
                        </a>
                    </div>
            </div>

            
        </div>

        <div class="table-responsive" id="tabla-empleados-container">
            <table class= "table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Proveedor</th>
                        <th>Sucursal</th>
                        <th>Cuenta</th>
                        <th>Monto   </th>
                        <th>Concepto</th>
                        <th>Método de pago</th>
                        <th>Comprobante</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($egresos)): ?>
                        <tr>
                            <td colspan="11" class="text-center">No se encontraron egresos</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($egresos as $egreso): ?>
                            <tr>
                                <td><?= htmlspecialchars($egreso['fecha']) ?></td>
                                <td><?= htmlspecialchars($egreso['nombre'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['id_proveedor'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['id_proveedor'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['id_cuenta'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['monto'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['concepto'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['metodo_pago'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['comprobante'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['observaciones'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>