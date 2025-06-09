// Registrar Cliente
document.getElementById('clienteForm').addEventListener('submit', function(event) {
  event.preventDefault();
  const cliente = {
    nombre: document.getElementById('nombre').value,
    tipo: document.getElementById('tipo').value,
    contacto: document.getElementById('contacto').value,
    correo: document.getElementById('correo').value,
    direccion: document.getElementById('direccion').value
  };
  console.log("Cliente guardado:", cliente);
  alert("Cliente registrado exitosamente");
  this.reset();
});

// Registrar Producto
document.getElementById('productoForm').addEventListener('submit', function(event) {
  event.preventDefault();
  const producto = {
    nombre: document.getElementById('nombreProducto').value,
    descripcion: document.getElementById('descripcionProducto').value,
    precio: parseFloat(document.getElementById('precioProducto').value),
    cantidad: parseInt(document.getElementById('cantidadProducto').value),
    categoria: document.getElementById('categoriaProducto').value
  };
  console.log("Producto guardado:", producto);
  alert("Producto registrado exitosamente");
  this.reset();
});
// Registrar Presupuesto
document.getElementById('presupuestoForm').addEventListener('submit', function(event) {
  event.preventDefault();
  const presupuesto = {
    cliente: document.getElementById('clientePresupuesto').value,
    detalle: document.getElementById('detallePresupuesto').value,
    monto: parseFloat(document.getElementById('montoPresupuesto').value)
  };
  console.log("Presupuesto guardado:", presupuesto);
  alert("Presupuesto registrado exitosamente");
  this.reset();
});

// Registrar Pedido
document.getElementById('pedidoForm').addEventListener('submit', function(event) {
  event.preventDefault();
  const pedido = {
    cliente: document.getElementById('clientePedido').value,
    productos: document.getElementById('productosPedido').value,
    fechaEntrega: document.getElementById('fechaEntregaPedido').value
  };
  console.log("Pedido guardado:", pedido);
  alert("Pedido registrado exitosamente");
  this.reset();
});

// Registrar Pago
document.getElementById('pagoForm').addEventListener('submit', function(event) {
  event.preventDefault();
  const pago = {
    cliente: document.getElementById('clientePago').value,
    monto: parseFloat(document.getElementById('montoPago').value),
    metodo: document.getElementById('metodoPago').value,
    fecha: document.getElementById('fechaPago').value
  };
  console.log("Pago guardado:", pago);
  alert("Pago registrado exitosamente");
  this.reset();
});
