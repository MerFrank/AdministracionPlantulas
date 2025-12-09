<?php
// Habilitar mostrar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicio de sesión debe ir al principio
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Crear instancia de Database y obtener conexión PDO
$database = new Database();
$pdo = $database->conectar();

// Verificar si hay conexión a la base de datos
try {
    if (!$pdo) {
        throw new Exception("No hay conexión a la base de datos");
    }
} catch (Exception $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Obtener parámetros de filtro
$semana_actual = $_GET['semana'] ?? date('Y-m-d');
$accion = $_GET['accion'] ?? '';

// Calcular fechas de la semana
if ($accion === 'anterior') {
    $semana_actual = date('Y-m-d', strtotime($semana_actual . ' -7 days'));
} elseif ($accion === 'siguiente') {
    $semana_actual = date('Y-m-d', strtotime($semana_actual . ' +7 days'));
}

// Calcular inicio y fin de semana (lunes a domingo)
$inicio_semana = date('Y-m-d', strtotime('monday this week', strtotime($semana_actual)));
$fin_semana = date('Y-m-d', strtotime('sunday this week', strtotime($semana_actual)));

// Obtener nóminas de la semana
function obtenerNominasPorSemana($pdo, $inicio_semana, $fin_semana) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ng.*,
                c.nombre AS nombre_cuenta, 
                o.nombre nombre_operador
            FROM nomina_general ng
            LEFT JOIN cuentas_bancarias c ON ng.id_cuenta = c.id_cuenta
            LEFT JOIN operadores o ON ng.id_operador = o.ID_Operador
            WHERE ng.fecha_inicio >= ? 
            AND ng.fecha_inicio <= ?
            ORDER BY ng.fecha_inicio DESC, ng.id_nomina_general ASC
        ");
        
        $stmt->execute([$inicio_semana, $fin_semana]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al obtener nóminas: " . $e->getMessage());
        return [];
    }
}

// Obtener todas las semanas disponibles para el filtro
function obtenerSemanasDisponibles($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT DATE(fecha_inicio) as fecha
            FROM nomina_general 
            ORDER BY fecha DESC
        ");
        
        $stmt->execute();
        $fechas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $semanas = [];
        foreach ($fechas as $fecha) {
            $inicio_semana = date('Y-m-d', strtotime('monday this week', strtotime($fecha['fecha'])));
            $fin_semana = date('Y-m-d', strtotime('sunday this week', strtotime($fecha['fecha'])));
            $semana_key = $inicio_semana . '_' . $fin_semana;
            
            if (!isset($semanas[$semana_key])) {
                $semanas[$semana_key] = [
                    'inicio' => $inicio_semana,
                    'fin' => $fin_semana,
                    'label' => date('d/m/Y', strtotime($inicio_semana)) . ' - ' . date('d/m/Y', strtotime($fin_semana))
                ];
            }
        }
        
        return array_values($semanas);
        
    } catch (Exception $e) {
        error_log("Error al obtener semanas disponibles: " . $e->getMessage());
        return [];
    }
}

// Obtener datos
$nominas = obtenerNominasPorSemana($pdo, $inicio_semana, $fin_semana);
$semanas_disponibles = obtenerSemanasDisponibles($pdo);

// Calcular totales
// $totales = [
//     'sueldo_base' => 0,
//     'pago_actividades' => 0,
//     'descuentos' => 0,
//     'total_pagar' => 0,
//     'empleados' => 0
// ];

// foreach ($nominas as $nomina) {
//     $totales['sueldo_base'] += $nomina['sueldo_base'];
//     $totales['pago_actividades'] += $nomina['pago_actividades_extras'];
//     $totales['descuentos'] += $nomina['descuento_registros'];
//     $totales['total_pagar'] += $nomina['total_pagar'];
//     $totales['empleados']++;
// }

$titulo = "Nóminas Guardadas";
$encabezado = "Nóminas Guardadas";
$subtitulo = "Visualización y consulta de nóminas históricas";
$active_page = "nomina";
$ruta = "dashboard_nomina.php";
$texto_boton = "";
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ESTILOS ESPECÍFICOS PARA NÓMINAS GUARDADAS */
    .form-container-nomina {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #dee2e6;
        margin-bottom: 25px;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
    }

    .container-nomina-full {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 15px;
        margin: 0 auto;
    }

    .table-responsive-nomina {
        overflow-x: auto;
        width: 100% !important;
        margin-top: 20px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    .table-nomina {
        width: 100% !important;
        min-width: 1200px !important;
        border-collapse: collapse;
        background-color: white;
        margin-bottom: 0;
    }

    .table-nomina th,
    .table-nomina td {
        border: 1px solid #dee2e6;
        padding: 12px;
        text-align: center;
        vertical-align: middle;
    }

    .table-nomina thead {
        background-color: #45814d !important;
        color: white;
    }

    .table-nomina thead th {
        background-color: #45814d !important;
        color: white !important;
        text-transform: uppercase;
        font-weight: 500;
        padding: 1rem;
        border: none;
    }

    .section-title-nomina {
        color: #495057;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
        margin-bottom: 20px;
        width: 100% !important;
    }

    .navigation-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .week-display {
        font-size: 1.2em;
        font-weight: bold;
        color: #495057;
    }

    .nav-buttons {
        display: flex;
        gap: 10px;
    }

    .btn-nav {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-prev {
        background: #6c757d;
        color: white;
    }

    .btn-next {
        background: #007bff;
        color: white;
    }

    .btn-prev:hover {
        background: #5a6268;
    }

    .btn-next:hover {
        background: #0056b3;
    }

    .filter-container {
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .filter-select {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 5px;
        font-size: 14px;
    }

    .total-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .total-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }

    .total-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6c757d;
        text-transform: uppercase;
    }

    .total-card .amount {
        font-size: 24px;
        font-weight: bold;
        color: #495057;
    }

    .total-card.positive {
        border-left: 4px solid #28a745;
    }

    .total-card.negative {
        border-left: 4px solid #dc3545;
    }

    .total-card.neutral {
        border-left: 4px solid #007bff;
    }

    .positive-amount {
        color: #28a745;
        font-weight: 600;
    }

    .negative-amount {
        color: #dc3545;
        font-weight: 600;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .container-nomina-full {
            padding: 0 10px;
        }
        
        .table-nomina {
            min-width: 1000px !important;
        }
        
        .navigation-container {
            flex-direction: column;
            gap: 15px;
        }
        
        .total-cards {
            grid-template-columns: 1fr;
        }
    }
</style>

<main>
    <div class="container-nomina-full">
        <h1 class="section-title-nomina">Nóminas Guardadas</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" style="width: 100% !important;">
                <?= $_SESSION['success_message'] ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" style="width: 100% !important;">
                <?= $_SESSION['error_message'] ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Navegación por semanas -->
        <div class="navigation-container">
            <div class="week-display">
                📅 Semana del <?= date('d/m/Y', strtotime($inicio_semana)) ?> al <?= date('d/m/Y', strtotime($fin_semana)) ?>
            </div>
            
            <div class="nav-buttons">
                <a href="historial_nominas.php?semana=<?= $semana_actual ?>&accion=anterior" class="btn-nav btn-prev">
                    ⬅️ Semana Anterior
                </a>
                <a href="historial_nominas.php?semana=<?= date('Y-m-d') ?>" class="btn-nav" style="background: #6c757d; color: white;">
                    🏠 Semana Actual
                </a>
                <a href="historial_nominas.php?semana=<?= $semana_actual ?>&accion=siguiente" class="btn-nav btn-next">
                    Semana Siguiente ➡️
                </a>
            </div>
        </div>

        <!-- Filtro por semanas específicas -->
        <div class="filter-container">
            <label for="select_semana" style="font-weight: bold;">Saltar a semana:</label>
            <select id="select_semana" class="filter-select" onchange="if(this.value) window.location.href = 'historial_nominas.php?semana=' + this.value">
                <option value="">-- Seleccionar semana --</option>
                <?php foreach ($semanas_disponibles as $semana): ?>
                    <option value="<?= $semana['inicio'] ?>" <?= $semana['inicio'] == $inicio_semana ? 'selected' : '' ?>>
                        <?= $semana['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>


        <!-- Tabla de nóminas -->
        <div class="form-container-nomina">
            <?php if (empty($nominas)): ?>
                <div class="empty-state">
                    <div>📭</div>
                    <h3>No hay nóminas guardadas para esta semana</h3>
                    <p>No se encontraron registros de nóminas para la semana del <?= date('d/m/Y', strtotime($inicio_semana)) ?> al <?= date('d/m/Y', strtotime($fin_semana)) ?></p>
                    <a href="generar_nomina.php" class="btn-nav btn-next" style="margin-top: 15px;">
                        ➕ Generar Nueva Nómina
                    </a>
                </div>
            <?php else: ?>
                <h2 class="section-title-nomina">Detalle de Nóminas</h2>
                
                <div class="table-responsive-nomina">
                    <table class="table-nomina">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Empleado Pagados</th>
                                <th>Sueldos</th>
                                <th>Actividades Extras</th>
                                <th>Descuentos</th>
                                <th>Total a Pagar</th>
                                <th>Cuenta</th>
                                <th>Operador</th>
                                <th>Fecha Registro</th>
                            </tr>
                        </thead>
                            <tbody>
                                <?php if (!empty($nominas)): ?>
                                    <?php foreach ($nominas as $nomina): ?>
                                    <tr>
                                        <td><?= isset($nomina['fecha_inicio']) ? date('d/m/Y', strtotime($nomina['fecha_inicio'])) : 'N/A' ?></td>
                                        <td><?= isset($nomina['empleados_pagados']) ? htmlspecialchars($nomina['empleados_pagados']) : 'N/A' ?></td>
                                        <td>$<?= isset($nomina['total_sueldos']) ? number_format($nomina['total_sueldos'], 2) : '0.00' ?></td>
                                        <td>$<?= isset($nomina['total_actividades_extras']) ? number_format($nomina['total_actividades_extras'], 2) : '0.00' ?></td>
                                        <td>$<?= isset($nomina['total_deducciones']) ? number_format($nomina['total_deducciones'], 2) : '0.00' ?></td>
                                        <td>$<?= isset($nomina['total_a_pagar']) ? number_format($nomina['total_a_pagar'], 2) : '0.00' ?></td>
                                        <td><?= isset($nomina['nombre_cuenta']) ? htmlspecialchars($nomina['nombre_cuenta']) : 'N/A' ?></td>
                                        <td><?= isset($nomina['nombre_operador']) ? htmlspecialchars($nomina['nombre_operador']) : 'N/A' ?></td>
                                        <td><?= isset($nomina['fecha_registro']) ? date('d/m/Y H:i', strtotime($nomina['fecha_registro'])) : 'N/A' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No hay datos para mostrar</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="generar_nomina.php" class="btn-nav btn-next">
                        ➕ Generar Nueva Nómina
                    </a>
                    <button onclick="window.print()" class="btn-nav" style="background: #28a745; color: white; margin-left: 10px;">
                        🖨️ Imprimir Reporte
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Función para mejorar la experiencia de navegación
document.addEventListener('DOMContentLoaded', function() {
    // Agregar atajos de teclado
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            // Flecha izquierda - semana anterior
            const prevLink = document.querySelector('.btn-prev');
            if (prevLink) prevLink.click();
        } else if (e.key === 'ArrowRight') {
            // Flecha derecha - semana siguiente
            const nextLink = document.querySelector('.btn-next');
            if (nextLink) nextLink.click();
        }
    });
    
    // Mostrar información de atajos
    console.log('💡 Atajos de teclado disponibles:');
    console.log('← Flecha izquierda: Semana anterior');
    console.log('→ Flecha derecha: Semana siguiente');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>