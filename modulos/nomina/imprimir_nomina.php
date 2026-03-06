<?php
require_once __DIR__ . '/../../includes/config.php';

$database = new Database();
$pdo = $database->conectar();

$semana = $_GET['semana'] ?? date('Y-m-d');

$inicio_semana = date('Y-m-d', strtotime('monday this week', strtotime($semana)));
$fin_semana = date('Y-m-d', strtotime('sunday this week', strtotime($semana)));

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
        @page {
            size: letter;
            margin: 10mm;
        }

        body {
            font-family: Arial;
            font-size: 11px;
            margin: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .contenedor {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .bloque {
            width: 48%;
            border: 2px solid #000;
            padding: 10px;
            margin-bottom: 15px;
            box-sizing: border-box;

            /* 🔥 Evita que se parta entre páginas */
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .tabla {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .tabla td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            word-wrap: break-word;
        }

        .total {
            font-weight: bold;
            background: #f2f2f2;
        }

        @media print {
            button {
                display: none;
            }

            .bloque {
                break-inside: avoid;
                page-break-inside: avoid;
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

    <div class="contenedor">

        <?php foreach ($nominas as $nomina): ?>

            <?php
            $stmtDetalle = $pdo->prepare("
        SELECT 
            nd.*,
            CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', e.apellido_materno) AS nombre_completo
        FROM nomina_detalle nd
        LEFT JOIN empleados e ON nd.id_empleado = e.id_empleado
        WHERE nd.id_nomina_general = ?
    ");
            $stmtDetalle->execute([$nomina['id_nomina_general']]);
            $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php foreach ($detalles as $det): ?>

                <div class="bloque">

                    <strong>Empleado:</strong><br>
                    <?= $det['nombre_completo'] ?><br><br>

                    <strong>Días Laborados:</strong> <?= $det['dias_laborados'] ?><br><br>

                    <table class="tabla">
                        <tr>
                            <td>Sueldo Base</td>
                            <td>$<?= number_format($det['sueldo_base'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Extras</td>
                            <td>$<?= number_format($det['actividades_extras'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Deducciones</td>
                            <td>$<?= number_format($det['deducciones'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Descuento Prestamo</td>
                            <td>$<?= number_format($det['prestamo_descuento'], 2) ?></td>
                        </tr>
                        <tr class="total">
                            <td>Total a Pagar</td>
                            <td>$<?= number_format($det['total_pagar'], 2) ?></td>
                        </tr>
                    </table>

                </div>

            <?php endforeach; ?>

        <?php endforeach; ?>

    </div>

    <script>
        window.onload = function () {
            window.print();
        }
    </script>

</body>

</html>