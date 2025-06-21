<?php
include __DIR__ . '/../db/config.php';
$db = new Database();
$conexion = $db->conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEspecie = $_POST['especie'];
    $idColor = $_POST['color'];
    $nombreVariedad = htmlspecialchars(trim($_POST['nombreVariedad']));
    $codigoVariedad = htmlspecialchars(trim($_POST['codigoVariedad']));
    
    if (empty($idEspecie) || empty($idColor) || empty($nombreVariedad) || empty($codigoVariedad)) {
        echo json_encode(['success' => false, 'message' => 'Complete todos los campos obligatorios']);
        exit;
    }
    
    try {
        // Verificar si el código ya existe
        $sqlCheck = "SELECT id_variedad FROM Variedades WHERE codigo = :codigo";
        $stmtCheck = $conexion->prepare($sqlCheck);
        $stmtCheck->bindParam(':codigo', $codigoVariedad);
        $stmtCheck->execute();
        
        if ($stmtCheck->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Este código ya está registrado para otra variedad']);
        } else {
            $sqlInsert = "INSERT INTO Variedades (id_especie, id_color, nombre_variedad, codigo) 
                          VALUES (:id_especie, :id_color, :nombre, :codigo)";
            $stmtInsert = $conexion->prepare($sqlInsert);
            $stmtInsert->bindParam(':id_especie', $idEspecie);
            $stmtInsert->bindParam(':id_color', $idColor);
            $stmtInsert->bindParam(':nombre', $nombreVariedad);
            $stmtInsert->bindParam(':codigo', $codigoVariedad);
            
            if ($stmtInsert->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Variedad registrada con éxito',
                    'data' => [
                        'especie' => $conexion->query("SELECT nombre FROM Especies WHERE id_especie = $idEspecie")->fetchColumn(),
                        'color' => $conexion->query("SELECT nombre_color FROM Colores WHERE id_color = $idColor")->fetchColumn(),
                        'variedad' => $nombreVariedad,
                        'codigo' => $codigoVariedad
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al registrar la variedad']);
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
    <title>Agregar Variedades</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .empty-message {
            color: #6c757d;
            font-style: italic;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="contenedor-pagina">
        <header>
            <div class="encabezado">
                <a class="navbar-brand" href="#">
                    <img src="/css/logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
                </a>
                <div>
                    <h2>Agregar Variedades</h2>
                    <p>Registra nuevas variedades para especies y colores</p>
                </div>
            </div>

            <div class="barra-navegacion">
                <nav class="navbar bg-body-tertiary">
                    <div class="container-fluid">
                        <div class="Opciones-barra">
                            <button onclick="window.location.href='dashboard_registroProducto.php'">
                                Regresar inicio
                            </button>
                        </div>
                    </div>
                </nav>
            </div>
        </header>

        <main>
            <div class="container mt-5">
                <form id="variedadForm" method="POST" action="">
                    <h5>Datos de la Variedad</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="selectEspecieVariedad" class="form-label">Especie <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectEspecieVariedad" name="especie" required>
                                <option value="">-- Seleccione una especie --</option>
                                <?php
                                try {
                                    $sql = "SELECT id_especie, nombre FROM Especies ORDER BY nombre";
                                    $stmt = $conexion->query($sql);
                                    
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='{$row['id_especie']}'>{$row['nombre']}</option>";
                                    }
                                } catch(PDOException $e) {
                                    echo "<option value='' disabled>Error al cargar especies</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="selectColorVariedad" class="form-label">Color <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectColorVariedad" name="color" required disabled>
                                <option value="">-- Seleccione un color --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label for="nombreVariedad" class="form-label">Nombre de la Variedad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombreVariedad" name="nombreVariedad" required>
                        </div>
                        <div class="col-md-6">
                            <label for="codigoVariedad" class="form-label">Código Único <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="codigoVariedad" name="codigoVariedad" required>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-success">Guardar Variedad</button>
                    </div>
                </form>

                <div class="mt-5">
                    <h5>Variedades Registradas</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tablaVariedades">
                            <thead>
                                <tr>
                                    <th>Especie</th>
                                    <th>Color</th>
                                    <th>Variedad</th>
                                    <th>Código</th>
                                </tr>
                            </thead>
                            <tbody id="listaVariedades">
                                <?php
                                try {
                                    $sql = "SELECT e.nombre AS especie, c.nombre_color AS color, 
                                               v.nombre_variedad AS variedad, v.codigo
                                        FROM Variedades v
                                        JOIN Especies e ON v.id_especie = e.id_especie
                                        JOIN Colores c ON v.id_color = c.id_color
                                        ORDER BY e.nombre, c.nombre_color, v.nombre_variedad";
                                    $stmt = $conexion->query($sql);
                                    
                                    if ($stmt->rowCount() > 0) {
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<tr>
                                                    <td>{$row['especie']}</td>
                                                    <td>{$row['color']}</td>
                                                    <td>{$row['variedad']}</td>
                                                    <td>{$row['codigo']}</td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo '<tr><td colspan="4" class="empty-message">No hay variedades registradas</td></tr>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<tr><td colspan="4" class="empty-message">Error al cargar variedades: '.$e->getMessage().'</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
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
        // Manejar el cambio de especie para cargar colores
        document.getElementById('selectEspecieVariedad').addEventListener('change', function() {
            const especieId = this.value;
            const colorSelect = document.getElementById('selectColorVariedad');
            
            colorSelect.innerHTML = '<option value="">-- Seleccione un color --</option>';
            colorSelect.disabled = !especieId;
            
            if (especieId) {
                fetch(`get_colores.php?especie=${especieId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error al cargar colores');
                        }
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
                        } else {
                            colorSelect.innerHTML = '<option value="">-- No hay colores para esta especie --</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        colorSelect.innerHTML = '<option value="">-- Error al cargar colores --</option>';
                    });
            }
        });

        // Manejar el envío del formulario con AJAX
        document.getElementById('variedadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
            
            fetch('Registro_variedades.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Agregar la nueva variedad a la tabla
                    const tbody = document.getElementById('listaVariedades');
                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `
                        <td>${data.data.especie}</td>
                        <td>${data.data.color}</td>
                        <td>${data.data.variedad}</td>
                        <td>${data.data.codigo}</td>
                    `;
                    tbody.insertBefore(newRow, tbody.firstChild);
                    
                    // Limpiar el formulario
                    this.reset();
                    document.getElementById('selectColorVariedad').disabled = true;
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al procesar la solicitud');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Guardar Variedad';
            });
        });
    </script>
</body>
</html>