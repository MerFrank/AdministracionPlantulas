<?php
include __DIR__ . '/../db/config.php';

$db = new Database();
$con = $db->conectar();

// Obtener clientes
$sql_clientes = $con->prepare("SELECT id_clientes, nombre_Cliente FROM Clientes");
$sql_clientes->execute();
$clientes = $sql_clientes->fetchAll(PDO::FETCH_ASSOC);

// Obtener especies
$sql_especies = $con->prepare("SELECT id_especie, nombre FROM Especies");
$sql_especies->execute();
$especies = $sql_especies->fetchAll(PDO::FETCH_ASSOC);

// Si es una solicitud AJAX para obtener variedades
if (isset($_GET['especie_id'])) {
    $especie_id = $_GET['especie_id'];
    
    $sql_variedades = $con->prepare("SELECT 
        Variedades.id_variedad,
        Variedades.nombre_variedad,
        Variedades.codigo
        FROM Variedades
        WHERE Variedades.id_especie = :especie_id
        ORDER BY Variedades.nombre_variedad");
    
    $sql_variedades->execute([':especie_id' => $especie_id]);
    $variedades = $sql_variedades->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($variedades);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cotización</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="contenedor-pagina">
        <header>
            <div class="encabezado">
                <a class="navbar-brand" href="#">
                    <img
                        src="/css/logoplantulas.png"
                        alt="Logo"
                        width="130"
                        height="124"
                        class="d-inline-block align-text-center"
                    />
                </a>
                <div>
                    <h2>Cotización</h2>
                    <p>Ofrece una propuesta a tus clientes</p>
                </div>
            </div>

            <div class="barra-navegacion">
                <nav class="navbar bg-body-tertiary">
                    <div class="container-fluid">
                        <div class="Opciones-barra">
                            <button
                                onclick="window.location.href='dashboard_clientesVentas.php'"
                            >
                                Regresar inicio
                            </button>
                        </div>
                    </div>
                </nav>
            </div>
        </header>

        <main>
            <div class="container">
                <form id="cotizacionForm" method="post" action="">
                    <h2>Crear Cotización</h2>

                    <div class="mb-3">
                        <label for="id_clientes" class="form-label">Cliente</label>
                        <select
                            class="form-select"
                            id="id_clientes"
                            name="id_clientes"
                            required
                        >
                            <option value="">Seleccione un Cliente</option>
                            <?php foreach ($clientes as $row): ?>
                            <option value="<?php echo $row['id_clientes'] ?>">
                                <?php echo $row['nombre_Cliente'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="especie" class="form-label">Especie</label>
                        <select name="especie" id="especie" class="form-select" required>
                            <option value="">Seleccione una Especie</option>
                            <?php foreach ($especies as $row): ?>
                            <option value="<?php echo $row['id_especie'] ?>">
                                <?php echo $row['nombre'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="variedad" class="form-label">Variedad</label>
                        <select name="variedad" id="variedad" class="form-select" disabled required>
                            <option value="">-- Primero seleccione una especie --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad</label>
                        <input 
                            type="number" 
                            id="cantidad" 
                            name="cantidad"
                            class="form-control"
                            placeholder="Cantidad" 
                            min="1" 
                            required
                        />
                    </div>

                    <div class="mb-3">
                        <label for="precioUnitario" class="form-label">Precio Unitario (MXN)</label>
                        <input
                            type="number"
                            id="precioUnitario"
                            name="precioUnitario"
                            class="form-control"
                            placeholder="Precio por unidad"
                            step="0.01"
                            min="0"
                            required
                        />
                    </div>

                    <button type="button" id="agregarProducto" class="btn btn-primary">Agregar producto</button>

                    <table id="tablaProductos" class="table mt-3" style="display: none">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Productos agregados -->
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3">Total:</td>
                                <td id="totalGeneral">$0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="mb-3">
                        <label for="fechaValidez" class="form-label">Fecha de Validez</label>
                        <input 
                            type="date" 
                            id="fechaValidez" 
                            name="fechaValidez"
                            class="form-control"
                            required 
                        />
                    </div>

                    <div class="mb-3">
                        <label for="notas" class="form-label">Notas adicionales</label>
                        <textarea
                            id="notas"
                            name="notas"
                            rows="4"
                            class="form-control"
                            placeholder="Comentarios o condiciones especiales"
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">Guardar Cotización</button>
                </form>
            </div>

            <script>
            $(document).ready(function() {
                // Habilitar variedad cuando se selecciona una especie
                $('#especie').change(function() {
                    var especieId = $(this).val();
                    var $variedadSelect = $('#variedad');
                    
                    if (especieId) {
                        // Habilitar el select de variedad
                        $variedadSelect.prop('disabled', false);
                        
                        // Limpiar opciones anteriores
                        $variedadSelect.empty().append('<option value="">Cargando variedades...</option>');
                        
                        // Hacer petición AJAX para obtener variedades
                        $.getJSON('', {especie_id: especieId}, function(data) {
                            if (data.length > 0) {
                                $variedadSelect.empty().append('<option value="">Seleccione una variedad</option>');
                                $.each(data, function(key, variedad) {
                                    $variedadSelect.append(
                                        $('<option></option>')
                                            .attr('value', variedad.id_variedad)
                                            .text(variedad.nombre_variedad + ' (' + variedad.codigo + ')')
                                    );
                                });
                            } else {
                                $variedadSelect.empty().append('<option value="">No hay variedades para esta especie</option>');
                            }
                        }).fail(function() {
                            $variedadSelect.empty().append('<option value="">Error al cargar variedades</option>');
                        });
                    } else {
                        // Deshabilitar si no hay especie seleccionada
                        $variedadSelect.prop('disabled', true).empty().append('<option value="">-- Primero seleccione una especie --</option>');
                    }
                });

                // Lógica para agregar productos a la tabla
                $('#agregarProducto').click(function() {
                    const especie = $('#especie option:selected').text();
                    const variedad = $('#variedad option:selected').text();
                    const cantidad = $('#cantidad').val();
                    const precioUnitario = $('#precioUnitario').val();
                    
                    if (!variedad || variedad.includes('--') || !cantidad || !precioUnitario) {
                        alert('Por favor complete todos los campos del producto');
                        return;
                    }
                    
                    const subtotal = (cantidad * precioUnitario).toFixed(2);
                    const productoId = Date.now(); // ID único para cada producto
                    
                    // Agregar fila a la tabla
                    $('#tablaProductos tbody').append(`
                        <tr id="producto-${productoId}">
                            <td>${especie} - ${variedad}</td>
                            <td>${cantidad}</td>
                            <td>$${parseFloat(precioUnitario).toFixed(2)}</td>
                            <td>$${subtotal}</td>
                            <td><button class="btn btn-danger btn-sm remover-producto" data-id="${productoId}">Eliminar</button></td>
                        </tr>
                    `);
                    
                    // Mostrar tabla si está oculta
                    $('#tablaProductos').show();
                    
                    // Actualizar total
                    actualizarTotal();
                    
                    // Limpiar campos
                    $('#cantidad').val('');
                    $('#precioUnitario').val('');
                });
                
                // Eliminar producto
                $(document).on('click', '.remover-producto', function() {
                    const productoId = $(this).data('id');
                    $(`#producto-${productoId}`).remove();
                    
                    // Ocultar tabla si no hay productos
                    if ($('#tablaProductos tbody tr').length === 0) {
                        $('#tablaProductos').hide();
                    }
                    
                    actualizarTotal();
                });
                
                // Función para actualizar el total
                function actualizarTotal() {
                    let total = 0;
                    
                    $('#tablaProductos tbody tr').each(function() {
                        const subtotal = parseFloat($(this).find('td:eq(3)').text().replace('$', ''));
                        total += subtotal;
                    });
                    
                    $('#totalGeneral').text('$' + total.toFixed(2));
                }
                
                // Enviar formulario
                $('#cotizacionForm').submit(function(e) {
                    e.preventDefault();
                    
                    // Validar que hay productos agregados
                    if ($('#tablaProductos tbody tr').length === 0) {
                        alert('Debe agregar al menos un producto');
                        return;
                    }
                    
                    // Aquí puedes agregar la lógica para enviar el formulario
                    alert('Cotización guardada correctamente');
                    // this.submit(); // Descomenta esta línea para enviar realmente el formulario
                });
            });
            </script>
        </main>

        <footer>
            <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>