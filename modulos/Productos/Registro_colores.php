<?php
require_once 'C:/xampp/htdocs/Plantulas/includes/config.php';
$db = new Database();
$conexion = $db->conectar();

$colorEditar = null;
$especieSeleccionada = $_GET['especie'] ?? null;

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
            $sql = "DELETE FROM Colores WHERE id_color = :id_color";
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
            $sqlCheck = "SELECT id_color FROM Colores WHERE id_especie = :id_especie AND nombre_color = :color";
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
                $sql = "INSERT INTO Colores (id_especie, nombre_color) VALUES (:id_especie, :color)";
                $stmt = $conexion->prepare($sql);
                $stmt->bindParam(':id_especie', $idEspecie, PDO::PARAM_INT);
                $stmt->bindParam(':color', $color);
            } elseif ($accion === 'editar') {
                $sql = "UPDATE Colores SET nombre_color = :color WHERE id_color = :id_color";
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

if (isset($_GET['editar'])) {
    try {
        $sql = "SELECT * FROM Colores WHERE id_color = :id_color";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id_color', $_GET['editar'], PDO::PARAM_INT);
        $stmt->execute();
        $colorEditar = $stmt->fetch(PDO::FETCH_ASSOC);
        $especieSeleccionada = $colorEditar['id_especie'];
    } catch(PDOException $e) {
        echo "<script>alert('Error al cargar color: ".addslashes($e->getMessage())."');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Colores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'C:/xampp/htdocs/Plantulas/includes/header.php'; ?>

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
                            $sql = "SELECT id_especie, nombre FROM Especies ORDER BY nombre";
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
                            $sql = "SELECT id_color, nombre_color FROM Colores 
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

    <?php include 'C:/xampp/htdocs/Plantulas/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para eliminar color
        function eliminarColor(idColor) {
            if (!confirm('¿Está seguro de eliminar este color permanentemente?')) {
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('id_color', idColor);
            formData.append('especie', document.getElementById('selectEspecie').value);

            fetch('Registro_colores.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    // Eliminar la fila visualmente sin recargar
                    document.querySelector(`tr[data-id="${idColor}"]`)?.remove();
                    
                    // Si no quedan registros, mostrar mensaje
                    if (document.querySelectorAll('#tablaColores tr').length <= 1) {
                        document.getElementById('tablaColores').innerHTML = 
                            '<tr><td colspan="2" class="text-center py-3">No hay colores registrados</td></tr>';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar el color');
            });
        }

        // Manejar envío del formulario
        document.getElementById('colorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            fetch('Registro_colores.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.href = `Registro_colores.php?especie=${formData.get('especie')}`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        });

        // Cambiar especie
        document.getElementById('selectEspecie').addEventListener('change', function() {
            if (this.value) {
                window.location.href = `Registro_colores.php?especie=${this.value}`;
            }
        });
    </script>
</body>
</html>