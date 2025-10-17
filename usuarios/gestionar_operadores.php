<?php
require_once(__DIR__ . '/../includes/config.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo = 'Lista de Operadores';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Procesar parámetros de búsqueda y paginación
$busqueda = $_GET['busqueda'] ?? '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;

// Construir consulta base con filtros
$where_conditions = [];
$params = [];

if (!empty($busqueda)) {
    $where_conditions[] = "(Nombre LIKE :busqueda OR Apellido_P LIKE :busqueda OR Apellido_M LIKE :busqueda OR Correo_Electronico LIKE :busqueda OR Puesto LIKE :busqueda OR Usuario LIKE :busqueda)";
    $params[':busqueda'] = '%' . $busqueda . '%';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Obtener el total de registros para paginación
$sql_count = "SELECT COUNT(*) as total FROM operadores $where_sql";
$stmt_count = $con->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Ajustar página si es necesario
if ($pagina < 1) $pagina = 1;
if ($pagina > $total_paginas && $total_paginas > 0) $pagina = $total_paginas;

// Calcular offset
$offset = ($pagina - 1) * $registros_por_pagina;

// Obtener los registros de la página actual
$sql = "SELECT * FROM operadores $where_sql ORDER BY Fecha_Registro DESC LIMIT :offset, :limit";

$stmt = $con->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->execute();
$operadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$encabezado = "Lista de Operadores";
$subtitulo = "Muestra operadores registrados en el sistema";
$ruta = "panel_usuarios.php";
$texto_boton = "Regresar";
require_once(__DIR__ . '/../includes/header.php');
?>

<main class="container mt-4 mb-5">
    <div class="card card-lista">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-people-fill me-2"></i>Operadores</h2>
                <div>
                    <a href="registro_operador.php" class="btn btn-success btn-sm ms-2">
                        <i class="bi bi-plus-circle"></i> Nuevo
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <div class="input-group mb-3">
                <span class="input-group-text" style="background-color: var(--color-primary); color: white;">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control" id="busqueda" 
                       placeholder="Buscar por nombre, apellido, correo, puesto o usuario"
                       value="<?= htmlspecialchars($busqueda) ?>">
                <button class="btn btn-outline-secondary" type="button" id="limpiar-busqueda">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div id="tabla-operadores-container">
                <?php if (empty($operadores)): ?>
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle me-2"></i> No se encontraron operadores.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Completo</th>
                                    <th>Puesto</th>
                                    <th>Correo</th>
                                    <th>Usuario</th>
                                    <th>Fecha Ingreso</th>
                                    <th>Fecha Registro</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($operadores as $operador): ?>
                                    <tr>
                                        <td><?= $operador['ID_Operador'] ?></td>
                                        <td><?= htmlspecialchars($operador['Nombre'] . ' ' . $operador['Apellido_P'] . ' ' . ($operador['Apellido_M'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($operador['Puesto'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($operador['Correo_Electronico'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($operador['Usuario'] ?? '') ?></td>
                                        <td><?= date('d/m/Y', strtotime($operador['Fecha_Ingreso'])) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($operador['Fecha_Registro'])) ?></td>
                                        <td>
                                            <span class="badge <?= $operador['Activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $operador['Activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="editar_operador.php?id=<?= $operador['ID_Operador'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-<?= $operador['Activo'] ? 'danger' : 'success' ?> toggle-estado" 
                                                        data-id="<?= $operador['ID_Operador'] ?>" 
                                                        data-estado="<?= $operador['Activo'] ?>" 
                                                        title="<?= $operador['Activo'] ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="bi bi-<?= $operador['Activo'] ? 'x-circle' : 'check-circle' ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Anterior</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                        <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once(__DIR__ . '/../includes/footer.php'); ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('[title]').tooltip();
    
    let searchTimeout;
    $('#busqueda').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            const searchTerm = $('#busqueda').val();
            $.get('lista_operadores.php', { 
                busqueda: searchTerm, 
                ajax: true 
            }, function(data) {
                $('#tabla-operadores-container').html(data);
                $('[title]').tooltip();
            });
        }, 300);
    });
    
    $('#limpiar-busqueda').click(function() {
        $('#busqueda').val('');
        $.get('lista_operadores.php', { 
            busqueda: '', 
            ajax: true 
        }, function(data) {
            $('#tabla-operadores-container').html(data);
            $('[title]').tooltip();
        });
    });
    

});
</script>