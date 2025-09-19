<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// Variables para el encabezado
$titulo = "Nómina";
$encabezado = "Historial de Nóminas";
$subtitulo = "Listado de todas las nóminas generadas";
$active_page = "nomina";

// Obtener listado de nóminas
$nominas = [];

try {
    $db = new Database();
    $con = $db->conectar();
    
    $sql = "SELECT 
            n.id_periodo as periodo,
            COUNT(n.id_empleado) as empleados,
            SUM(n.sueldo_neto) as total_nomina,
            n.estatus as estado,
            MAX(n.fecha_creacion) as fecha_generacion,
            n.id_periodo as id_nomina
            FROM nominas n
            GROUP BY n.id_periodo, n.estatus
            ORDER BY n.id_periodo DESC, n.fecha_creacion DESC";
    
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $nominas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener nóminas: " . $e->getMessage());
}
?>

<main class="container py-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0"><?= htmlspecialchars($encabezado) ?></h2>
                <a href="generar_nomina.php" class="btn btn-light btn-sm">
                    <i class="bi bi-plus-circle"></i> Nueva Nómina
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Fecha Generación</th>
                            <th>Empleados</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($nominas)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">No hay nóminas generadas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($nominas as $nomina): ?>
                                <tr>
                                    <td><?= htmlspecialchars($nomina['periodo']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($nomina['fecha_generacion'])) ?></td>
                                    <td><?= htmlspecialchars($nomina['empleados']) ?></td>
                                    <td>$<?= number_format($nomina['total_nomina'], 2) ?></td>
                                    <td>
                                        <?php 
                                        $badge_class = [
                                            'generada' => 'bg-warning',
                                            'pagada' => 'bg-success',
                                            'cancelada' => 'bg-danger'
                                        ][$nomina['estado']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= ucfirst($nomina['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detalle_nomina.php?periodo=<?= $nomina['periodo'] ?>" 
                                               class="btn btn-info" title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($nomina['estado'] == 'generada'): ?>
                                                <a href="pagar_nomina.php?periodo=<?= $nomina['periodo'] ?>" 
                                                   class="btn btn-success" title="Marcar como pagada">
                                                    <i class="bi bi-check-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="reporte_nomina.php?periodo=<?= $nomina['periodo'] ?>" 
                                               class="btn btn-primary" title="Generar reporte">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php require_once(__DIR__ . '/../../includes/footer.php'); ?>