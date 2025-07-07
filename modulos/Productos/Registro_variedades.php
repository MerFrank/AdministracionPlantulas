<?php
require_once __DIR__ . '/../../includes/config.php';

$db = new Database();
$conexion = $db->conectar();

// Variables para edición
$variedadEditar = null;
$especieSeleccionada = $_POST['especie'] ?? $_GET['especie'] ?? null;

// Función para enviar respuestas JSON
function sendJsonResponse($success, $message, $data = []) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Procesamiento de formularios (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';
    
    try {
        if ($accion === 'eliminar') {
            // Validación para eliminación
            $idVariedad = filter_var($_POST['id_variedad'] ?? null, FILTER_VALIDATE_INT);
            
            if (!$idVariedad || $idVariedad <= 0) {
                sendJsonResponse(false, 'ID de variedad no válido');
            }

            // Iniciar transacción
            $conexion->beginTransaction();

            // Verificar que la variedad existe y está activa
            $sqlCheck = "SELECT id_variedad FROM variedades WHERE id_variedad = :id AND activo = 1";
            $stmtCheck = $conexion->prepare($sqlCheck);
            $stmtCheck->bindParam(':id', $idVariedad, PDO::PARAM_INT);
            $stmtCheck->execute();
            
            if ($stmtCheck->rowCount() === 0) {
                throw new Exception('La variedad no existe o ya fue eliminada');
            }

            // Borrado lógico (marcar como inactivo)
            $sql = "UPDATE variedades SET activo = 0 WHERE id_variedad = :id";
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':id', $idVariedad, PDO::PARAM_INT);
            
            if (!$stmt->execute()) {
                throw new Exception('Error al marcar la variedad como eliminada');
            }

            $conexion->commit();
            
            sendJsonResponse(true, 'Variedad marcada como eliminada');
            
        } else {
            // Validación para creación/edición
            $idVariedad = filter_var($_POST['id_variedad'] ?? null, FILTER_VALIDATE_INT);
            $idEspecie = filter_var($_POST['especie'] ?? null, FILTER_VALIDATE_INT);
            $idColor = filter_var($_POST['color'] ?? null, FILTER_VALIDATE_INT);
            $nombreVariedad = htmlspecialchars(trim($_POST['nombreVariedad'] ?? ''));
            $codigoVariedad = htmlspecialchars(trim($_POST['codigoVariedad'] ?? ''));
            
            // Validar campos obligatorios
            if (empty($idEspecie) || empty($idColor) || empty($nombreVariedad) || empty($codigoVariedad)) {
                sendJsonResponse(false, 'Complete todos los campos obligatorios');
            }

            // Validar longitudes
            if (strlen($nombreVariedad) < 2 || strlen($nombreVariedad) > 100) {
                sendJsonResponse(false, 'El nombre debe tener entre 2 y 100 caracteres');
            }

            if (strlen($codigoVariedad) < 2 || strlen($codigoVariedad) > 50) {
                sendJsonResponse(false, 'El código debe tener entre 2 y 50 caracteres');
            }

            // Verificar duplicados de código
            $sqlCheck = "SELECT id_variedad FROM variedades WHERE codigo = :codigo AND activo = 1";
            if ($accion === 'editar') {
                $sqlCheck .= " AND id_variedad != :id_variedad";
            }
            
            $stmtCheck = $conexion->prepare($sqlCheck);
            $stmtCheck->bindParam(':codigo', $codigoVariedad);
            if ($accion === 'editar') {
                $stmtCheck->bindParam(':id_variedad', $idVariedad, PDO::PARAM_INT);
            }
            $stmtCheck->execute();
            
            if ($stmtCheck->rowCount() > 0) {
                sendJsonResponse(false, 'Este código ya está registrado');
            }

            // Insertar o actualizar
            if ($accion === 'crear') {
                $sql = "INSERT INTO variedades (id_especie, id_color, nombre_variedad, codigo, activo) 
                        VALUES (:id_especie, :id_color, :nombre, :codigo, 1)";
            } else {
                $sql = "UPDATE variedades SET 
                        id_especie = :id_especie, 
                        id_color = :id_color, 
                        nombre_variedad = :nombre, 
                        codigo = :codigo 
                        WHERE id_variedad = :id_variedad";
            }
            
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':id_especie', $idEspecie, PDO::PARAM_INT);
            $stmt->bindParam(':id_color', $idColor, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombreVariedad);
            $stmt->bindParam(':codigo', $codigoVariedad);
            
            if ($accion === 'editar') {
                $stmt->bindParam(':id_variedad', $idVariedad, PDO::PARAM_INT);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Error al ejecutar la operación');
            }
            
            // Obtener datos para respuesta
            $lastId = $accion === 'crear' ? $conexion->lastInsertId() : $idVariedad;
            $nombreEspecie = $conexion->query("SELECT nombre FROM especies WHERE id_especie = $idEspecie")->fetchColumn();
            $nombreColor = $conexion->query("SELECT nombre_color FROM colores WHERE id_color = $idColor")->fetchColumn();
            
            sendJsonResponse(true, 
                $accion === 'crear' ? 'Variedad registrada con éxito' : 'Variedad actualizada con éxito',
                [
                    'id_variedad' => $lastId,
                    'especie' => $nombreEspecie,
                    'color' => $nombreColor,
                    'variedad' => $nombreVariedad,
                    'codigo' => $codigoVariedad
                ]
            );
        }
    } catch(PDOException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log('Error en gestión de variedades: ' . $e->getMessage());
        sendJsonResponse(false, 'Error en la base de datos: ' . $e->getMessage());
    } catch(Exception $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log('Error en variedades: ' . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
}

// Cargar datos para edición
if (isset($_GET['editar'])) {
    try {
        $idEditar = filter_var($_GET['editar'], FILTER_VALIDATE_INT);
        if (!$idEditar || $idEditar <= 0) {
            throw new Exception('ID de edición no válido');
        }

        $sql = "SELECT v.*, e.nombre as nombre_especie, c.nombre_color 
                FROM variedades v
                JOIN especies e ON v.id_especie = e.id_especie
                JOIN colores c ON v.id_color = c.id_color
                WHERE v.id_variedad = :id AND v.activo = 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id', $idEditar, PDO::PARAM_INT);
        $stmt->execute();
        $variedadEditar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$variedadEditar) {
            throw new Exception('Variedad no encontrada o eliminada');
        }
    } catch(Exception $e) {
        echo "<script>alert('".addslashes($e->getMessage())."');</script>";
    }
}

// Obtener listado de especies
$especies = [];
try {
    $sql = "SELECT id_especie, nombre FROM especies ORDER BY nombre";
    $stmt = $conexion->query($sql);
    $especies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorEspecies = "Error al cargar especies: " . $e->getMessage();
}

// Obtener colores para la especie seleccionada
$colores = [];
if (isset($variedadEditar) || $especieSeleccionada) {
    $idEspecie = $variedadEditar['id_especie'] ?? $especieSeleccionada;
    try {
        $sql = "SELECT id_color, nombre_color FROM colores WHERE id_especie = :id_especie ORDER BY nombre_color";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id_especie', $idEspecie, PDO::PARAM_INT);
        $stmt->execute();
        $colores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $errorColores = "Error al cargar colores: " . $e->getMessage();
    }
}

// Obtener variedades activas
$variedades = [];
try {
    $sql = "SELECT v.id_variedad, e.nombre AS especie, c.nombre_color AS color, 
                   v.nombre_variedad, v.codigo, v.id_especie
            FROM variedades v
            JOIN especies e ON v.id_especie = e.id_especie
            JOIN colores c ON v.id_color = c.id_color
            WHERE v.activo = 1";
    
    if ($especieSeleccionada) {
        $sql .= " AND v.id_especie = :id_especie";
    }
    
    $sql .= " ORDER BY e.nombre, c.nombre_color, v.nombre_variedad";
    
    $stmt = $especieSeleccionada ? $conexion->prepare($sql) : $conexion->query($sql);
    
    if ($especieSeleccionada) {
        $stmt->bindParam(':id_especie', $especieSeleccionada, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    $variedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorVariedades = "Error al cargar variedades: " . $e->getMessage();
}



// Configuración de encabezado
$titulo = "Gestión de Variedades";
$encabezado = "Panel de Control de Variedadesuctos";
$subtitulo = "Administra inventario de variedades";

// Incluir la cabecera (ruta relativa al archivo actual)
$ruta = "dashboard_registroProducto.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>


<main class="container py-4">
    <div class="toast-container">
        <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Notificación</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage"></div>
        </div>
    </div>

    <div class="form-container">
        <h4 class="mb-4"><?= isset($variedadEditar) ? 'Editar Variedad' : 'Nueva Variedad' ?></h4>
        <form id="variedadForm" method="POST">
            <input type="hidden" name="accion" value="<?= isset($variedadEditar) ? 'editar' : 'crear' ?>">
            <input type="hidden" name="id_variedad" value="<?= $variedadEditar['id_variedad'] ?? '' ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="selectEspecie" class="form-label">Especie <span class="text-danger">*</span></label>
                    <select class="form-select" id="selectEspecie" name="especie" required>
                        <option value="">-- Seleccione una especie --</option>
                        <?php foreach ($especies as $especie): ?>
                            <option value="<?= $especie['id_especie'] ?>" 
                                <?= ($especie['id_especie'] == ($variedadEditar['id_especie'] ?? $especieSeleccionada ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($especie['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="selectColor" class="form-label">Color <span class="text-danger">*</span></label>
                    <select class="form-select" id="selectColor" name="color" required <?= (!isset($variedadEditar) && !$especieSeleccionada) ? 'disabled' : '' ?>>
                        <option value="">-- Seleccione un color --</option>
                        <?php if (isset($variedadEditar) || $especieSeleccionada): ?>
                            <?php foreach ($colores as $color): ?>
                                <option value="<?= $color['id_color'] ?>" 
                                    <?= (isset($variedadEditar) && $color['id_color'] == $variedadEditar['id_color']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($color['nombre_color']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <label for="nombreVariedad" class="form-label">Nombre de la Variedad <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nombreVariedad" name="nombreVariedad" 
                            value="<?= htmlspecialchars($variedadEditar['nombre_variedad'] ?? '') ?>" 
                            minlength="2" maxlength="100" required>
                </div>
                <div class="col-md-6">
                    <label for="codigoVariedad" class="form-label">Código Único <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="codigoVariedad" name="codigoVariedad" 
                            value="<?= htmlspecialchars($variedadEditar['codigo'] ?? '') ?>" 
                            minlength="2" maxlength="50" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-success px-4">
                    <i class="fas fa-save me-2"></i>
                    <span class="btn-text"><?= isset($variedadEditar) ? 'Actualizar' : 'Guardar' ?></span>
                    <i class="fas fa-spinner fa-spin d-none"></i>
                </button>
                <?php if (isset($variedadEditar)): ?>
                    <a href="Registro_variedades.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-container">
        <?php if ($especieSeleccionada): ?>
            <?php 
            $nombreEspecie = '';
            foreach ($especies as $especie) {
                if ($especie['id_especie'] == $especieSeleccionada) {
                    $nombreEspecie = $especie['nombre'];
                    break;
                }
            }
            ?>
            <div class="current-filter mb-3">
                <h5>Mostrando variedades para: <strong><?= htmlspecialchars($nombreEspecie) ?></strong></h5>
                <a href="Registro_variedades.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times"></i> Mostrar todas
                </a>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Especie</th>
                        <th>Color</th>
                        <th>Variedad</th>
                        <th>Código</th>
                        <th class="actions-cell">Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaVariedades">
                    <?php if (!empty($variedades)): ?>
                        <?php foreach ($variedades as $variedad): ?>
                            <tr data-id="<?= $variedad['id_variedad'] ?>">
                                <td><?= htmlspecialchars($variedad['especie']) ?></td>
                                <td><?= htmlspecialchars($variedad['color']) ?></td>
                                <td><?= htmlspecialchars($variedad['nombre_variedad']) ?></td>
                                <td><?= htmlspecialchars($variedad['codigo']) ?></td>
                                <td class="actions-cell">
                                    <a href="Registro_variedades.php?editar=<?= $variedad['id_variedad'] ?>" 
                                        class="btn btn-sm btn-warning" title="Editar">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <button onclick="confirmarEliminacion(<?= $variedad['id_variedad'] ?>)" 
                                            class="btn btn-sm btn-danger" title="Eliminar">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-message">
                                <?= isset($errorVariedades) ? $errorVariedades : 'No hay variedades registradas' ?>
                                <?= $especieSeleccionada ? ' para la especie seleccionada' : '' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require('../../includes/footer.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Inicializar toast
    const toastLiveExample = document.getElementById('liveToast');
    const toast = new bootstrap.Toast(toastLiveExample);
    
    // Función para mostrar notificación
    function mostrarNotificacion(titulo, mensaje, tipo = 'success') {
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');
        
        toastTitle.textContent = titulo;
        toastMessage.textContent = mensaje;
        
        // Cambiar color según el tipo
        const toastHeader = toastLiveExample.querySelector('.toast-header');
        toastHeader.className = 'toast-header';
        toastHeader.classList.add(`bg-${tipo}`, 'text-white');
        
        toast.show();
    }

    // Función para cargar colores
    function cargarColores(especieId) {
        const colorSelect = document.getElementById('selectColor');
        
        colorSelect.innerHTML = '<option value="">-- Seleccione un color --</option>';
        colorSelect.disabled = !especieId;
        
        if (especieId) {
            fetch(`get_colores.php?especie=${especieId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error al cargar colores');
                    return response.json();
                })
                .then(data => {
                    if (data.length > 0) {
                        data.forEach(color => {
                            const option = document.createElement('option');
                            option.value = color.id_color;
                            option.textContent = color.nombre_color;
                            colorSelect.appendChild(option);
                        });
                        colorSelect.disabled = false;
                    } else {
                        colorSelect.innerHTML = '<option value="">-- No hay colores para esta especie --</option>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarNotificacion('Error', 'Error al cargar colores', 'danger');
                    colorSelect.innerHTML = '<option value="">-- Error al cargar colores --</option>';
                });
        }
    }

    // Evento change para select de especie
    document.getElementById('selectEspecie').addEventListener('change', function() {
        const especieId = this.value;
        cargarColores(especieId);
        
        // Actualizar la tabla si no estamos editando
        <?php if (!isset($variedadEditar)): ?>
            if (especieId) {
                window.location.href = `Registro_variedades.php?especie=${especieId}`;
            } else {
                window.location.href = 'Registro_variedades.php';
            }
        <?php endif; ?>
    });

    // Función para confirmar y eliminar variedad
    function confirmarEliminacion(idVariedad) {
        if (confirm('¿Está seguro que desea marcar esta variedad como eliminada? Podrá restaurarla más tarde si es necesario.')) {
            eliminarVariedad(idVariedad);
        }
    }

    // Función para eliminar variedad (borrado lógico)
    function eliminarVariedad(idVariedad) {
        const btn = document.querySelector(`button[onclick="confirmarEliminacion(${idVariedad})"]`);
        const originalHTML = btn.innerHTML;
        
        // Mostrar spinner
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('accion', 'eliminar');
        formData.append('id_variedad', idVariedad);
        
        fetch('Registro_variedades.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(text || 'Error en la respuesta del servidor');
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data) throw new Error('Respuesta vacía del servidor');
            
            if (data.success) {
                mostrarNotificacion('Éxito', data.message);
                // Eliminar la fila visualmente
                document.querySelector(`tr[data-id="${idVariedad}"]`)?.remove();
                
                // Si no quedan filas, mostrar mensaje
                if (!document.querySelector('#listaVariedades tr:not(.empty-message)')) {
                    document.getElementById('listaVariedades').innerHTML = 
                        '<tr><td colspan="5" class="empty-message">No hay variedades registradas</td></tr>';
                }
            } else {
                throw new Error(data.message || 'Error desconocido');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error', error.message || 'Error al eliminar variedad', 'danger');
        })
        .finally(() => {
            // Restaurar el botón
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        });
    }

    // Manejar envío del formulario con AJAX
    document.getElementById('variedadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const spinner = submitBtn.querySelector('.fa-spinner');
        
        // Mostrar spinner y ocultar texto
        btnText.classList.add('d-none');
        spinner.classList.remove('d-none');
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        fetch('Registro_variedades.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(text || 'Error en la respuesta del servidor');
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data) throw new Error('Respuesta vacía del servidor');
            
            if (data.success) {
                mostrarNotificacion('Éxito', data.message);
                
                // Recargar después de 1.5 segundos
                setTimeout(() => {
                    if (data.data?.id_variedad) {
                        window.location.href = `Registro_variedades.php?especie=${document.getElementById('selectEspecie').value}`;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                throw new Error(data.message || 'Error desconocido');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error', error.message || 'Error al procesar la solicitud', 'danger');
        })
        .finally(() => {
            // Restaurar botón
            btnText.classList.remove('d-none');
            spinner.classList.add('d-none');
            submitBtn.disabled = false;
        });
    });

    // Configuración inicial al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($variedadEditar) || $especieSeleccionada): ?>
            const especieId = document.getElementById('selectEspecie').value;
            if (especieId) {
                cargarColores(especieId);
            }
        <?php endif; ?>
    });
</script>