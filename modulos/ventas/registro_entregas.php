<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once (__DIR__ . '/../../includes/config.php');
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = new Database();
    $pdo = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$mostrar_pedidos = $pdo->query('
    SELECT
        np.folio,
        c.nombre_Cliente,
        v.nombre_variedad,
        dnp.color,
        dnp.cantidad
    FROM
        notaspedidos np
    LEFT JOIN detallesnotapedido dnp ON
        np.id_notaPedido = dnp.id_notaPedido
    LEFT JOIN clientes c ON
        np.id_cliente = c.id_cliente
    LEFT JOIN variedades v ON
        dnp.id_variedad = v.id_variedad
    ORDER BY np.fechaPedido DESC
')->fetchAll();

$stmt = $pdo->prepare('
    SELECT
        np.folio,
        c.nombre_Cliente,
        v.nombre_variedad,
        dnp.color,
        dnp.cantidad,
        np.fecha_entrega,
        np.fecha_validez
    FROM
        notaspedidos np
    LEFT JOIN detallesnotapedido dnp ON
        np.id_notaPedido = dnp.id_notaPedido
    LEFT JOIN clientes c ON
        np.id_cliente = c.id_cliente
    LEFT JOIN variedades v ON
        dnp.id_variedad = v.id_variedad
    WHERE np.folio = :folio
');

$id_pedido = $_GET['id_pedido'] ?? null;

$stmt->execute([
    ':folio' => $id_pedido
]);

$pedidoEntrega = $stmt->fetch(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        // Validar Datos
        if (empty($_POST['folio'])) {
            throw new Exception("Recarge la pagian. Falta folio del pedido");
        }

        if (empty($_POST['fechaEntrega'])) {
            throw new Exception("Ingrese una fecha");
        }

        $folio = $_POST['folio'];
        $fechaEntrega = $_POST['fechaEntrega'];

        $actualizar_alumno = $pdo->prepare(
            "UPDATE notaspedidos SET
                fecha_entrega_Real = :fecha_entrega_Real
                WHERE folio = :folio "
        );

        $actualizar_alumno->execute([
            'folio' => $folio,
            'fecha_entrega_Real' => $fechaEntrega,
        ]);

        $pdo->commit();
        // header('Location: ver_alumno.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$titulo = 'Registro de entregas';
$encabezado = 'Registra entregas de pedidos';
$ruta = "vista_pedidos.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';

?>

<main class="container py-4">
    <div class=" mb-4">
        <form method='GET' id='selec_pedido'>
            <select name="id_pedido" id="id_pedido" onchange="this.form.submit()">
                <option value="">Seleccione un pedido</option>
                <?php foreach ($mostrar_pedidos as $pedido): ?>
                    <option value="<?= $pedido['folio'] ?>" <?= ($id_pedido == $pedido['folio']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pedido['folio'] . '     ' . $pedido['nombre_Cliente'] . '   -    ' .
                        $pedido['nombre_variedad'] . '     Cant:  '.$pedido['cantidad']  ) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </form>
    </div>

    <?php if ($pedidoEntrega): ?>
        <div class="card shadow border-0 rounded-0 py-4">
            <div>
                <form method="POST" id="fechaEntregaForm" class="form-doble-columna">
                    <div class="row g-3">
                        
                        <div class="col-md-4">
                                <label class="form-label">Folio </label>
                                <input type="text" name="folio" id="folio" value="<?= htmlspecialchars($pedidoEntrega['folio']) ?>" >
                        </div>

                        <div class="col-md-4">
                                <label class="form-label">Cliente </label>
                                <input type="text" name="nombre" id="nombre" value="<?= htmlspecialchars($pedidoEntrega['nombre_Cliente']) ?>" >
                        </div>

                        <div class="col-md-4">
                                <label class="form-label">Pedido  </label>
                                <input type="text" name="pedido" id="pedido" 
                                value="<?= htmlspecialchars($pedidoEntrega['nombre_variedad'] . ' - ' . $pedidoEntrega['color']) ?>">
                        </div>
    
                        <div class="col-md-4">
                            <label class="form-label">Fecha de Entrega Programada </span></label>
                            <input type="date" class="form-control" name="fechaEntregaProgramada" id="fechaEntregaProgramada" 
                                value="<?= htmlspecialchars($pedidoEntrega['fecha_entrega']) ?>" >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Fecha Validez Pedido </span></label>
                            <input type="date" class="form-control" name="fechaValidez" id="fechaValidez" 
                                value="<?= htmlspecialchars($pedidoEntrega['fecha_validez']) ?>" >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Fecha de Entrega<span class="text-danger">*</label>
                            <input type="date" class="form-control" name="fechaEntrega" id="fechaEntrega" 
                                required min="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="col-md-4 justify-content-center" >
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Registrar Entrega
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>