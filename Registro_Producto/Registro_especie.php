<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = htmlspecialchars(trim($_POST['nombreEspecie']));
    $descripcion = htmlspecialchars(trim($_POST['descripcionEspecie']));
    
    if (empty($nombre)) {
        echo "<script>alert('Por favor ingrese el nombre de la especie');</script>";
        exit;
    }
    
    try {
        $sql = "INSERT INTO Especies (nombre, descripcion, fecha_registro) VALUES (:nombre, :descripcion, CURDATE())";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion, $descripcion ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        if ($stmt->execute()) {
            echo "<script>alert('Especie registrada exitosamente'); window.location.href='Registro_especie.php';</script>";
        } else {
            echo "<script>alert('Error al registrar la especie');</script>";
        }
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "<script>alert('Esta especie ya está registrada');</script>";
        } else {
            echo "<script>alert('Error: ".addslashes($e->getMessage())."');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro de Especies</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
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
            <h2>Registro de especies</h2>
            <p>Ingresa una nueva especie en el sistema</p>
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
            <h2>Registrar Nueva Especie</h2>
            <div class="container mt-5">
                <form id="especieForm" method="POST" action="">
                    <h5>Datos de la Especie</h5>
                    <div class="mb-3">
                        <label for="nombreEspecie" class="form-label">Nombre de la especie <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombreEspecie" name="nombreEspecie" required placeholder="Ej. Rosa, Tulipán, Orquídea">
                    </div>
                    <div class="mb-3">
                        <label for="descripcionEspecie" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcionEspecie" name="descripcionEspecie" rows="3" placeholder="Características principales de la especie"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">Guardar Especie</button>
                </form>
            </div>

            <div class="container mt-5">
                <h5>Especies Registradas</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Fecha Registro</th>
                            </tr>
                        </thead>
                        <tbody id="listaEspecies">
                            <?php
                            include __DIR__ . '/../db/config.php';
                            $db = new Database();
                            $conexion = $db->conectar();
                            
                            try {
                                $sql = "SELECT * FROM Especies ORDER BY fecha_registro DESC";
                                $stmt = $conexion->query($sql);
                                
                                if ($stmt->rowCount() > 0) {
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr>
                                                <td>{$row['id_especie']}</td>
                                                <td>{$row['nombre']}</td>
                                                <td>".($row['descripcion'] ?: '-')."</td>
                                                <td>{$row['fecha_registro']}</td>
                                              </tr>";
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-center">No hay especies registradas</td></tr>';
                                }
                            } catch(PDOException $e) {
                                echo '<tr><td colspan="4" class="text-center">Error al cargar especies: '.$e->getMessage().'</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
