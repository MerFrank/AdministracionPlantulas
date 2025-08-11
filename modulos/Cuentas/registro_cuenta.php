<?php
// Incluye el archivo de configuración que contiene constantes y configuraciones de la base de datos
require_once __DIR__ . '/../../includes/config.php';

// Intenta establecer conexión con la base de datos
try {
    $db = new Database(); // Crea una nueva instancia de la clase Database
    $con = $db->conectar(); // Obtiene la conexión PDO
} catch (PDOException $e) {
    // Si hay error en la conexión, muestra mensaje y termina la ejecución
    die("Error de conexión: " . $e->getMessage());
}

/* 
 * Configuración para el header:
 * - $titulo: Título de la página que aparece en la pestaña del navegador
 * - $encabezado: Título principal que aparece en el contenido
 * - $ruta: Destino del botón "Volver" en el header
 * - $texto_boton: Texto que aparece en el botón "Volver"
 */
$titulo = 'Registrar Cuenta Bancaria';
$encabezado = 'Registro de Nueva Cuenta';
$ruta = "dashboard_cuentas.php"; // Ruta a donde redirige el botón Volver
$texto_boton = "Volver"; // Texto del botón Volver

// Genera un token CSRF para protección contra ataques de falsificación de solicitudes
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Token seguro de 32 bytes
}

$error = ''; // Variable para almacenar mensajes de error

// Procesamiento del formulario cuando se envía (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica que el token CSRF coincida
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido'); // Detiene la ejecución si el token no es válido
    }

    try {
        // Prepara los datos del formulario:
        // - Aplica htmlspecialchars y trim a los campos de texto
        // - Convierte el saldo a float
        $datos = [
            'nombre' => htmlspecialchars(trim($_POST['nombre'])),
            'banco' => htmlspecialchars(trim($_POST['banco'])),
            'tipo_cuenta' => $_POST['tipo_cuenta'],
            'numero' => htmlspecialchars(trim($_POST['numero'])),
            'clabe' => !empty($_POST['clabe']) ? htmlspecialchars(trim($_POST['clabe'])) : null,
            'saldo_inicial' => (float)$_POST['saldo_inicial']
        ];

        // Validación de campos requeridos
        if (empty($datos['nombre'])) throw new Exception("Nombre requerido");
        if (empty($datos['banco'])) throw new Exception("Banco requerido");
        if (empty($datos['numero'])) throw new Exception("Número de cuenta requerido");

        // Consulta SQL para insertar la nueva cuenta bancaria
        $sql = "INSERT INTO cuentas_bancarias (nombre, banco, tipo_cuenta, numero, clabe, saldo_inicial, saldo_actual) 
                VALUES (:nombre, :banco, :tipo_cuenta, :numero, :clabe, :saldo_inicial, :saldo_inicial)";
        
        $stmt = $con->prepare($sql);
        if ($stmt->execute($datos)) {
            // Si la inserción es exitosa:
            $_SESSION['success_message'] = 'Cuenta registrada correctamente';
            header('Location: lista_cuentas.php'); // Redirige al listado
            exit; // Termina la ejecución
        } else {
            throw new Exception("Error al registrar cuenta");
        }
    } catch (Exception $e) {
        $error = $e->getMessage(); // Almacena el mensaje de error
    }
}

// Incluye el header de la página (contiene HTML inicial, estilos, etc.)
require __DIR__ . '/../../includes/header.php';
?>

<!-- Contenido principal de la página -->
<main class="container mt-4">
    <!-- Tarjeta contenedora con sombra -->
    <div class="card shadow">
        <!-- Encabezado de la tarjeta con fondo primario (verde) -->
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-bank"></i> Registrar Cuenta Bancaria</h2>
        </div>
        
        <!-- Cuerpo de la tarjeta -->
        <div class="card-body">
            <!-- Muestra mensaje de error si existe -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Formulario de registro -->
            <form method="post">
                <!-- Campo oculto para el token CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <!-- Fila con dos columnas para organizar los campos -->
                <div class="row g-3">
                    <!-- Primera columna -->
                    <div class="col-md-6">
                        <!-- Campo: Nombre de la cuenta -->
                        <div class="mb-3">
                            <label class="form-label">Nombre de la Cuenta</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        
                        <!-- Campo: Banco -->
                        <div class="mb-3">
                            <label class="form-label">Banco</label>
                            <input type="text" class="form-control" name="banco" required>
                        </div>
                        
                        <!-- Campo: Tipo de cuenta (select) -->
                        <div class="mb-3">
                            <label class="form-label">Tipo de Cuenta</label>
                            <select class="form-select" name="tipo_cuenta" required>
                                <option value="Cheques">Cuenta de Cheques</option>
                                <option value="Ahorros">Cuenta de Ahorros</option>
                                <option value="Inversión">Cuenta de Inversión</option>
                                <option value="Efectivo">Efectivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Segunda columna -->
                    <div class="col-md-6">
                        <!-- Campo: Número de cuenta -->
                        <div class="mb-3">
                            <label class="form-label">Número de Cuenta</label>
                            <input type="text" class="form-control" name="numero" required>
                        </div>
                        
                        <!-- Campo: CLABE Interbancaria (opcional) -->
                        <div class="mb-3">
                            <label class="form-label">CLABE Interbancaria</label>
                            <input type="text" class="form-control" name="clabe">
                        </div>
                        
                        <!-- Campo: Saldo inicial -->
                        <div class="mb-3">
                            <label class="form-label">Saldo Inicial</label>
                            <input type="number" class="form-control" name="saldo_inicial" step="0.01" min="0" value="0" required>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div class="d-flex justify-content-between mt-4">
                    <!-- Botón Cancelar - Redirige a lista_cuentas.php -->
                    <a href="lista_cuentas.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <!-- Botón para enviar el formulario -->
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Registrar Cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- Incluye el footer de la página -->
<?php require __DIR__ . '/../../includes/footer.php'; ?>

<!-- Script para validación del formulario -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Agrega validación al enviar el formulario
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault(); // Evita el envío si no es válido
            e.stopPropagation(); // Detiene la propagación del evento
        }
        this.classList.add('was-validated'); // Muestra mensajes de validación
    });
});
</script>