<?php
require('./includes/config.php');
?>


<?php
// Variables para el encabezado
$titulo = "Página principal";
$encabezado = "Este es el index";
$subtitulo = "En esta página puedes ver el contenido del index";

// Incluir la cabecera
require('./includes/header.php');
?>


<main class="container mt-4">
  <p>Contenido principal del index aquí.</p>
</main>

<?php require('./includes/footer.php'); ?>
