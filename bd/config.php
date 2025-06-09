<?php
$servername = " sql113.infinityfree.com "; // Host real que te dio el panel
$username = "if0_38718916";              // Usuario de tu cuenta
$password = "eaEiLdT133";                 // Tu contraseña
$database = "if0_38718916_clientes_ventas"; // Nombre de la base

// Crear conexión
$conn = new mysqli($servername, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// ✅ Esta línea evita errores con acentos y ñ
$conn->set_charset("utf8mb4");
?>
