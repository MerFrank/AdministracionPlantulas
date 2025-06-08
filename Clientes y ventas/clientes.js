// clientes.js

// Lista simulada de clientes en memoria
let clientes = [];

// Función para agregar un cliente
function agregarCliente(nombre, tipo, contacto, correo, direccion) {
  const cliente = {
    id: Date.now(),
    nombre,
    tipo,
    contacto,
    correo,
    direccion,
  };
  clientes.push(cliente);
  console.log("Cliente agregado:", cliente);
  mostrarClientes();
}

// Función para mostrar clientes en consola (o en UI si se extiende)
function mostrarClientes() {
  console.clear();
  console.table(clientes);
}

// Función para eliminar cliente por id
function eliminarCliente(id) {
  clientes = clientes.filter(c => c.id !== id);
  mostrarClientes();
}

// Ejemplo simple de uso en consola para test
// agregarCliente("Juan Pérez", "mayoreo", "555-1234", "juan@mail.com", "Calle 1 #100");
// agregarCliente("Ana López", "menudeo", "555-5678", "ana@mail.com", "Calle 2 #200");

// mostrarClientes();

// Para integración con formulario y UI debes agregar listeners y manejo DOM
