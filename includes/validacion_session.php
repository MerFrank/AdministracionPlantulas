<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['ID_Operador'])) {
    header('Location: /AdministracionPlantulas/session/login.php');
    exit;
}
