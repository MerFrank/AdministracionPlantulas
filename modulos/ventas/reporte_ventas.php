<?php
include __DIR__ . '/../db/config.php';

$db = new Database();
$con = $db->conectar();

// Consulta seguimientos ventas y sus detalles
$sql = $con->prepare("
  SELECT
    sv.id_notaPedido,
    sv.fecha,
    c.nombre_Cliente AS nombre_cliente,
    sv.estado_pedido,
    v.nombre_variedad AS nombre_variedad,
    dsv.estado_pago
  FROM
    SeguimientoVentas sv
  LEFT JOIN Clientes c ON
    sv.id_cliente = c.id_cliente
  LEFT JOIN DetallesSeguimientoVentas dsv ON
    sv.id_notaPedido = dsv.id_detalleSeguimiento
  LEFT JOIN Variedades v ON
    dsv.id_variedad = v.id_variedad
  ORDER BY
    sv.fecha DESC
");
$sql->execute();
$resultados = $sql->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por pedidos
$ventasAgrupadas = [];
foreach ($resultados as $venta) {
    $idPedido = $venta['id_notaPedido'];
    if (!isset($ventasAgrupadas[$idPedido])) {
        $ventasAgrupadas[$idPedido] = [
            'fecha' => $venta['fecha'],
            'cliente' => $venta['nombre_cliente'],
            'estado_pedido' => $venta['estado_pedido'],
            'productos' => [],  
            'estados_pago' => []
        ];
    }
    if (!empty($venta['nombre_variedad'])) {
        $ventasAgrupadas[$idPedido]['productos'][] = $venta['nombre_variedad'];
        $ventasAgrupadas[$idPedido]['estados_pago'][] = $venta['estado_pago'];
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reporte de Ventas</title>
  <link rel="stylesheet" href="/css/style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="/css/logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
        </a>
        <div>
          <h2>Reporte de Ventas</h2>
          <p>Consulta el estado de las ventas </p>
        </div>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_clientesVentas.php'">
                Regresar inicio
              </button>
            </div>
          </div>
        </nav>
      </div>

    <form id="filtroVentas" class="row g-3">
      <div class="col-md-3">
        <label for="fechaInicio" class="form-label">Fecha inicio</label>
        <input type="date" class="form-control" id="fechaInicio" />
      </div>
      <div class="col-md-3">
        <label for="fechaFin" class="form-label">Fecha fin</label>
        <input type="date" class="form-control" id="fechaFin" />
      </div>
      <div class="col-md-3">
        <label for="cliente" class="form-label">Cliente</label>
        <input type="text" class="form-control" id="cliente" placeholder="Nombre o alias" />
      </div>
      <div class="col-md-3">
        <label for="estado" class="form-label">Estado de venta</label>
        <select id="estado" class="form-select">
          <option value="">Todos</option>
          <option value="pendiente">Pendiente</option>
          <option value="en preparacion">En preparación</option>
          <option value="entregado">Entregado</option>
          <option value="sin trabajar">Sin trabajar</option>
        </select>
      </div>
    </form>

    <div class="text-end mt-3">
      <button class="btn btn-success" onclick="exportarExcel()">Exportar a Excel</button>
    </div>

    <hr />

    <h5>Resultados</h5>
    <div class="table-responsive">
      <table class="table table-bordered mt-3" id="tablaVentas">
        <thead class="table-light">
          <tr>
            <th>ID Pedido</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Estado Pedido</th>
            <th>Productos</th> 
            <th>Estado de Pago</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ventasAgrupadas as $idPedido => $venta): ?>
            <tr>
              <td><?php echo htmlspecialchars($idPedido); ?></td>
              <td><?php echo date('d/m/Y', strtotime($venta['fecha'])); ?></td>
              <td><?php echo htmlspecialchars($venta['cliente'] ?? 'N/A'); ?></td>
              <td>
                <span class="badge 
                  <?php 
                    switch(strtolower($venta['estado_pedido'])) {
                      case 'pendiente': echo 'bg-warning'; break;
                      case 'en preparacion': echo 'bg-info'; break;
                      case 'entregado': echo 'bg-success'; break;
                      default: echo 'bg-secondary';
                    }
                  ?>">
                  <?php echo htmlspecialchars($venta['estado_pedido']); ?>
                </span>
              </td>
              <td>
                <?php if (!empty($venta['productos'])): ?>
                  <ul class="mb-0">
                    <?php foreach (array_unique($venta['productos']) as $variedad): ?>
                      <li><?php echo htmlspecialchars($variedad); ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  Sin variedades registradas
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($venta['estados_pago'])): ?>
                  <?php foreach (array_unique($venta['estados_pago']) as $estadoPago): ?>
                    <span class="badge 
                      <?php 
                        switch(strtolower($estadoPago)) {
                          case 'pagado': echo 'bg-success'; break;
                          case 'pendiente': echo 'bg-warning'; break;
                          case 'cancelado': echo 'bg-danger'; break;
                          default: echo 'bg-secondary';
                        }
                      ?>">
                      <?php echo htmlspecialchars($estadoPago); ?>
                    </span><br>
                  <?php endforeach; ?>
                <?php else: ?>
                  Sin información
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <script></script>
</body>
</html>