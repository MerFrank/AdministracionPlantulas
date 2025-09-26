<?php
// ==============================================
// SECCIÓN PHP - Configuración y Lógica Principal
// ==============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir solo archivo de configuración (sin functions.php)
require_once __DIR__ . '/../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ==============================================
// MANEJADOR DE PETICIONES AJAX
// ==============================================

if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['ajax_action']) {
            case 'get_colores':
                $id_especie = (int)$_GET['id_especie'];
                $stmt = $con->prepare("SELECT id_color, nombre_color FROM colores WHERE id_especie = ? ORDER BY nombre_color");
                $stmt->execute([$id_especie]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'get_variedades':
                $id_especie = (int)$_GET['id_especie'];
                $id_color = (int)$_GET['id_color'];
                $stmt = $con->prepare("SELECT id_variedad, nombre_variedad, codigo FROM variedades WHERE id_especie = ? AND id_color = ? AND activo = 1 ORDER BY nombre_variedad");
                $stmt->execute([$id_especie, $id_color]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            default:
                echo json_encode(['error' => 'Acción AJAX no válida']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ==============================================
// FUNCIONES Y LÓGICA DE COTIZACIÓN
// ==============================================

function generarFolio($con) {
    $stmt = $con->query("SELECT MAX(id_cotizacion) as ultimo_id FROM cotizaciones");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $ultimo_id = $result['ultimo_id'] ?? 0;
    return 'COT-' . date('Y') . '-' . str_pad($ultimo_id + 1, 4, '0', STR_PAD_LEFT);
}

// Obtener datos iniciales
$clientes = $con->query("SELECT id_cliente, nombre_Cliente as nombre, telefono, nombre_Empresa 
                        FROM clientes 
                        WHERE activo = 1 
                        ORDER BY nombre_Cliente")->fetchAll();

$especies = $con->query("SELECT id_especie, nombre FROM especies ORDER BY nombre")->fetchAll();

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar formulario
$error = '';
$folio = generarFolio($con);
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $con->beginTransaction();

        // Validar campos requeridos
        $camposRequeridos = [
            'id_cliente' => 'Cliente',
            'fecha' => 'Fecha',
            'valida_hasta' => 'Válida hasta',
            'items' => 'Items de cotización'
        ];

        foreach ($camposRequeridos as $campo => $nombre) {
            if (empty($_POST[$campo])) {
                throw new Exception("El campo $nombre es requerido");
            }
        }

        // Validar items
        $items = json_decode($_POST['items'], true);
        if (empty($items)) {
            throw new Exception("Debe agregar al menos un item a la cotización");
        }

        // Calcular total
        $total = 0;
        foreach ($items as $item) {
            $total += $item['cantidad'] * $item['precio_unitario'];
        }

        // Insertar la cotización
        $sql = "INSERT INTO cotizaciones (
                    folio, id_cliente, fecha, valida_hasta, 
                    total, observaciones, estado, items
                ) VALUES (
                    :folio, :id_cliente, :fecha, :valida_hasta, 
                    :total, :observaciones, 'pendiente', :items
                )";

        $stmt = $con->prepare($sql);
        $stmt->execute([
            'folio' => $folio,
            'id_cliente' => (int)$_POST['id_cliente'],
            'fecha' => $_POST['fecha'],
            'valida_hasta' => $_POST['valida_hasta'],
            'total' => $total,
            'observaciones' => !empty($_POST['observaciones']) ? htmlspecialchars(trim($_POST['observaciones'])) : null,
            'items' => $_POST['items']
        ]);

        $id_cotizacion = $con->lastInsertId();

        // Insertar items de cotización
        foreach ($items as $item) {
            $sqlItem = "INSERT INTO detallescotizacion (
                id_cotizacion, id_especie, id_variedad, id_color, cantidad, 
                precio_unitario, subtotal, observaciones
            ) VALUES (
                :id_cotizacion, :id_especie, :id_variedad, :id_color, :cantidad, 
                :precio_unitario, :subtotal, :observaciones
            )";

            $stmtItem = $con->prepare($sqlItem);
            $stmtItem->execute([
                'id_cotizacion' => $id_cotizacion,
                'id_especie' => $item['id_especie'],
                'id_variedad' => $item['id_variedad'] ?? null,
                'id_color' => $item['id_color'] ?? null,
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'subtotal' => $item['cantidad'] * $item['precio_unitario'],
                'observaciones' => $item['observaciones'] ?? null
            ]);
        }

        $con->commit();

        $_SESSION['success_message'] = 'Cotización registrada correctamente con folio: ' . $folio;
        header('Location: lista_cotizaciones.php');
        exit;
    } catch (Exception $e) {
        $con->rollBack();
        $error = $e->getMessage();
    }
}

// ==============================================
// CONFIGURACIÓN PARA EL HEADER
// ==============================================

$titulo = 'Nueva Cotización';
$encabezado = 'Nueva Cotización';
$ruta = "dashboard_cotizaciones.php";
$texto_boton = "Regesar";

require_once __DIR__ . '/../../includes/header.php';
?>



<main class="container-fluid mt-4 px-0">
    <div class="card shadow border-0 rounded-0">
        <div class="card-header bg-primary text-white">
            <div class="d-flex align-items-center">
                <h2 class="mb-0"><i class="bi bi-file-text"></i> Nueva Cotización</h2>
            </div>
        </div>
        
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <form method="post" id="cotizacionForm" class="form-doble-columna" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="items" id="itemsCotizacion" value="">
            
            <div class="row g-3">
                <!-- Sección Datos Básicos -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Folio</label>
                        <input type="text" class="form-control" value="<?= $folio ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="fecha" required 
                                value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Válida hasta <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="valida_hasta" required 
                                value="<?= htmlspecialchars($_POST['valida_hasta'] ?? date('Y-m-d', strtotime('+15 days'))) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select class="form-select" name="id_cliente" id="selectCliente" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= $cliente['id_cliente'] ?>" 
                                        <?= (isset($_POST['id_cliente']) && $_POST['id_cliente'] == $cliente['id_cliente']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cliente['nombre']) ?> - <?= htmlspecialchars($cliente['telefono']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Sección Items -->
                <div class="col-12">
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h3 class="h5 mb-0"><i class="bi bi-list-check"></i> Items de Cotización</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3" id="formItem">
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label">Especie <span class="text-danger">*</span></label>
                                    <select class="form-select" id="selectEspecie" name="especie" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($especies as $especie): ?>
                                            <option value="<?= $especie['id_especie'] ?>">
                                                <?= htmlspecialchars($especie['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label">Color</label>
                                    <select class="form-select" id="selectColor" name="color" disabled>
                                        <option value="">Seleccione especie primero</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label">Variedad</label>
                                    <select class="form-select" id="selectVariedad" name="variedad" disabled>
                                        <option value="">Seleccione color primero</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-1 col-sm-3">
                                    <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="inputCantidad" name="cantidad" min="1" value="1">
                                </div>
                                
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Precio Unitario <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="inputPrecio" name="precio_unitario" step="0.01" min="0.01" placeholder="0.00">
                                    </div>
                                </div>
                                
                                <div class="col-md-1 col-sm-3 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary w-100" id="btnAgregarItem">
                                        <i class="bi bi-plus-circle"></i> Agregar
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="tablaItems">
                                        <thead>
                                            <tr>
                                                <th>Especie</th>
                                                <th>Color</th>
                                                <th>Variedad</th>
                                                <th>Cantidad</th>
                                                <th>P. Unitario</th>
                                                <th>Subtotal</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyItems">
                                            <!-- Items se agregarán aquí dinámicamente -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="5" class="text-end">Total:</th>
                                                <th id="totalCotizacion">$0.00</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sección Observaciones (movida al final) -->
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="lista_cotizaciones.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary" id="btnRegistrar">
                    <i class="bi bi-save"></i> Guardar Cotización
                </button>
            </div>
        </form>
        
    </div>
</main>



<!-- ============================================== -->
<!-- SECCIÓN JAVASCRIPT -->
<!-- ============================================== -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Variables globales
    const items = [];
    const selectEspecie = $('#selectEspecie');
    const selectColor = $('#selectColor');
    const selectVariedad = $('#selectVariedad');
    const inputCantidad = $('#inputCantidad');
    const inputPrecio = $('#inputPrecio');
    const btnAgregarItem = $('#btnAgregarItem');
    const tbodyItems = $('#tbodyItems');
    const totalCotizacion = $('#totalCotizacion');
    const form = $('#cotizacionForm');
    const itemsCotizacion = $('#itemsCotizacion');
    const btnRegistrar = $('#btnRegistrar');
    
    // Cargar colores cuando se selecciona una especie
    selectEspecie.on('change', function() {
        const idEspecie = $(this).val();
        
        if (!idEspecie) {
            selectColor.prop('disabled', true).html('<option value="">Seleccione especie primero</option>');
            selectVariedad.prop('disabled', true).html('<option value="">Seleccione color primero</option>');
            return;
        }
        
        $.get('registro_cotizacion.php', {
            ajax_action: 'get_colores',
            id_especie: idEspecie
        }, function(data) {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            if (data.length > 0) {
                let options = '<option value="">Seleccione color</option>';
                data.forEach(color => {
                    options += `<option value="${color.id_color}">${color.nombre_color}</option>`;
                });
                
                selectColor.prop('disabled', false).html(options);
                selectVariedad.prop('disabled', true).html('<option value="">Seleccione color primero</option>');
            } else {
                selectColor.prop('disabled', true).html('<option value="">No hay colores para esta especie</option>');
                selectVariedad.prop('disabled', true).html('<option value="">Seleccione color primero</option>');
            }
        }).fail(function() {
            alert('Error al cargar colores');
        });
    });
    
    // Cargar variedades cuando se selecciona un color
    selectColor.on('change', function() {
        const idColor = $(this).val();
        const idEspecie = selectEspecie.val();
        
        if (!idColor) {
            selectVariedad.prop('disabled', true).html('<option value="">Seleccione color primero</option>');
            return;
        }
        
        $.get('registro_cotizacion.php', {
            ajax_action: 'get_variedades',
            id_especie: idEspecie,
            id_color: idColor
        }, function(data) {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            if (data.length > 0) {
                let options = '<option value="">Seleccione variedad</option>';
                data.forEach(variedad => {
                    const displayText = variedad.codigo 
                        ? `${variedad.nombre_variedad} (${variedad.codigo})` 
                        : variedad.nombre_variedad;
                    options += `<option value="${variedad.id_variedad}">${displayText}</option>`;
                });
                
                selectVariedad.prop('disabled', false).html(options);
            } else {
                selectVariedad.prop('disabled', true).html('<option value="">No hay variedades para este color</option>');
            }
        }).fail(function() {
            alert('Error al cargar variedades');
        });
    });
    
    // Agregar item a la cotización
    btnAgregarItem.on('click', function() {
        // Validar campos requeridos
        if (!selectEspecie.val()) {
            alert('Seleccione una especie');
            selectEspecie.focus();
            return;
        }
        
        if (!selectColor.val()) {
            alert('Seleccione un color');
            selectColor.focus();
            return;
        }
        
        if (!inputCantidad.val() || inputCantidad.val() <= 0) {
            alert('Ingrese una cantidad válida');
            inputCantidad.focus();
            return;
        }
        
        if (!inputPrecio.val() || inputPrecio.val() <= 0) {
            alert('Ingrese un precio válido');
            inputPrecio.focus();
            return;
        }
        
        // Obtener textos de las opciones seleccionadas
        const especieText = selectEspecie.find('option:selected').text();
        const colorText = selectColor.find('option:selected').text();
        const variedadText = selectVariedad.val() ? selectVariedad.find('option:selected').text() : 'N/A';
        const variedadId = selectVariedad.val() || null;
        
        // Crear objeto item
        const item = {
            id_especie: selectEspecie.val(),
            especie: especieText,
            id_color: selectColor.val(),
            color: colorText,
            id_variedad: variedadId,
            variedad: variedadText,
            cantidad: parseInt(inputCantidad.val()),
            precio_unitario: parseFloat(inputPrecio.val()),
            subtotal: parseInt(inputCantidad.val()) * parseFloat(inputPrecio.val()),
            observaciones: ''
        };
        
        // Agregar item al array
        items.push(item);
        
        // Actualizar tabla
        actualizarTablaItems();
        
        // Limpiar inputs
        inputCantidad.val('1');
        inputPrecio.val('');
    });
    
    // Función para actualizar la tabla de items
    function actualizarTablaItems() {
        // Limpiar tabla
        tbodyItems.empty();
        
        // Calcular total
        let total = 0;
        
        // Agregar cada item a la tabla
        items.forEach((item, index) => {
            const row = $(`
                <tr data-index="${index}">
                    <td>${item.especie}</td>
                    <td>${item.color}</td>
                    <td>${item.variedad}</td>
                    <td>${item.cantidad}</td>
                    <td>$${item.precio_unitario.toFixed(2)}</td>
                    <td>$${item.subtotal.toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger btnEliminarItem">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
            
            tbodyItems.append(row);
            total += item.subtotal;
        });
        
        // Actualizar total
        totalCotizacion.text(`$${total.toFixed(2)}`);
        
        // Actualizar campo hidden con los items en formato JSON
        itemsCotizacion.val(JSON.stringify(items));
    }
    
    // Eliminar item de la cotización
    tbodyItems.on('click', '.btnEliminarItem', function() {
        const row = $(this).closest('tr');
        const index = parseInt(row.data('index'));
        
        // Confirmar eliminación
        if (confirm('¿Eliminar este item de la cotización?')) {
            items.splice(index, 1);
            actualizarTablaItems();
        }
    });
    
    // Validar formulario antes de enviar
    form.on('submit', function(e) {
        // Validar que haya al menos un item
        if (items.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un item a la cotización');
            return;
        }
        
        // Validar fecha de validez
        const fechaValidez = new Date($('[name="valida_hasta"]').val());
        const fechaActual = new Date();
        fechaActual.setHours(0, 0, 0, 0);
        
        if (fechaValidez < fechaActual) {
            e.preventDefault();
            alert('La fecha de validez debe ser igual o posterior a la fecha actual');
            return;
        }
        
        // Mostrar loader en el botón
        btnRegistrar.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');
        btnRegistrar.prop('disabled', true);
    });
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>