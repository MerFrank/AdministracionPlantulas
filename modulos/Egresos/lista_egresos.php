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

// Consulta para obtener los egresos con joins para nombres relacionados
$egresos = $con->query("
    SELECT e.*, 
           t.nombre AS tipo_egreso, 
           p.nombre_proveedor AS proveedor, 
           s.nombre AS sucursal,
           cb.nombre AS cuenta_bancaria
    FROM egresos e
    LEFT JOIN tipos_egreso t ON e.id_tipo_egreso = t.id_tipo
    LEFT JOIN proveedores p ON e.id_proveedor = p.id_proveedor
    LEFT JOIN sucursales s ON e.id_sucursal = s.id_sucursal
    LEFT JOIN cuentas_bancarias cb ON e.id_cuenta = cb.id_cuenta
    ORDER BY e.fecha DESC
")->fetchAll();

$titulo = 'Listado de Egresos';
$ruta = "dashboard_egresos.php";
$texto_boton = "Regresar";
require __DIR__ . '/../../includes/header.php';

// Función para convertir número a letras
function numeroALetras($numero) {
    require_once __DIR__ . '/../../includes/numeros_a_letras.php';
    return numeros_a_letras($numero);
}
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-cash-stack"></i> Listado de Egresos</h2>
                <div>
                    <a href="Registro_egreso.php" class="btn btn-success me-2">
                        <i class="bi bi-plus-circle"></i> Nuevo Egreso
                    </a>
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalGenerarVale">
                        <i class="bi bi-file-earmark-text"></i> Generar Vale
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
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Proveedor</th>
                            <th>Sucursal</th>
                            <th>Monto</th>
                            <th>Método Pago</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($egresos)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No hay egresos registrados</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($egresos as $egreso): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($egreso['fecha'])) ?></td>
                                <td><?= htmlspecialchars($egreso['tipo_egreso'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['proveedor'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($egreso['sucursal'] ?? 'N/A') ?></td>
                                <td>$<?= number_format($egreso['monto'], 2) ?></td>
                                <td>
                                    <?= htmlspecialchars(ucfirst($egreso['metodo_pago'] ?? 'N/A')) ?>
                                    <?= isset($egreso['cuenta_bancaria']) ? " (" . htmlspecialchars($egreso['cuenta_bancaria']) . ")" : '' ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="editar_egreso.php?id=<?= $egreso['id_egreso'] ?>"
                                        class="btn btn-sm btn-primary" 
                                        style="background-color: var(--color-accent); border-color: var(--color-accent);">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="eliminar_egreso.php?id=<?= $egreso['id_egreso'] ?>" 
                                        class="btn btn-sm btn-primary" 
                                        style="background-color: var(--color-danger); border-color: var(--color-danger);"
                                           onclick="return confirm('¿Eliminar este egreso?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <a href="generar_vale.php?id=<?= $egreso['id_egreso'] ?>"
                                         class="btn btn-sm btn-primary" target="_blank"
                                         style="background-color: var(--color-receipt2); border-color: var(--color-receipt2);">
                                            <i class="bi bi-receipt"></i> Vale
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

<!-- Modal para generar vale -->
<div class="modal fade" id="modalGenerarVale" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generar Vale Provisional</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="generar_vale.php" method="post" target="_blank">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="mb-3">
            <label class="form-label">Número de Folio</label>
            <input type="text" class="form-control" name="folio" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Invernadero</label>
            <input type="text" class="form-control" name="invernadero" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Concepto</label>
            <input type="text" class="form-control" name="concepto" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Monto</label>
            <input type="number" class="form-control" name="monto" step="0.01" min="0.01" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" name="fecha" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Autorizado por</label>
            <input type="text" class="form-control" name="autorizado_por" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Recibido por</label>
            <input type="text" class="form-control" name="recibido_por" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Generar Vale</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>