<?php
require_once __DIR__ . '/../../includes/config.php';

// 1. Verificar y establecer conexión
if (!class_exists('Database')) {
    die("Error: Clase Database no encontrada");
}

try {
    $db = new Database();
    $pdo = $db->conectar();
    
    if (!$pdo) {
        throw new Exception("No se pudo establecer conexión a la base de datos");
    }
} catch (Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}

// 2. Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// 3. Obtener y validar ID
$id_empleado = filter_input(INPUT_GET, 'id_empleado', FILTER_VALIDATE_INT);
if (!$id_empleado || $id_empleado <= 0) {
    $_SESSION['error'] = "ID de empleado inválido";
    header('Location: lista_empleados.php');
    exit();
}

// 4. Procesar eliminación lógica
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        
        // Consulta de actualización para borrado lógico
        $sql = "UPDATE empleados SET 
                activo = 0,
                fecha_actualizacion = NOW()
                WHERE id_empleado = :id";
                
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id_empleado, PDO::PARAM_INT);
        
        if (!$stmt->execute()) {
            throw new Exception("No se pudo ejecutar la actualización");
        }
        
        // Verificar si se actualizó algún registro
        if ($stmt->rowCount() === 0) {
            throw new Exception("No se encontró el empleado con ID $id_empleado o ya estaba inactivo");
        }
        
        $pdo->commit();
        $_SESSION['mensaje'] = "Empleado marcado como inactivo correctamente";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error al desactivar empleado: " . $e->getMessage();
    }
    
    header('Location: lista_empleados.php');
    exit();
}

// 5. Obtener datos para confirmación
try {
    $sql = "SELECT nombre, apellido_paterno, apellido_materno 
            FROM empleados 
            WHERE id_empleado = :id AND activo = 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id_empleado, PDO::PARAM_INT);
    $stmt->execute();
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado) {
        $_SESSION['error'] = "Empleado no encontrado o ya está inactivo";
        header('Location: lista_empleados.php');
        exit();
    }
} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

// 6. Mostrar confirmación
$titulo = "Desactivar Empleado";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="card shadow-lg">
        <div class="card-header bg-warning text-dark">
            <h2><i class="bi bi-person-x"></i> Desactivar Empleado</h2>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="alert alert-warning">
                <h5>¿Está seguro que desea desactivar este empleado?</h5>
                <p class="mb-1"><strong>Nombre:</strong> 
                    <?= htmlspecialchars($empleado['nombre']) ?>
                    <?= htmlspecialchars($empleado['apellido_paterno']) ?>
                    <?= htmlspecialchars($empleado['apellido_materno'] ?? '') ?>
                </p>
                <p class="mb-0"><small>Esta acción marcará al empleado como inactivo en el sistema.</small></p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                
                <div class="d-flex justify-content-end">
                    <button type="submit" name="confirmar" class="btn btn-warning me-2">
                        <i class="bi bi-person-x"></i> Confirmar Desactivación
                    </button>
                    <a href="lista_empleados.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>