<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include __DIR__ . '/../db/config.php';
    $db = new Database();
    $conexion = $db->conectar();
    
    $idEspecie = $_POST['especie'];
    $color = htmlspecialchars(trim($_POST['color']));
    
    if (empty($idEspecie) || empty($color)) {
        echo json_encode(['success' => false, 'message' => 'Complete todos los campos obligatorios']);
        exit;
    }
    
    try {
        // Verificar si el color ya existe para esta especie
        $sqlCheck = "SELECT id_color FROM Colores WHERE id_especie = :id_especie AND nombre_color = :color";
        $stmtCheck = $conexion->prepare($sqlCheck);
        $stmtCheck->bindParam(':id_especie', $idEspecie);
        $stmtCheck->bindParam(':color', $color);
        $stmtCheck->execute();
        
        if ($stmtCheck->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Este color ya está asignado a la especie seleccionada']);
        } else {
            $sqlInsert = "INSERT INTO Colores (id_especie, nombre_color) VALUES (:id_especie, :color)";
            $stmtInsert = $conexion->prepare($sqlInsert);
            $stmtInsert->bindParam(':id_especie', $idEspecie);
            $stmtInsert->bindParam(':color', $color);
            
            if ($stmtInsert->execute()) {
                $nombreEspecie = $conexion->query("SELECT nombre FROM Especies WHERE id_especie = $idEspecie")->fetchColumn();
                echo json_encode([
                    'success' => true,
                    'message' => "Color \"$color\" agregado correctamente a \"$nombreEspecie\"",
                    'especie' => $idEspecie
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al agregar el color']);
            }
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: '.$e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Asignar Colores a Especies</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <style>
        .color-badge {
            display: inline-block;
            padding: 5px 10px;
            margin: 5px;
            background-color: #f0f0f0;
            border-radius: 15px;
            border: 1px solid #ddd;
        }
        .empty-message {
            color: #6c757d;
            font-style: italic;
        }
    </style>
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
            <h2>Asignar Colores a Especies</h2>
            <p>Gestiona los colores disponibles para cada especie</p>
          </div>
        </div>

        <div class="barra-navegacion">
          <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
              <div class="Opciones-barra">
                <button
                  onclick="window.location.href='dashboard_registroProducto.php'"
                >
                  Regresar inicio
                </button>
              </div>
            </div>
          </nav>
        </div>
      </header>

      <main>
            <div class="container mt-5">
                <form id="colorForm" method="POST" action="">
                    <h5>Asignar Nuevo Color</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="selectEspecie" class="form-label">Seleccionar Especie <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectEspecie" name="especie" required>
                                <option value="">-- Seleccione una especie --</option>
                                <?php
                                include __DIR__ . '/../db/config.php';
                                $db = new Database();
                                $conexion = $db->conectar();
                                
                                try {
                                    $sql = "SELECT id_especie, nombre FROM Especies ORDER BY nombre";
                                    $stmt = $conexion->query($sql);
                                    
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $selected = (isset($_GET['especie']) && $_GET['especie'] == $row['id_especie']) ? 'selected' : '';
                                        echo "<option value='{$row['id_especie']}' $selected>{$row['nombre']}</option>";
                                    }
                                } catch(PDOException $e) {
                                    echo "<option value='' disabled>Error al cargar especies</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="nuevoColor" class="form-label">Nombre del Color <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="nuevoColor" name="color" required>
                                <button type="submit" class="btn btn-primary">Agregar</button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="mt-4">
                    <h5>Colores Asignados</h5>
                    <div class="color-container" id="coloresAsignados">
                        <?php
                        $especieSeleccionada = $_GET['especie'] ?? null;
                        
                        if ($especieSeleccionada) {
                            try {
                                $sql = "SELECT c.nombre_color 
                                        FROM Colores c 
                                        WHERE c.id_especie = :id_especie 
                                        ORDER BY c.nombre_color";
                                $stmt = $conexion->prepare($sql);
                                $stmt->bindParam(':id_especie', $especieSeleccionada, PDO::PARAM_INT);
                                $stmt->execute();
                                
                                if ($stmt->rowCount() > 0) {
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<span class='color-badge'>{$row['nombre_color']}</span>";
                                    }
                                } else {
                                    echo '<div class="empty-message">Esta especie no tiene colores asignados</div>';
                                }
                            } catch(PDOException $e) {
                                echo '<div class="empty-message">Error al cargar colores</div>';
                            }
                        } else {
                            echo '<div class="empty-message">Seleccione una especie para ver sus colores</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejar el cambio de especie con AJAX
        document.getElementById('selectEspecie').addEventListener('change', function() {
            const especieId = this.value;
            cargarColores(especieId);
        });

        // Función para cargar colores via AJAX
        function cargarColores(especieId) {
            const coloresContainer = document.getElementById('coloresAsignados');
            
            if (!especieId) {
                coloresContainer.innerHTML = '<div class="empty-message">Seleccione una especie para ver sus colores</div>';
                return;
            }
            
            fetch(`obtener_colores.php?especie=${especieId}`)
                .then(response => response.text())
                .then(data => {
                    coloresContainer.innerHTML = data;
                })
                .catch(error => {
                    coloresContainer.innerHTML = '<div class="empty-message">Error al cargar los colores</div>';
                    console.error('Error:', error);
                });
        }

        // Manejar el envío del formulario con AJAX
        document.getElementById('colorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const especieId = formData.get('especie');
            const color = formData.get('color');
            
            fetch('Registro_colores.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    document.getElementById('nuevoColor').value = '';
                    cargarColores(data.especie || especieId);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al procesar la solicitud');
            });
        });

        // Cargar colores al inicio si hay una especie seleccionada
        document.addEventListener('DOMContentLoaded', function() {
            const especieSelect = document.getElementById('selectEspecie');
            if (especieSelect.value) {
                cargarColores(especieSelect.value);
            }
        });
    </script>
</body>
</html>