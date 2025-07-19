<?php
// Configuración de la página
$titulo = "Seleccionar Venta para Nota";
$active_page = "ventas";

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener todas las ventas (sin filtro inicial)
    $sql = "
        SELECT np.id_notaPedido, np.fechaPedido, np.total, np.estado, c.nombre_Cliente
        FROM NotasPedidos np
        LEFT JOIN Clientes c ON np.id_cliente = c.id_cliente
        ORDER BY np.fechaPedido DESC
        LIMIT 50
    ";
    
    $ventas = $con->query($sql)->fetchAll();

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> - Plantulas</title>
    <style>
        .table-hover tbody tr:hover {
            cursor: pointer;
            background-color: #f8f9fa;
        }
        .search-box {
            margin-bottom: 20px;
        }
        #filtro {
            padding: 10px;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .no-results {
            display: none;
            padding: 10px;
            text-align: center;
            color: #666;
        }
        .badge-success {
            background-color: #28a745;
        }
        .badge-warning {
            background-color: #ffc107;
        }
        .badge-danger {
            background-color: #dc3545;
        }
    </style>
</head>
<body class="dashboard-body">
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2"><i class="bi bi-receipt"></i> <?= htmlspecialchars($titulo) ?></h1>
                <p class="lead mb-0">Seleccione una venta para generar la nota</p>
            </div>
        </div>

        <!-- Campo único de búsqueda -->
        <div class="card shadow search-box">
            <div class="card-body">
                <input type="text" id="filtro" name="filtro" 
                       placeholder="Buscar por ID, cliente, fecha o estado...">
            </div>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <div class="no-results alert alert-info">No se encontraron ventas que coincidan con la búsqueda</div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-ventas">
                            <?php foreach ($ventas as $venta): ?>
                            <tr data-search="<?=
                                strtolower(
                                    htmlspecialchars($venta['id_notaPedido'] . ' ' .
                                        ($venta['nombre_Cliente'] ?? '') . ' ' .
                                        date('d/m/Y', strtotime($venta['fechaPedido'])) . ' ' .
                                        $venta['estado']
                                    )
                                )
                            ?>">
                                <td><?= $venta['id_notaPedido'] ?></td>
                                <td><?= date('d/m/Y', strtotime($venta['fechaPedido'])) ?></td>
                                <td><?= htmlspecialchars($venta['nombre_Cliente'] ?? 'Sin cliente') ?></td>
                                <td>$<?= number_format($venta['total'], 2) ?></td>
                                <td>
                                    <?php
                                    $color = '';
                                    switch($venta['estado']) {
                                        case 'completado':
                                            $color = 'success';
                                            break;
                                        case 'cancelado':
                                            $color = 'danger';
                                            break;
                                        default:
                                            $color = 'warning';
                                    }
                                    ?>
                                    <span class="badge badge-<?= $color ?>">
                                        <?= ucfirst($venta['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="generar_nota.php?id=<?= $venta['id_notaPedido'] ?>" 
                                       class="btn btn-sm btn-primary">
                                        Generar Nota
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filtro = document.getElementById('filtro');
            const filas = document.querySelectorAll('#tabla-ventas tr');
            const noResults = document.querySelector('.no-results');
            
            filtro.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                let hasResults = false;
                
                filas.forEach(function(fila) {
                    const searchData = fila.getAttribute('data-search');
                    if (searchData.includes(searchTerm)) {
                        fila.style.display = '';
                        hasResults = true;
                    } else {
                        fila.style.display = 'none';
                    }
                });
                
                // Mostrar u ocultar mensaje de no resultados
                noResults.style.display = hasResults ? 'none' : 'block';
            });
        });
    </script>

    <?php require __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>