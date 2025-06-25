<?php

require_once __DIR__ . '/../../includes/config.php';

// Iniciar buffer de salida
ob_start();

$db = new Database();
$conexion = $db->conectar();
// Procesamiento de formularios (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEspecie = $_POST['especie'];
    $color = htmlspecialchars(trim($_POST['color'] ?? ''));
    $accion = $_POST['accion'] ?? 'crear';
    $idColor = $_POST['id_color'] ?? null;

    // Validación (excepto para eliminación)
    if ($accion !== 'eliminar' && (empty($idEspecie) || empty($color))) {
        echo json_encode(['success' => false, 'message' => 'Complete todos los campos obligatorios']);
        exit;
    }
    
    try {
        if ($accion === 'eliminar' && $idColor) {
            $sql = "DELETE FROM colores WHERE id_color = :id_color";
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':id_color', $idColor, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Color eliminado correctamente',
                    'especie' => $idEspecie
                ]);
                exit;
            }
        } else {
            // Verificar si el color ya existe
            $sqlCheck = "SELECT id_color FROM colores WHERE id_especie = :id_especie AND nombre_color = :color";
            if ($accion === 'editar' && $idColor) {
                $sqlCheck .= " AND id_color != :id_color_excluir";
            }
            
            $stmtCheck = $conexion->prepare($sqlCheck);
            $stmtCheck->bindParam(':id_especie', $idEspecie, PDO::PARAM_INT);
            $stmtCheck->bindParam(':color', $color);
            if ($accion === 'editar' && $idColor) {
                $stmtCheck->bindParam(':id_color_excluir', $idColor, PDO::PARAM_INT);
            }
            $stmtCheck->execute();
            
            if ($stmtCheck->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Este color ya existe para esta especie']);
                exit;
            }

            if ($accion === 'crear') {
                $sql = "INSERT INTO colores (id_especie, nombre_color) VALUES (:id_especie, :color)";
                $stmt = $conexion->prepare($sql);
                $stmt->bindParam(':id_especie', $idEspecie, PDO::PARAM_INT);
                $stmt->bindParam(':color', $color);
            } elseif ($accion === 'editar') {
                $sql = "UPDATE colores SET nombre_color = :color WHERE id_color = :id_color";
                $stmt = $conexion->prepare($sql);
                $stmt->bindParam(':color', $color);
                $stmt->bindParam(':id_color', $idColor, PDO::PARAM_INT);
            }
            
            if ($stmt->execute()) {
                $response = [
                    'success' => true,
                    'message' => $accion === 'crear' ? 'Color agregado correctamente' : 'Color actualizado',
                    'especie' => $idEspecie
                ];
                echo json_encode($response);
            }
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: '.$e->getMessage()]);
    }
    exit;
}

if (isset($_GET['especie'])) {
    $idEspecie = intval($_GET['especie']);
    $sql = "SELECT id_color, nombre_color FROM colores WHERE id_especie = :id_especie ORDER BY nombre_color";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id_especie', $idEspecie, PDO::PARAM_INT);
    $stmt->execute();
    $colores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($colores);
    exit;
}

// Configuración de encabezado
$titulo = "Gestión de Colores";
$encabezado = "Gestión de Colores";
$subtitulo = "Administra inventario de colores.";

// Incluir la cabecera (ruta relativa al archivo actual)
require('../../includes/header.php');
?>


<main class="container py-4">
    <div class="form-container">
        <h4><?= isset($colorEditar) ? 'Editar Color' : 'Agregar Nuevo Color' ?></h4>
        <form id="colorForm" method="POST">
            <input type="hidden" name="accion" value="<?= isset($colorEditar) ? 'editar' : 'crear' ?>">
            <input type="hidden" name="id_color" value="<?= $colorEditar['id_color'] ?? '' ?>">
            
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label class="form-label">Especie <span class="text-danger">*</span></label>
                    <select class="form-select" name="especie" id="selectEspecie" required>
                        <option value="">-- Seleccione --</option>
                        <?php
                        $sql = "SELECT id_especie, nombre FROM especies ORDER BY nombre";
                        $stmt = $conexion->query($sql);
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $selected = ($especieSeleccionada == $row['id_especie']) ? 'selected' : '';
                            echo "<option value='{$row['id_especie']}' $selected>{$row['nombre']}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Nombre del Color <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="color" 
                                value="<?= htmlspecialchars($colorEditar['nombre_color'] ?? '') ?>" required>
                        <button type="submit" class="btn btn-primary">
                            <?= isset($colorEditar) ? 'Actualizar' : 'Guardar' ?>
                        </button>
                        <?php if (isset($colorEditar)): ?>
                            <a href="Registro_colores.php?especie=<?= $especieSeleccionada ?>" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <h4>Colores Registrados</h4>
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Nombre del Color</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaColores">
                <?php
                if ($especieSeleccionada) {
                    try {
                        $sql = "SELECT id_color, nombre_color FROM colores 
                                WHERE id_especie = :id_especie 
                                ORDER BY nombre_color";
                        $stmt = $conexion->prepare($sql);
                        $stmt->bindParam(':id_especie', $especieSeleccionada, PDO::PARAM_INT);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() > 0) {
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr data-id='{$row['id_color']}'>
                                        <td>{$row['nombre_color']}</td>
                                        <td>
                                            <a href='Registro_colores.php?editar={$row['id_color']}&especie={$especieSeleccionada}' 
                                                class='btn btn-sm btn-warning'>Editar</a>
                                            <button onclick='eliminarColor({$row['id_color']})' 
                                                    class='btn btn-sm btn-danger'>Eliminar</button>
                                        </td>
                                        </tr>";
                            }
                        } else {
                            echo '<tr><td colspan="2" class="text-center py-3">No hay colores registrados</td></tr>';
                        }
                    } catch(PDOException $e) {
                        echo '<tr><td colspan="2" class="text-center text-danger py-3">Error al cargar colores</td></tr>';
                    }
                } else {
                    echo '<tr><td colspan="2" class="text-center py-3">Seleccione una especie</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Función para editar color
function editarColor(idColor, idEspecie) {
fetch(`Registro_colores.php?especie=${idEspecie}`)
    .then(response => {
        if (!response.ok) throw new Error('Error en la respuesta');
        return response.json();
    })
    .then(colores => {
        const colorEditar = colores.find(c => c.id_color == idColor);
        if (colorEditar) {
            const form = document.getElementById('colorForm');
            if (form) {
                // Cambiar a modo edición
                form.elements['accion'].value = 'editar';
                form.elements['id_color'].value = idColor;
                form.elements['color'].value = colorEditar.nombre_color;
                
                // Cambiar texto del botón
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Actualizar';
                
                // Agregar botón cancelar si no existe
                if (!form.querySelector('.btn-cancelar')) {
                    const cancelBtn = document.createElement('a');
                    cancelBtn.className = 'btn btn-secondary btn-cancelar';
                    cancelBtn.href = '#';
                    cancelBtn.textContent = 'Cancelar';
                    cancelBtn.onclick = function(e) {
                        e.preventDefault();
                        resetForm();
                    };
                    form.querySelector('.input-group').appendChild(cancelBtn);
                }
                
                // Desplazarse al formulario
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cargar los datos del color');
    });
}

// Función para resetear el formulario
function resetForm() {
const form = document.getElementById('colorForm');
if (form) {
    form.elements['accion'].value = 'crear';
    form.elements['id_color'].value = '';
    form.elements['color'].value = '';
    
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.textContent = 'Guardar';
    
    const cancelBtn = form.querySelector('.btn-cancelar');
    if (cancelBtn) cancelBtn.remove();
    
    // Limpiar parámetros de la URL
    window.history.pushState({}, '', 'Registro_colores.php');
}
}

// Función para eliminar color
function eliminarColor(idColor) {
const especie = document.getElementById('selectEspecie').value;
if (!especie) {
    alert('Por favor seleccione una especie primero');
    return;
}

if (!confirm('¿Está seguro de eliminar este color permanentemente?')) {
    return;
}

const formData = new FormData();
formData.append('accion', 'eliminar');
formData.append('id_color', idColor);
formData.append('especie', especie);

fetch('Registro_colores.php', {
    method: 'POST',
    body: formData
})
.then(response => {
    if (!response.ok) throw new Error('Error en la respuesta');
    return response.json();
})
.then(data => {
    if (data.success) {
        alert('Color eliminado correctamente');
        document.getElementById('selectEspecie').dispatchEvent(new Event('change'));
    } else {
        alert(data.message || 'Error al eliminar el color');
    }
})
.catch(error => {
    console.error('Error:', error);
    alert('Error al conectar con el servidor');
});
}

// Carga inicial y configuración de eventos
document.addEventListener('DOMContentLoaded', function() {
// Configurar el formulario
const colorForm = document.getElementById('colorForm');
if (colorForm) {
    colorForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const especie = this.elements['especie'].value;
        const color = this.elements['color'].value.trim();
        
        if (!especie) {
            alert('Por favor seleccione una especie');
            return;
        }
        
        if (!color) {
            alert('Por favor ingrese un color');
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('Registro_colores.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(this.elements['accion'].value === 'crear' ? 
                        'Color agregado correctamente' : 'Color actualizado');
                
                // Resetear el formulario
                resetForm();
                
                // Actualizar la tabla
                document.getElementById('selectEspecie').dispatchEvent(new Event('change'));
            } else {
                alert(data.message || 'Error en la operación');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    });
}

// Configurar el selector de especie
const selectEspecie = document.getElementById('selectEspecie');
if (selectEspecie) {
    selectEspecie.addEventListener('change', function() {
        const especieId = this.value;
        const tbody = document.getElementById('tablaColores');
        
        if (!especieId) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center py-3">Seleccione una especie</td></tr>';
            return;
        }

        fetch(`Registro_colores.php?especie=${especieId}`)
            .then(response => {
                if (!response.ok) throw new Error('Error al cargar colores');
                return response.json();
            })
            .then(colores => {
                if (!colores || colores.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center py-3">No hay colores registrados</td></tr>';
                    return;
                }

                tbody.innerHTML = colores.map(c => `
                    <tr data-id="${c.id_color}">
                        <td>${c.nombre_color}</td>
                        <td>
                            <button onclick="editarColor(${c.id_color}, ${especieId})" 
                                    class="btn btn-sm btn-warning">Editar</button>
                            <button onclick="eliminarColor(${c.id_color})" 
                                    class="btn btn-sm btn-danger">Eliminar</button>
                        </td>
                    </tr>`).join('');
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Error al cargar colores</td></tr>';
            });
    });

    // Cargar colores si ya hay una especie seleccionada
    if (selectEspecie.value) {
        selectEspecie.dispatchEvent(new Event('change'));
    }
}
});
</script>