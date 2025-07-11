<?php
require_once __DIR__ . '/funciones.php';

function verificarAutenticacion() {
    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirigir('modulos/auth/login.php');
    }
}

function verificarRol($rolRequerido) {
    verificarAutenticacion();
    if ($_SESSION['usuario_rol'] != $rolRequerido) {
        $_SESSION['error'] = "No tienes permisos para acceder a esta sección";
        redirigir('dashboard.php');
    }
}
?>