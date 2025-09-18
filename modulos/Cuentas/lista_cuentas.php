<?php
// Configuración para mostrar todos los errores (útil durante desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicia la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluye el archivo de configuración de la base de datos
require_once(__DIR__ . '/../../includes/config.php');

try {
    // Intenta conectar a la base de datos
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    // Si hay error en la conexión, muestra mensaje y termina ejecución
    die("Error de conexión: " . $e->getMessage());
}

// Consulta SQL para obtener cuentas bancarias activas con información adicional:
// - Número total de movimientos (egresos)
// - Fecha del último movimiento
$cuentas = $con->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM egresos WHERE id_cuenta = c.id_cuenta) as num_movimientos,
           (SELECT MAX(fecha) FROM egresos WHERE id_cuenta = c.id_cuenta) as ultimo_movimiento
    FROM cuentas_bancarias c 
    WHERE c.activo = 1 
    ORDER BY c.nombre
")->fetchAll();

// Variables para la plantilla del encabezado
$titulo = 'Cuentas Bancarias';
$encabezado = 'Listado de Cuentas Bancarias';
$ruta = "dashboard_cuentas.php";
$texto_boton = "Regresar";
// Incluye el archivo de cabecera (header) del sitio
require __DIR__ . '/../../includes/header.php';
?>

<!-- Contenido principal de la página -->
<main class="container mt-4">
    <!-- Tarjeta contenedora con sombra -->
    <div class="card shadow">
        <!-- Encabezado de la tarjeta con fondo primario (verde) -->
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <!-- Título con icono de banco -->
                <h2 class="mb-0"><i class="bi bi-bank"></i> Cuentas Bancarias</h2>
                <!-- Botón para agregar nueva cuenta -->
                <a href="registro_cuenta.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Nueva Cuenta
                </a>
            </div>
        </div>
        
        <!-- Cuerpo de la tarjeta -->
        <div class="card-body">
            <!-- Mensaje de éxito (si existe en sesión) -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <!-- Contenedor responsive para la tabla -->
            <div class="table-responsive">
                <!-- Tabla con estilo striped (filas alternadas) -->
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Banco</th>
                            <th>Tipo</th>
                            <th>Número</th>
                            <th>Saldo</th>
                            <th>Movimientos</th>
                            <th>Último Movimiento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Si no hay cuentas, muestra mensaje -->
                        <?php if (empty($cuentas)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No hay cuentas registradas</td>
                            </tr>
                        <?php else: ?>
                            <!-- Itera sobre cada cuenta bancaria -->
                            <?php foreach ($cuentas as $cuenta): ?>
                            <tr>
                                <!-- Muestra información de la cuenta con htmlspecialchars para seguridad XSS -->
                                <td><?= htmlspecialchars($cuenta['nombre']) ?></td>
                                <td><?= htmlspecialchars($cuenta['banco']) ?></td>
                                <td><?= htmlspecialchars($cuenta['tipo_cuenta']) ?></td>
                                <td><?= htmlspecialchars($cuenta['numero']) ?></td>
                                <!-- Formatea el saldo con 2 decimales -->
                                <td>$<?= number_format($cuenta['saldo_actual'], 2) ?></td>
                                <td><?= $cuenta['num_movimientos'] ?></td>
                                <!-- Formatea la fecha del último movimiento o muestra N/A si no hay -->
                                <td><?= $cuenta['ultimo_movimiento'] ? date('d/m/Y', strtotime($cuenta['ultimo_movimiento'])) : 'N/A' ?></td>
                                <td>
                                     <!-- Contenedor flex para los botones de acción -->
                                      <div class="d-flex gap-2">
                                     <!-- Grupo de botones -->
                                   
                                        <!-- Botón Editar (color amarillo/accent) -->
                                        <a href="editar_cuenta.php?id=<?= $cuenta['id_cuenta'] ?>" class="btn btn-sm text-white" 
                               style="background-color: var(--color-accent); border-color: var(--color-accent);">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                         <!-- Botón Eliminar (color rojo/danger) con confirmación JS -->
                                        <a href="eliminar_cuenta.php?id=<?= $cuenta['id_cuenta'] ?>" 
                                        class="btn btn-sm text-white" 
                                         style="background-color: var(--color-danger); border-color: var(--color-danger);"
                                           onclick="return confirm('¿Eliminar esta cuenta? Esta acción no se puede deshacer.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <!-- Botón Movimientos (color gris/secondary) -->
                                        <a href="movimientos_cuenta.php?id=<?= $cuenta['id_cuenta'] ?>" 
                                               class="btn btn-sm text-white" 
                                                style="background-color: var(--color-secondary); border-color: var(--color-secondary);">
                                              <i class="bi bi"></i> Movimientos
                                        </a>
                                    
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

<!-- Incluye el pie de página (footer) del sitio -->
<?php require __DIR__ . '/../../includes/footer.php'; ?>