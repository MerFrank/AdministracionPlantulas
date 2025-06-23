<?php
require_once 'C:/xampp/htdocs/Plantulas/includes/config.php';
$db = new Database();
$conexion = $db->conectar();

// Procesar el envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar creación o actualización
    if (isset($_POST['accion'])) {
        $id = $_POST['id'] ?? null;
        $nombre = $_POST['nombreEspecie'] ?? '';
        $descripcion = $_POST['descripcionEspecie'] ?? '';

        if (empty($nombre)) {
            echo "<script>alert('Por favor ingrese el nombre de la especie');</script>";
            exit;
        }

        try {
            if ($_POST['accion'] === 'crear') {
                $sql = "INSERT INTO Especies (nombre, descripcion, fecha_registro) VALUES (:nombre, :descripcion, CURDATE())";
            } elseif ($_POST['accion'] === 'editar' && $id) {
                $sql = "UPDATE Especies SET nombre = :nombre, descripcion = :descripcion WHERE id_especie = :id";
            }
            
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion, $descripcion ? PDO::PARAM_STR : PDO::PARAM_NULL);
            
            if ($_POST['accion'] === 'editar') {
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            }
            
            if ($stmt->execute()) {
                $mensaje = $_POST['accion'] === 'crear' ? 'Especie registrada exitosamente' : 'Especie actualizada exitosamente';
                echo "<script>alert('$mensaje'); window.location.href='Registro_especie.php';</script>";
            }
        } catch(PDOException $e) {
            $mensaje = $e->getCode() == 23000 ? 'Esta especie ya está registrada' : 'Error: '.addslashes($e->getMessage());
            echo "<script>alert('$mensaje');</script>";
        }
    }
}

// Procesar eliminación
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    try {
        $sql = "DELETE FROM Especies WHERE id_especie = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo "<script>alert('Especie eliminada exitosamente'); window.location.href='Registro_especie.php';</script>";
        }
    } catch(PDOException $e) {
        echo "<script>alert('Error al eliminar: ".addslashes($e->getMessage())."');</script>";
    }
}

// Obtener datos para edición
$especieEditar = null;
if (isset($_GET['editar'])) {
    try {
        $sql = "SELECT * FROM Especies WHERE id_especie = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id', $_GET['editar'], PDO::PARAM_INT);
        $stmt->execute();
        $especieEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        echo "<script>alert('Error al cargar datos: ".addslashes($e->getMessage())."');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro de Especies</title>
    <link rel="stylesheet" href="/Plantulas/assets/css/style.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .acciones-btn {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php include 'C:/xampp/htdocs/Plantulas/includes/header.php'; ?>

    <main>
        <div class="container mt-5">
            <h2>Registro de Especies</h2>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="<?= $especieEditar ? 'editar' : 'crear' ?>">
                <input type="hidden" name="id" value="<?= $especieEditar['id_especie'] ?? '' ?>">
                
                <div class="mb-3">
                    <label for="nombreEspecie" class="form-label">Nombre de la Especie <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nombreEspecie" name="nombreEspecie" 
                           value="<?= htmlspecialchars($especieEditar['nombre'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="descripcionEspecie" class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcionEspecie" name="descripcionEspecie" rows="3"><?= 
                        htmlspecialchars($especieEditar['descripcion'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <?= $especieEditar ? 'Actualizar Especie' : 'Registrar Especie' ?>
                </button>
                <?php if ($especieEditar): ?>
                    <a href="Registro_especie.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </form>

            <div class="mt-5">
                <h3>Especies Registradas</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Fecha de Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $sql = "SELECT * FROM Especies ORDER BY nombre";
                                $stmt = $conexion->query($sql);
                                
                                if ($stmt->rowCount() > 0) {
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr>
                                                <td>{$row['id_especie']}</td>
                                                <td>".htmlspecialchars($row['nombre'])."</td>
                                                <td>".($row['descripcion'] ? htmlspecialchars($row['descripcion']) : '-')."</td>
                                                <td>{$row['fecha_registro']}</td>
                                                <td class='acciones-btn'>
                                                    <a href='Registro_especie.php?editar={$row['id_especie']}' class='btn btn-sm btn-warning'>Editar</a>
                                                    <a href='Registro_especie.php?eliminar={$row['id_especie']}' 
                                                       class='btn btn-sm btn-danger' 
                                                       onclick='return confirm(\"¿Estás seguro de eliminar esta especie?\")'>Eliminar</a>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center'>No hay especies registradas</td></tr>";
                                }
                            } catch(PDOException $e) {
                                echo "<tr><td colspan='5'>Error al cargar especies: ".htmlspecialchars($e->getMessage())."</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php include 'C:/xampp/htdocs/Plantulas/includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para confirmar eliminación
        function confirmarEliminacion(e) {
            if (!confirm('¿Estás seguro de eliminar esta especie?')) {
                e.preventDefault();
            }
        }
    </script>
</body>
</html>