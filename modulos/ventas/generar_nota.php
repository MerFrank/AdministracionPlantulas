<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/numeros_a_letras.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("<div style='text-align:center; padding:20px;'>
            <h2>Error de conexión</h2>
            <p>No se pudo conectar a la base de datos.</p>
            <p>Detalles: " . htmlspecialchars($e->getMessage()) . "</p>
            <a href='seleccionar_venta_nota.php' class='btn btn-primary'>Volver a selección</a>
         </div>");
}

// Validación mejorada del ID de venta
$id_venta = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => [
        'min_range' => 1
    ]
]);

if ($id_venta === false || $id_venta === null) {
    die("<div style='text-align:center; padding:20px;'>
            <h2>ID de venta no válido</h2>
            <p>No se ha proporcionado un ID de venta válido en la URL.</p>
            <a href='seleccionar_venta_nota.php' class='btn btn-primary'>Seleccionar venta</a>
         </div>");
}

// Obtener información de la venta
$stmt = $con->prepare("
    SELECT 
        np.*, 
        c.nombre_Cliente as cliente_nombre, 
        c.domicilio_fiscal as direccion, 
        c.telefono,
        
        -- 'datos_garantia'

        g.nom_bien,                     
        g.num_regis,                    
        g.nom_aval,                     
        g.monto as monto_garantia       
        
    FROM notaspedidos np
    LEFT JOIN clientes c ON np.id_cliente = c.id_cliente
    LEFT JOIN datos_garantia g ON np.id_notaPedido = g.id_notaPedido 
    WHERE np.id_notaPedido = ?
");
$stmt->execute([$id_venta]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    die("Venta no encontrada");
}

// Obtener detalles de la venta (solo colores)
$stmt = $con->prepare("
    SELECT col.nombre_color as color, 
           SUM(dnp.cantidad) as cantidad, 
           dnp.precio_unitario as precio_unitario, 
           SUM(dnp.monto_total) as subtotal
    FROM detallesnotapedido dnp
    JOIN variedades v ON dnp.id_variedad = v.id_variedad
    JOIN colores col ON v.id_color = col.id_color
    WHERE dnp.id_notaPedido = ?
    GROUP BY col.nombre_color, dnp.precio_unitario
    ORDER BY col.nombre_color
");
$stmt->execute([$id_venta]);
$detalles = $stmt->fetchAll();

// Convertir importe a letras
$importe_letra = numeros_a_letras($venta['total']);

if (!empty($venta['nom_bien'])) {


    $bien_info = "Se recibe como garantía un: " . htmlspecialchars($venta['nom_bien']);


    if (!empty($venta['num_regis'])) {
        $bien_info .= " Con número de registro/factura: " . htmlspecialchars(trim($venta['num_regis']));
    }


    $monto = "";
    if (!empty($venta['monto_garantia'])) {
        $monto = " Con un monto estimado de: $" . number_format($venta['monto_garantia'], 2);
    }


    $aval = "";
    if (!empty($venta['nom_aval'])) {
        $aval = " Quedando como aval: " . htmlspecialchars($venta['nom_aval']);
    }


    $texto_garantia_aval = trim($bien_info . $monto . $aval) . ".";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Venta #<?= $id_venta ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            width: 21cm;
            margin: 0 auto;
        }

        .garantia-info {
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed #000;

            font-size: 9px;
            line-height: 1.3;
            color: #333;
            text-align: justify;
        }


        .nota-container {
            padding: 1cm;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .empresa {
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
        }

        .direccion-empresa {
            font-size: 10px;
            margin: 3px 0;
            line-height: 1.2;
        }

        .titulo {
            font-size: 16px;
            font-weight: bold;
            margin: 8px 0;
            text-decoration: underline;
        }

        .folio {
            font-size: 12px;
            text-align: right;
            margin-bottom: 10px;
        }

        .info-cliente {
            margin-bottom: 15px;
        }

        .info-cliente p {
            margin: 2px 0;
        }

        .label {
            font-weight: bold;
            display: inline-block;
            width: 70px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
            font-size: 11px;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .importe-letra {
            margin: 10px 0;
            padding: 5px;
            border: 1px solid #000;
            font-size: 11px;
        }

        .total {
            text-align: right;
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0;
        }

        .pagare {
            margin-top: 15px;
            font-size: 9px;
            line-height: 1.2;
            border: 1px solid #000;
            padding: 5px;
        }

        .firmas {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }

        .firma {
            width: 45%;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
            font-size: 10px;
        }

        .no-print {
            text-align: center;
            margin-top: 20px;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
            }

            .nota-container {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="nota-container">
        <div class="header">
            <div class="empresa">Plantulas Agrodex S.C. de P de R.L. de C.V.</div>
            <div class="direccion-empresa">
                ING. SILVESTRE PEREZ PEREZ<br>
                CALLE 16 DE SEPTIEMBRE S/N. COL. EMILIANO ZAPATA<br>
                TENANCINGO, EDO. DE MEXICO 52433<br>
                CELS.: 7222041444<br>
                E-mail: plantasdoc@hotmail.com
            </div>
            <div class="titulo">NOTA DE REMISION</div>
            <div class="folio">No. <?= htmlspecialchars($venta['num_remision']) ?></div>
        </div>

        <div class="info-cliente">
            <p><span class="label">TENANCINGO, MEX.</span> <?= date('d/m/Y', strtotime($venta['fechaPedido'])) ?></p>
            <p><span class="label">DE:</span> <?= htmlspecialchars($venta['cliente_nombre'] ?? 'Sin cliente') ?></p>
            <p><span class="label">Dirección:</span> <?= htmlspecialchars($venta['direccion'] ?? '') ?></p>
            <p><span class="label">Tel.:</span> <?= htmlspecialchars($venta['telefono'] ?? '') ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>VARIEDAD Y/O ESPECIE</th>
                    <th>CANTIDAD DE PLANTAS</th>
                    <th>COSTO POR PLANTA</th>
                    <th>SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?= htmlspecialchars($detalle['color']) ?></td>
                        <td><?= $detalle['cantidad'] ?></td>
                        <td>$<?= number_format($detalle['precio_unitario'], 2) ?></td>
                        <td>$<?= number_format($detalle['subtotal'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: bold;">TOTAL:</td>
                    <td style="font-weight: bold;">$<?= number_format($venta['total'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="importe-letra">
            <strong>IMPORTE CON LETRA:</strong> <?= strtoupper($importe_letra) ?>
        </div>

        <?php if ($venta['tipo_pago'] === 'credito'): ?>
            <div class="pagare">
                <p><strong>PAGARÉ No. <?= $venta['num_pagare'] ?></strong> Bueno por: $<?= number_format($venta['total'], 2) ?></p>
                <p>Dato y pagaré incondicionalmente por este pagaré a la orden del <strong>Ing. Silvestre Pérez Pérez</strong> en la ciudad de Tenancingo, Mex. y/o en cualquier lugar que se me requiera el pago, el día <?= date('d/m/Y', strtotime($venta['fecha_validez'])) ?>.</p>
                <p>Vale de la mercancía que he recibido a mi entera satisfacción. Este pagaré forma parte de una serie numerada del ______ al ______ y están todos sujetos a la condición de que, al no pagarse cualquiera de ellos a su vencimiento, serán exigibles todos los que siguen a números siguientes de los ya vencidos, desde la fecha de vencimiento de este documento hasta el día de su liquidación. Este pagaré es mercantil y está regido por la ley general de títulos y operaciones de crédito.</p>

                <?php if (!empty($texto_garantia_aval)): ?>
                    <div class="garantia-info">
                        <strong>EN GARANTÍA:</strong> <?= $texto_garantia_aval ?>
                    </div>
                <?php endif; ?>
            </div>
    </div>

    <div class="firmas">
        <div class="firma">
            Dirección: <?= htmlspecialchars($venta['direccion'] ?? '') ?><br>
            Población: <?= htmlspecialchars($venta['direccion'] ?? '') ?><br>
            C. P. ______ Teléfono: <?= htmlspecialchars($venta['telefono'] ?? '') ?>
        </div>
        <div class="firma">
            Acepto de Conformidad:<br><br>
            ___________________________
        </div>
    </div>
<?php endif; ?>
</div>

<div class="no-print">
    <button onclick="window.print()" class="btn btn-primary">Imprimir Nota</button>
    <button onclick="window.close()" class="btn btn-secondary">Cerrar</button>
</div>
</body>

</html>