<?php
require_once __DIR__ . '/../../includes/config.php';

$database = new Database();
$pdo = $database->conectar();

$semana = $_GET['semana'] ?? date('Y-m-d');

$inicio_semana = date('Y-m-d', strtotime('monday this week', strtotime($semana)));
$fin_semana    = date('Y-m-d', strtotime('sunday this week', strtotime($semana)));

$stmt = $pdo->prepare("
    SELECT *
    FROM nomina_general
    WHERE fecha_inicio BETWEEN ? AND ?
");
$stmt->execute([$inicio_semana, $fin_semana]);
$nominas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Imprimir Nómina</title>

<style>
body {
    font-family: Arial;
    font-size: 12px;
}

.header {
    text-align: center;
    margin-bottom: 20px;
}

.bloque {
    border: 2px solid #000;
    padding: 10px;
    margin-bottom: 20px;
}

.tabla {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}

.tabla td, .tabla th {
    border: 1px solid #000;
    padding: 5px;
    text-align: center;
}

.total {
    font-weight: bold;
}

@media print {
    button {
        display: none;
    }
}
</style>
</head>

<body>

<button onclick="window.print()">🖨️ Descargar PDF</button>

<div class="header">
    <h2>NÓMINA SEMANA</h2>
    <strong>
        Del <?= date('d/m/Y', strtotime($inicio_semana)) ?>
        al <?= date('d/m/Y', strtotime($fin_semana)) ?>
    </strong>
</div>

<?php foreach ($nominas as $nomina): ?>

<div class="bloque">

    <strong>Empleados Pagados:</strong>
    <?= $nomina['empleados_pagados'] ?>

    <br><br>

    <table class="tabla">
        <tr>
            <td>Total Sueldos</td>
            <td>$<?= number_format($nomina['total_sueldos'],2) ?></td>
        </tr>
        <tr>
            <td>Total Extras</td>
            <td>$<?= number_format($nomina['total_actividades_extras'],2) ?></td>
        </tr>
        <tr>
            <td>Total Deducciones</td>
            <td>$<?= number_format($nomina['total_deducciones'],2) ?></td>
        </tr>
        <tr class="total">
            <td>Total a Pagar</td>
            <td>$<?= number_format($nomina['total_a_pagar'],2) ?></td>
        </tr>
    </table>

</div>

<?php endforeach; ?>

<script>
window.onload = function() {
    window.print();
}
</script>

</body>
</html>