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
//Calcular semana actual
$semanaActual = date('W'); 
$anioActual = date('Y'); 

$fechaInicioSemana = new DateTime();
$fechaInicioSemana->setISODate($anioActual, $semanaActual);

$fechaFinSemana = clone $fechaInicioSemana;
$fechaFinSemana->modify('+6 days');

// Formatear como string antes de usar
$fechaInicioStr = $fechaInicioSemana->format('Y-m-d');
$fechaFinStr = $fechaFinSemana->format('Y-m-d');

$stmt = $pdo->prepare('
    SELECT
        np.folio,
        c.nombre_Cliente,
        v.nombre_variedad,
        dnp.color,
        dnp.cantidad,
        np.fecha_entrega,
        np.fecha_validez
    FROM
        notaspedidos np
    LEFT JOIN detallesnotapedido dnp ON
        np.id_notaPedido = dnp.id_notaPedido
    LEFT JOIN clientes c ON
        np.id_cliente = c.id_cliente
    LEFT JOIN variedades v ON
        dnp.id_variedad = v.id_variedad
    WHERE np.fecha_entrega BETWEEN :fechaInicio AND :fechaFinStr
');

 $stmt ->execute([
    ':fechaInicio' => $fechaInicioStr ,
    ':fechaFinStr' => $fechaFinStr 
]);

$pedidoEntrega = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Entregas de Pedidos';
$encabezado = 'Reportes entregas';
$ruta = "dashboard_ventas.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';

?>
<main class="container py-4">

<section class="dashboard-grid mb-5">

    <!-- Tarjeta de Entregas (ancho completo arriba) -->
    <div class="dashboard-card-full">
        <div class="card shadow h-100">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="h5 mb-0"><i class="bi bi-clock-history"></i> Entregas de esta Semana</h3>
                    <a href="lista_entregas.php" class="btn btn-sm btn-light">Ver Todas</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($pedidoEntrega)): ?>
                    <div class="alert alert-info mb-0">No hay entregas programas para esta semana</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Cliente</th>
                                    <th>Variedad</th>
                                    <th>Color</th>
                                    <th>Cantidad</th>
                                    <th>Fecha Entrega</th>
                                    <th>Fecha Validez</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidoEntrega as $entrega): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($entrega['folio']) ?></td>
                                        <td><?= htmlspecialchars($entrega['nombre_Cliente']) ?></td>
                                        <td><?= htmlspecialchars($entrega['nombre_variedad']) ?></td>
                                        <td><?= htmlspecialchars($entrega['color']) ?></td>
                                        <td>$<?= number_format($entrega['cantidad'], 2) ?></td>
                                        <td><?= date('Y/m/d', strtotime($entrega['fecha_entrega'])) ?></td>
                                        <td><?= date('Y/m/d', strtotime($entrega['fecha_validez'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tarjetas pequeñas (dos columnas debajo) -->
    <div class="dashboard-card-half">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                    <i class="bi bi-clipboard2-plus text-success fs-1"></i>
                </div>
                <h3 class="h5">Registrar entrega de pedidos</h3>
                <p class="text-muted">Mantén el registro de las fechas de registros de los pedidos</p>
                <a href="registro_entregas.php" class="btn btn-info stretched-link">
                    <i class="bi me-1"></i> Acceder
                </a>
            </div>
        </div>
    </div>

    <div class="dashboard-card-half">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                    <i class="bi bi-truck text-info fs-1"></i>
                </div>
                <h3 class="h5">Reporte Entregas</h3>
                <p class="text-muted">Generar reportes detallados de entregas</p>
                <a href="lista_entregas.php" class="btn btn-info stretched-link">
                    <i class="bi me-1"></i> Acceder
                </a>
            </div>
        </div>
    </div>

</section>

</main>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>