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

$semanaActual = date('W'); 
$anioActual = date('Y'); 

// Obtener semana y año de la URL o usar la actual
$semanaSeleccionada = isset($_GET['semana']) ? (int)$_GET['semana'] : $semanaActual;
$anioSeleccionado = isset($_GET['anio']) ? (int)$_GET['anio'] : $anioActual;


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


$busqueda = $_GET['busqueda'] ?? '';
$params = [$fechaInicioStr, $fechaFinStr];
$where = " WHERE e.fecha BETWEEN ? AND ?";

if (!empty($busqueda)) {
    $busquedaSQL = "%$busqueda%";
    $where .= " AND (c.folio LIKE ? OR cl.nombre_Cliente LIKE ? OR c.estado LIKE ?)";
    $params[] = $busquedaSQL;
    $params[] = $busquedaSQL;
    $params[] = $busquedaSQL;
}



$sql = "
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
     {$where}
    ORDER BY e.fecha DESC
";

$stmt = $con->prepare($sql);
$stmt->execute($params);
$egresos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Listado de Egresos';
$ruta = "dashboard_egresos.php";
$texto_boton = "Regresar";
require __DIR__ . '/../../includes/header.php';

// Función para convertir número a letras
function numeroALetras($numero)
{
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
        <div class="row align-items-center">
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
                            placeholder="Buscar por Proveedor, Tipo o Sucursal..."
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

<script>
    window.generarPDFEgresos = function() {

        try {

            const doc = new window.jspdf.jsPDF({
                orientation: 'landscape',
                unit: 'mm'
            });

            const title = "Reporte de Egresos";
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
                ['Fecha', 'Tipo', 'Proveedor', 'Sucursal', 'Monto', 'Método Pago']
            ];


            <?php foreach ($egresos as $egreso): ?>
                tableData.push([
                    '<?= date('d/m/Y', strtotime($egreso['fecha'])) ?>',
                    '<?= htmlspecialchars($egreso['tipo_egreso'] ?? 'N/A') ?>',
                    '<?= htmlspecialchars($egreso['proveedor'] ?? 'N/A') ?>',
                    '<?= htmlspecialchars($egreso['sucursal'] ?? 'N/A') ?>',
                    '$<?= number_format($egreso['monto'], 2, '.', '') ?>',
                    '<?= htmlspecialchars(ucfirst($egreso['metodo_pago'] ?? 'N/A')) ?>',
                ]);

            <?php endforeach; ?>



            // Crear tabla
            doc.autoTable({
                startY: 35,
                head: [tableData[0]],
                body: tableData.slice(1),
                theme: 'grid',
                headStyles: {

                    fillColor: [0, 150, 60],
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
                    5: {
                        cellWidth: 55
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

            doc.save(`Reporte_Egresos_Semana_<?= $semanaSeleccionada ?>_<?= $anioSeleccionado ?>.pdf`);

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

                    window.generarPDFEgresos();
                } catch (error) {
                    console.error('Error al generar el PDF:', error);
                } finally {
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }
            }, 100);
        });

        // Inserta el botón de PDF después del botón 'Generar Vale'
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