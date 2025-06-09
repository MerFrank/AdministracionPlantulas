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
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Productos</th>
            <th>Estado de Pago</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </main>

  <script>
    const ventas = [
      { fecha: "2025-06-06", cliente: "Juan Pérez", estado: "pendiente", productos: ["Fertilizante", "Maceta"], total: 500, pagado: 300 },
      { fecha: "2025-06-07", cliente: "Lucía Torres", estado: "entregado", productos: ["Sustrato", "Semillas"], total: 200, pagado: 200 },
      { fecha: "2025-06-07", cliente: "Mario Gómez", estado: "en preparacion", productos: ["Maceta 3L", "Etiqueta"], total: 100, pagado: 50 },
      { fecha: "2025-06-05", cliente: "Ana López", estado: "pendiente", productos: ["Maceta pequeña"], total: 150, pagado: 0 }
    ];

    const filtros = {
      fechaInicio: document.getElementById("fechaInicio"),
      fechaFin: document.getElementById("fechaFin"),
      cliente: document.getElementById("cliente"),
      estado: document.getElementById("estado")
    };

    Object.values(filtros).forEach(input => {
      input.addEventListener("input", filtrarVentas);
      input.addEventListener("change", filtrarVentas);
    });

    function filtrarVentas() {
      const tbody = document.querySelector("#tablaVentas tbody");
      tbody.innerHTML = "";

      const fechaInicio = filtros.fechaInicio.value;
      const fechaFin = filtros.fechaFin.value;
      const cliente = filtros.cliente.value.toLowerCase();
      const estado = filtros.estado.value.toLowerCase();

      const resultados = ventas.filter(v => {
        const fecha = new Date(v.fecha);
        const inicio = fechaInicio ? new Date(fechaInicio) : null;
        const fin = fechaFin ? new Date(fechaFin) : null;

        const coincideFecha = (!inicio || fecha >= inicio) && (!fin || fecha <= fin);
        const coincideCliente = !cliente || v.cliente.toLowerCase().includes(cliente);
        const coincideEstado = !estado || v.estado.toLowerCase() === estado;
        const incluir = !["en preparacion", "entregado", "sin trabajar"].includes(v.estado.toLowerCase());

        return coincideFecha && coincideCliente && coincideEstado && incluir;
      });

      if (resultados.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center">No se encontraron resultados</td></tr>`;
      } else {
        resultados.forEach(v => {
          let estadoPago = "<span class='text-danger fw-bold'>Sin abono</span>";
          if (v.pagado === v.total) estadoPago = "<span class='text-success fw-bold'>Liquidado</span>";
          else if (v.pagado > 0 && v.pagado < v.total) estadoPago = "<span class='text-warning fw-bold'>Con saldo</span>";

          tbody.innerHTML += `
            <tr>
              <td>${v.fecha}</td>
              <td>${v.cliente}</td>
              <td>${v.estado}</td>
              <td>${v.productos.join(", ")}</td>
              <td>${estadoPago}</td>
            </tr>`;
        });
      }
    }

    function exportarExcel() {
      const tabla = document.getElementById("tablaVentas");
      const wb = XLSX.utils.table_to_book(tabla, { sheet: "Reporte" });
      XLSX.writeFile(wb, "reporte_ventas.xlsx");
    }

    filtrarVentas();
  </script>
</body>
</html>
