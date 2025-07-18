<?php
session_start();
session_unset();      // Limpia las variables de sesión
session_destroy();    // Destruye la sesión

// Redirige al login
header("Location: login.php");
exit();
?>
