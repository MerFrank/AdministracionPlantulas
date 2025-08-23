<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Consulta para obtener las cotizaciones con información de cliente
$cotizaciones = $con->query("
    SELECT 
        c.id_cotizacion, 
        c.folio, 
        cl.nombre_Cliente AS cliente_nombre, 
        cl.telefono AS cliente_telefono,
        c.fecha, 
        c.total, 
        c.valida_hasta,
        c.estado 
    FROM cotizaciones c 
    JOIN clientes cl ON c.id_cliente = cl.id_cliente
    ORDER BY c.fecha DESC
")->fetchAll();

$titulo = 'Listado de Cotizaciones';
require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-file-text"></i> Listado de Cotizaciones</h2>
                <div>
                    <a href="registro_cotizacion.php" class="btn btn-success me-2">
                        <i class="bi bi-plus-circle"></i> Nueva Cotización
                    </a>
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalGenerarPDF">
                        <i class="bi bi-file-earmark-pdf"></i> Generar PDF
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-striped" id="tablaCotizaciones">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Total</th>
                            <th>Válida hasta</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cotizaciones)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No hay cotizaciones registradas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cotizaciones as $cotizacion): ?>
                            <tr>
                                <td><?= htmlspecialchars($cotizacion['folio']) ?></td>
                                <td><?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></td>
                                <td><?= htmlspecialchars($cotizacion['cliente_nombre']) ?></td>
                                <td><?= htmlspecialchars($cotizacion['cliente_telefono']) ?></td>
                                <td>$<?= number_format($cotizacion['total'], 2) ?></td>
                                <td><?= date('d/m/Y', strtotime($cotizacion['valida_hasta'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $cotizacion['estado'] == 'aprobada' ? 'success' : 
                                        ($cotizacion['estado'] == 'pendiente' ? 'warning' : 'secondary') 
                                    ?>">
                                        <?= ucfirst($cotizacion['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="eliminar_cotizacion.php?id=<?= $cotizacion['id_cotizacion'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Eliminar esta cotización?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <a href="generar_pdf_cotizacion.php?id=<?= $cotizacion['id_cotizacion'] ?>" 
                                        class="btn btn-sm btn-info" 
                                        target="_blank">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF
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

<!-- Modal para generar PDF -->
<div class="modal fade" id="modalGenerarPDF" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generar Cotización en PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="generar_pdf_cotizacion.php" method="post" target="_blank">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Seleccionar Cotización</label>
            <select class="form-select" name="id_cotizacion" required>
              <option value="">Seleccione una cotización</option>
              <?php foreach ($cotizaciones as $cotizacion): ?>
                <option value="<?= $cotizacion['id_cotizacion'] ?>">
                  <?= htmlspecialchars($cotizacion['folio']) ?> - <?= htmlspecialchars($cotizacion['cliente_nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Generar PDF</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar DataTable si existe
    if ($.fn.DataTable) {
        $('#tablaCotizaciones').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
            },
            order: [[1, 'desc']]
        });
    }
});
</script>