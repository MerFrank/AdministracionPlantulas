<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);



require_once __DIR__ . '/../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$semanaActual = date('W'); 
$anioActual = date('Y'); 


$semanaSeleccionada = isset($_GET['semana']) ? (int)$_GET['semana'] : $semanaActual;
$anioSeleccionado = isset($_GET['anio']) ? (int)$_GET['anio'] : $anioActual;

// Validar y ajustar valores (navegación)
if ($semanaSeleccionada < 1) {
    $semanaSeleccionada = 52;
    $anioSeleccionado--;
} elseif ($semanaSeleccionada > 52) {
    $semanaSeleccionada = 1;
    $anioSeleccionado++;
}

// Calcular fechas de inicio y fin de la semana seleccionada
$fechaInicioSemana = new DateTime();
$fechaInicioSemana->setISODate($anioSeleccionado, $semanaSeleccionada);
$fechaInicioSemana->setTime(0, 0, 0);

$fechaFinSemana = clone $fechaInicioSemana;
$fechaFinSemana->modify('+6 days');
$fechaFinSemana->setTime(23, 59, 59);

$fechaInicioStr = $fechaInicioSemana->format('Y-m-d H:i:s');
$fechaFinStr = $fechaFinSemana->format('Y-m-d H:i:s');

// Procesar búsqueda de texto
$busqueda = $_GET['busqueda'] ?? '';
$params = [$fechaInicioStr, $fechaFinStr];
$where = " WHERE c.fecha BETWEEN ? AND ?";

if (!empty($busqueda)) {
    $busquedaSQL = "%$busqueda%";
    
    $where .= " AND (c.folio LIKE ? OR cl.nombre_Cliente LIKE ? OR c.estado LIKE ?)";
    $params[] = $busquedaSQL;
    $params[] = $busquedaSQL;
    $params[] = $busquedaSQL;
}


$sql = "
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
    {$where}
    ORDER BY c.fecha DESC
";


$stmt = $con->prepare($sql);
$stmt->execute($params);
$cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);


$titulo = 'Listado de Cotizaciones';
$ruta = "dashboard_cotizaciones.php";
$texto_boton = "Regresar";
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
                </div>
            </div>
        </div>

        <div class="row align-items-center mt-2">
            <div class="col-md-6 mb-3 mb-md-0">
                <form method="GET" class="d-flex align-items-center flex-wrap" id="form-navegacion">
                    <div class="d-flex align-items-center flex-grow-1">
                        <strong class="mb-0 me-1 text-nowrap ajuste-alineacion">SEMANA:</strong>
                        <input type="number" name="semana" id="input-semana"
                            value="<?= htmlspecialchars($semanaSeleccionada) ?>"
                            min="1" max="52" class="form-control form-control-sm me-2">
                        <strong class="mb-0 me-1 text-nowrap ajuste-alineacion">DE:</strong>
                        <input type="number" name="anio" id="input-anio"
                            value="<?= htmlspecialchars($anioSeleccionado) ?>"
                            min="2020" max="<?= $anioActual + 1 ?>" class="form-control form-control-sm me-2">
                    </div>
                    <button class="btn btn-secondary btn-sm"
                        style="background-color: var(--color-receipt2); border-color: var(--color-receipt2);" type="submit">
                        <i class="bi bi-arrow-right-square"></i> Ir
                    </button>
                    <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                </form>

                <div class="d-flex align-items-center mt-2">
                    <div class="btn-group ">
                        <a href="?semana=<?= $semanaSeleccionada - 1 ?>&anio=<?= $semanaSeleccionada == 1 ? $anioSeleccionado - 1 : $anioSeleccionado ?><?= !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : '' ?>"
                            class="btn btn-outline-primary">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <a href="?semana=<?= $semanaActual ?>&anio=<?= $anioActual ?><?= !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : '' ?>"
                            style="background-color: var(--color-eye); border-color: var(--color-eye);"
                            class="btn btn-sm btn-secondary rounded-3 <?= ($semanaSeleccionada == $semanaActual && $anioSeleccionado == $anioActual) ? 'active' : '' ?>">
                            Hoy
                        </a>
                        <a href="?semana=<?= $semanaSeleccionada + 1 ?>&anio=<?= $semanaSeleccionada == 52 ? $anioSeleccionado + 1 : $anioSeleccionado ?><?= !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : '' ?>"
                            class="btn btn-outline-primary">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    <p class="text-muted mt-2 mb-0 ms-2">
                        Del <?= $fechaInicioSemana->format('d/m/Y') ?> al <?= $fechaFinSemana->format('d/m/Y') ?>
                    </p>
                </div>
            </div>

            <div class="col-md-6">
                <form method="GET" id="form-busqueda">
                    <input type="hidden" name="semana" value="<?= $semanaSeleccionada ?>" id="hidden-semana">
                    <input type="hidden" name="anio" value="<?= $anioSeleccionado ?>" id="hidden-anio">

                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white">
                            <i class="bi bi-search"></i>
                        </span>

                        <input type="text" class="form-control" name="busqueda" id="busqueda"
                            placeholder="Buscar por folio, cliente o estado..."
                            value="<?= htmlspecialchars($busqueda) ?>">
                        <button class="btn btn-secondary rounded-3" style="background-color: var(--color-secondary); border-color: var(--color-secondary)" type="submit">
                            Buscar
                        </button>
                        <button class="btn btn-secondary rounded-3"
                            style="background-color: var(--color-danger); border-color: var(--color-danger)" type="button" id="limpiar-busqueda">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </form>
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
                                                                $cotizacion['estado'] == 'aprobada' ? 'success' : ($cotizacion['estado'] == 'pendiente' ? 'warning' : 'secondary')
                                                                ?>">
                                            <?= ucfirst($cotizacion['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="eliminar_cotizacion.php?id=<?= $cotizacion['id_cotizacion'] ?>"
                                                style="background-color: var(--color-danger); border-color: var(--color-danger);"
                                                class="btn btn-sm btn-primary"
                                                onclick="return confirm('¿Eliminar esta cotización?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <a href="generar_pdf_cotizacion.php?id=<?= $cotizacion['id_cotizacion'] ?>"
                                                class="btn btn-sm btn-primary "
                                                style="background-color: #2795d4ff; border-color: #2795d4ff "
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



<script>
    window.generarPDFCotizaciones = function() {
        try {

            const doc = new window.jspdf.jsPDF({
                orientation: 'landscape',
                unit: 'mm'
            });

            const title = "Reporte de Cotizaciones";
            const subtitle = `Semana <?= $semanaSeleccionada ?> del <?= $anioSeleccionado ?> (<?= $fechaInicioSemana->format('d/m/Y') ?> al <?= $fechaFinSemana->format('d/m/Y') ?>)`;
            const date = new Date().toLocaleDateString();
            const footer = `Generado el ${date}`;
            const centerPoint = doc.internal.pageSize.width / 2;


            doc.setFontSize(18);
            doc.text(title, centerPoint, 15, {
                align: 'center'
            });
            doc.setFontSize(12);
            doc.text(subtitle, centerPoint, 22, {
                align: 'center'
            });

            // Preparar datos de la tabla
            const tableData = [
                ['Folio', 'Fecha', 'Cliente', 'Teléfono', 'Total', 'Válida hasta', 'Estado']
            ];


            <?php foreach ($cotizaciones as $cotizacion): ?>
                tableData.push([
                    '<?= htmlspecialchars($cotizacion['folio']) ?>',
                    '<?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?>',
                    '<?= htmlspecialchars($cotizacion['cliente_nombre']) ?>',
                    '<?= htmlspecialchars($cotizacion['cliente_telefono']) ?>',
                    '$<?= number_format($cotizacion['total'], 2, '.', '') ?>',
                    '<?= date('d/m/Y', strtotime($cotizacion['valida_hasta'])) ?>',
                    '<?= ucfirst($cotizacion['estado']) ?>'
                ]);
            <?php endforeach; ?>


            // Crear tabla
            doc.autoTable({
                startY: 30,
                head: [tableData[0]],
                body: tableData.slice(1),
                theme: 'grid',

                headStyles: {
                    fillColor: [0, 100, 0],
                    textColor: [255, 255, 255]
                },
                margin: {
                    top: 10,
                    horizontal: 10
                },

                 bodyStyles: {
                 textColor: [0, 0, 0]
                 },
                styles: {
                    fontSize: 9,
                    cellPadding: 2
                },
                columnStyles: {

                    2: {
                        cellWidth: 50
                    }, 
                    3: {
                        cellWidth: 30
                    } 
                },
                didDrawPage: function(data) {
                    const pageCount = doc.internal.getNumberOfPages();
                    doc.setFontSize(8);
                    doc.text(footer, data.settings.margin.left, doc.internal.pageSize.height - 5);
                    doc.text(`Página ${data.pageNumber} de ${pageCount}`, doc.internal.pageSize.width - data.settings.margin.right, doc.internal.pageSize.height - 5, {
                        align: 'right'
                    });
                }
            });

            doc.save(`Reporte_Cotizaciones_Semana_<?= $semanaSeleccionada ?>_<?= $anioSeleccionado ?>.pdf`);
        } catch (error) {
            console.error('Error al generar PDF:', error);
            alert('Error al generar el PDF: ' + error.message);

        }
    };



    document.addEventListener('DOMContentLoaded', function() {

        const limpiarBtn = document.getElementById('limpiar-busqueda');
        const inputBusqueda = document.getElementById('busqueda');
        const formBusqueda = document.getElementById('form-busqueda');
        const hiddenSemana = document.getElementById('hidden-semana');
        const hiddenAnio = document.getElementById('hidden-anio');
        const urlParams = new URLSearchParams(window.location.search);


        if (sessionStorage.getItem('scrollPos') !== null) {
            window.scrollTo(0, parseInt(sessionStorage.getItem('scrollPos')));
            sessionStorage.removeItem('scrollPos');
        }

        if (formBusqueda && inputBusqueda && hiddenSemana && hiddenAnio) {



            formBusqueda.addEventListener('submit', function() {
                sessionStorage.setItem('scrollPos', window.scrollY);
            });


            inputBusqueda.addEventListener('input', function() {

                if (this.value.trim() === '') {

                    sessionStorage.setItem('scrollPos', window.scrollY);


                    window.location.href = window.location.pathname +
                        '?semana=' + hiddenSemana.value +
                        '&anio=' + hiddenAnio.value;
                    return;
                }

            });

            if (!urlParams.has('busqueda') || urlParams.get('busqueda').trim() === '') {
                inputBusqueda.value = '';
            }
        }


        if (limpiarBtn && hiddenSemana && hiddenAnio) {
            limpiarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (inputBusqueda) {
                    inputBusqueda.value = '';
                }

                window.location.href = window.location.pathname +
                    '?semana=' + hiddenSemana.value +
                    '&anio=' + hiddenAnio.value;
            });
        }




        // Lógica del Botón PDF
        const pdfBtn = document.createElement('button');
        pdfBtn.className = 'btn btn-secondary rounded-3';
        pdfBtn.style = "background-color: var(--color-receipt2); border-color: var(--color-receipt2)";
        pdfBtn.type = 'button';
        pdfBtn.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> PDF';

        pdfBtn.addEventListener('click', function() {
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            this.disabled = true;

            setTimeout(() => {
                try {

                    window.generarPDFCotizaciones();
                } catch (error) {
                    console.error('Error en la llamada al PDF:', error);
                } finally {
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }
            }, 100);
        });

        // Inserta el botón de PDF
        const searchInputGroup = document.querySelector('#form-busqueda .input-group');
        if (searchInputGroup) {
            const clearBtn = searchInputGroup.querySelector('#limpiar-busqueda');
            if (clearBtn) {
                clearBtn.insertAdjacentElement('afterend', pdfBtn);
            } else {
                searchInputGroup.appendChild(pdfBtn);
            }
        }
    });
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
