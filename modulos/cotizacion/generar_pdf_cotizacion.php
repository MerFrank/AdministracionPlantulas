<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Verificar si el ID viene por GET o POST
$id_cotizacion = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

if (!$id_cotizacion) {
    $_SESSION['error_message'] = 'No se ha especificado una cotización para generar el PDF';
    header('Location: lista_cotizaciones.php');
    exit;
}

$stmt = $con->prepare("
    SELECT 
        c.*,
        cl.nombre_Cliente AS cliente_nombre,
        cl.telefono AS cliente_telefono,
        cl.nombre_Empresa AS cliente_empresa
    FROM cotizaciones c
    JOIN clientes cl ON c.id_cliente = cl.id_cliente
    WHERE c.id_cotizacion = ?
");
$stmt->execute([$id_cotizacion]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cotizacion) {
    $_SESSION['error_message'] = 'La cotización solicitada no existe';
    header('Location: lista_cotizaciones.php');
    exit;
}

$items = json_decode($cotizacion['items'], true);

function formatoCantidadLetras($cantidad) {
    $unidades = ["", "un", "dos", "tres", "cuatro", "cinco", "seis", "siete", "ocho", "nueve"];
    $decenas = ["", "diez", "veinte", "treinta", "cuarenta", "cincuenta", "sesenta", "setenta", "ochenta", "noventa"];
    $especiales = ["once", "doce", "trece", "catorce", "quince", "dieciséis", "diecisiete", "dieciocho", "diecinueve"];
    $centenas = ["", "ciento", "doscientos", "trescientos", "cuatrocientos", "quinientos", 
                "seiscientos", "setecientos", "ochocientos", "novecientos"];
    
    $entero = intval($cantidad);
    $decimales = intval(round(($cantidad - $entero) * 100));
    
    $texto = "";
    
    if ($entero == 0) {
        $texto = "cero";
    } elseif ($entero < 1000) {
        $texto = convertirMenorMil($entero);
    } elseif ($entero < 1000000) {
        $miles = intval($entero / 1000);
        $resto = $entero % 1000;
        $texto = convertirMenorMil($miles) . " mil";
        if ($resto > 0) {
            $texto .= " " . convertirMenorMil($resto);
        }
    } elseif ($entero < 100000000) {
        $millones = intval($entero / 1000000);
        $resto = $entero % 1000000;
        $texto = ($millones == 1 ? "un millón" : convertirMenorMil($millones) . " millones");
        if ($resto > 0) {
            $texto .= " " . formatoCantidadLetras($resto);
        }
    } else {
        $texto = "cien millones";
    }
    
    $texto .= " pesos";
    if ($decimales > 0) {
        $texto .= " " . ($decimales == 1 ? "un" : convertirMenorMil($decimales)) . " centavo" . ($decimales > 1 ? "s" : "");
    }
    
    return ucfirst($texto);
}

function convertirMenorMil($numero) {
    $unidades = ["", "un", "dos", "tres", "cuatro", "cinco", "seis", "siete", "ocho", "nueve"];
    $decenas = ["", "diez", "veinte", "treinta", "cuarenta", "cincuenta", "sesenta", "setenta", "ochenta", "noventa"];
    $especiales = ["once", "doce", "trece", "catorce", "quince", "dieciséis", "diecisiete", "dieciocho", "diecinueve"];
    $centenas = ["", "ciento", "doscientos", "trescientos", "cuatrocientos", "quinientos", 
                "seiscientos", "setecientos", "ochocientos", "novecientos"];
    
    if ($numero == 0) return "";
    if ($numero == 100) return "cien";
    
    $texto = "";
    $centena = intval($numero / 100);
    $decena = intval(($numero % 100) / 10);
    $unidad = $numero % 10;
    
    if ($centena > 0) {
        $texto .= $centenas[$centena];
    }
    
    $resto = $numero % 100;
    if ($resto > 0) {
        if ($centena > 0) $texto .= " ";
        
        if ($resto < 10) {
            $texto .= $unidades[$resto];
        } elseif ($resto >= 11 && $resto <= 19) {
            $texto .= $especiales[$resto - 11];
        } else {
            $texto .= $decenas[$decena];
            if ($unidad > 0 && $resto > 20) {
                $texto .= " y " . $unidades[$unidad];
            }
        }
    }
    
    return $texto;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vista Previa Nota de Remisión - <?= htmlspecialchars($cotizacion['folio']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .preview-container { 
            width: 210mm; 
            min-height: 297mm; 
            margin: 0 auto; 
            padding: 20px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            background: white;
        }
        .header { text-align: center; margin-bottom: 15px; }
        .header h1 { font-size: 16px; margin: 5px 0; font-weight: bold; }
        .header p { font-size: 12px; margin: 3px 0; }
        .info-box { margin: 10px 0; font-size: 12px; }
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }
        .table th, .table td { border: 1px solid #000; padding: 5px; }
        .table th { text-align: center; font-weight: bold; }
        .total-section { margin-top: 20px; font-size: 12px; }
        .pagare { 
            margin-top: 30px; 
            font-size: 12px; 
            line-height: 1.5;
        }
        .pagare-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        .pagare-content {
            margin-bottom: 15px;
        }
        .pagare-signature {
            margin-top: 20px;
        }
        .footer { margin-top: 20px; font-size: 10px; }
        .action-buttons { 
            text-align: center; 
            margin: 20px 0; 
            padding: 20px; 
            background: #f5f5f5; 
            border-radius: 5px; 
        }
        .btn { 
            padding: 10px 20px; 
            margin: 0 10px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px;
        }
        .btn-download { 
            background: #28a745; 
            color: white; 
        }
        .btn-close { 
            background: #dc3545; 
            color: white; 
        }
    </style>
</head>
<body>
    <div class="preview-container" id="pdf-preview">
        <div class="header">
            <h1>Venta de Plantas *In vitro*</h1>
            <p>ING. SILVESTRE PEREZ PEREZ</p>
            <p>CALLE 16 DE SEPTIEMBRE S/N. COL. EMILIANO ZAPATA</p>
            <p>TENANCINGO, EDO. DE MEXICA 52433</p>
            <p>CELS.: 7222041444  E-mail: plantasdoc@hotmail.com</p>
            <h1>NOTA DE REMISION</h1>
            <p>No. <?= htmlspecialchars($cotizacion['folio']) ?></p>
        </div>

        <div class="info-box">
            <p><strong>TENANCINGO, MEX.</strong></p>
            <p>DE</p>
            <p>Nombre: <?= htmlspecialchars($cotizacion['cliente_nombre']) ?></p>
            <p>Dirección: <?= htmlspecialchars($cotizacion['cliente_empresa'] ?? 'N/A') ?></p>
            <p>Tel: <?= htmlspecialchars($cotizacion['cliente_telefono']) ?></p>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>VARIEDAD Y/O ESPECIE</th>
                    <th>CANTIDAD DE PLANTAS</th>
                    <th>COSTO POR PLANTA</th>
                    <th>SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['especie']) ?>
                        <?= $item['color'] ? ' - '.htmlspecialchars($item['color']) : '' ?>
                        <?= ($item['variedad'] && $item['variedad'] !== 'N/A') ? ' - '.htmlspecialchars($item['variedad']) : '' ?>
                    </td>
                    <td><?= htmlspecialchars($item['cantidad']) ?></td>
                    <td>$<?= number_format($item['precio_unitario'], 2) ?></td>
                    <td>$<?= number_format($item['cantidad'] * $item['precio_unitario'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            <p><strong>IMPORTE CON LETRA:</strong> <?= formatoCantidadLetras($cotizacion['total']) ?></p>
            <p><strong>TOTAL $<?= number_format($cotizacion['total'], 2) ?></strong></p>
        </div>

        <div class="pagare">
            <div class="pagare-title">PAGARÉ</div>
            
            <div class="pagare-content">
                <p>No. <?= htmlspecialchars($cotizacion['folio']) ?> &nbsp;&nbsp;&nbsp;&nbsp; Bueno por:</p>
                <p>Debo y pagaré incondicionalmente por este pagaré a la orden del <strong>Ing. Silvestre Pérez Pérez</strong> en la ciudad de Tenancingo, Méx.</p>
                <p>y/o en cualquier lugar que se me requiera el pago, el día ______ (______)</p>
                <p>la cantidad de $ ______ (<?= formatoCantidadLetras($cotizacion['total']) ?>).</p>
                
                <p>Valor de la mercancía que he recibido a mi entera satisfacción. Este pagaré forma parte de una serie numerada del ______ al ______</p>
                <p>y están todos sujetos a la condición de que, al no pagarse cualquiera de ellos a su vencimiento; serán exigibles todos los que</p>
                <p>sigan en número, además de los ya vencidos, desde la fecha de vencimiento de este documento hasta el día de su liquidación,</p>
                <p>causará intereses moratorios al tipo de ______ % mensual, pagaderos en esta ciudad juntamente con el adeudo principal.</p>
                <p>Este pagaré es mercantil y está regido por la ley general de títulos y operaciones de crédito. En su artículo 173 parte final y</p>
                <p>artículos correlativos, por no ser pagaré domiciliario.</p>
            </div>
            
            <div class="pagare-signature">
                <p>Nombre: _______________________________</p>
                <p>Dirección: _______________________________</p>
                <p>Población: _______________________________</p>
                <p>C. P. ______ Teléfono: ______</p>
                <p>Acepto de Conformidad: _______________________________</p>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <button class="btn btn-download" onclick="generatePDF()">Descargar PDF</button>
        <button class="btn btn-close" onclick="window.close()">Cerrar Vista Previa</button>
    </div>

    <script>
    function generatePDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        const cotizacion = {
            folio: '<?= htmlspecialchars($cotizacion['folio']) ?>',
            fecha: '<?= htmlspecialchars($cotizacion['fecha']) ?>',
            cliente: '<?= htmlspecialchars($cotizacion['cliente_nombre']) ?>',
            telefono: '<?= htmlspecialchars($cotizacion['cliente_telefono']) ?>',
            empresa: '<?= htmlspecialchars($cotizacion['cliente_empresa'] ?? '') ?>',
            total: <?= $cotizacion['total'] ?>,
            items: <?= json_encode($items) ?>,
            importeLetras: '<?= addslashes(formatoCantidadLetras($cotizacion['total'])) ?>'
        };

        // Encabezado
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.text('Venta de Plantas *In vitro*', 105, 15, { align: 'center' });
        
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text('ING. SILVESTRE PEREZ PEREZ', 105, 20, { align: 'center' });
        doc.text('CALLE 16 DE SEPTIEMBRE S/N. COL. EMILIANO ZAPATA', 105, 25, { align: 'center' });
        doc.text('TENANCINGO, EDO. DE MEXICA 52433', 105, 30, { align: 'center' });
        doc.text('CELS.: 7222041444  E-mail: plantasdoc@hotmail.com', 105, 35, { align: 'center' });
        
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.text('NOTA DE REMISION', 105, 45, { align: 'center' });
        doc.text(`No. ${cotizacion.folio}`, 105, 50, { align: 'center' });
        
        // Datos del cliente
        doc.setFontSize(10);
        doc.text('TENANCINGO, MEX.', 20, 60);
        doc.text('DE', 20, 65);
        doc.text(`Nombre: ${cotizacion.cliente}`, 20, 70);
        doc.text(`Dirección: ${cotizacion.empresa || 'N/A'}`, 20, 75);
        doc.text(`Tel: ${cotizacion.telefono}`, 20, 80);
        
        // Tabla de productos
        const headers = [['VARIEDAD Y/O ESPECIE', 'CANTIDAD DE PLANTAS', 'COSTO POR PLANTA', 'SUBTOTAL']];
        const tableData = cotizacion.items.map(item => [
            `${item.especie}${item.color ? ' - ' + item.color : ''}${item.variedad && item.variedad !== 'N/A' ? ' - ' + item.variedad : ''}`,
            item.cantidad,
            `$${item.precio_unitario.toFixed(2)}`,
            `$${(item.cantidad * item.precio_unitario).toFixed(2)}`
        ]);
        
        doc.autoTable({
            startY: 85,
            head: headers,
            body: tableData,
            margin: { left: 15 },
            styles: { fontSize: 8, cellPadding: 3 },
            headStyles: { fillColor: [255, 255, 255], textColor: [0, 0, 0], fontStyle: 'bold' }
        });
        
        // Total
        const finalY = doc.lastAutoTable.finalY + 10;
        doc.text('IMPORTE CON LETRA:', 20, finalY);
        doc.text(cotizacion.importeLetras, 20, finalY + 5, { maxWidth: 180 });
        doc.text(`TOTAL $${cotizacion.total.toFixed(2)}`, 160, finalY);
        
        // Sección de pagaré
        let pagareY = finalY + 20;
        
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.text('PAGARÉ', 105, pagareY, { align: 'center' });
        pagareY += 8;
        
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text(`No. ${cotizacion.folio}       Bueno por:`, 20, pagareY);
        pagareY += 7;
        
        doc.text(`Debo y pagaré incondicionalmente por este pagaré a la orden del Ing. Silvestre Pérez Pérez`, 20, pagareY, { maxWidth: 180 });
        pagareY += 5;
        doc.text(`en la ciudad de Tenancingo, Méx. y/o en cualquier lugar que se me requiera el pago, el día ______ (______)`, 20, pagareY, { maxWidth: 180 });
        pagareY += 5;
        doc.text(`la cantidad de $ ______ (${cotizacion.importeLetras}).`, 20, pagareY, { maxWidth: 180 });
        pagareY += 7;
        
        doc.text(`Valor de la mercancía que he recibido a mi entera satisfacción. Este pagaré forma parte de una serie numerada del ______ al ______`, 20, pagareY, { maxWidth: 180 });
        pagareY += 5;
        doc.text(`y están todos sujetos a la condición de que, al no pagarse cualquiera de ellos a su vencimiento; serán exigibles todos los que`, 20, pagareY, { maxWidth: 180 });
        pagareY += 5;
        doc.text(`sigan en número, además de los ya vencidos, desde la fecha de vencimiento de este documento hasta el día de su liquidación,`, 20, pagareY, { maxWidth: 180 });
        pagareY += 5;
        doc.text(`causará intereses moratorios al tipo de ______ % mensual, pagaderos en esta ciudad juntamente con el adeudo principal.`, 20, pagareY, { maxWidth: 180 });
        pagareY += 5;
        doc.text(`Este pagaré es mercantil y está regido por la ley general de títulos y operaciones de crédito. En su artículo 173 parte final y`, 20, pagareY, { maxWidth: 180 });
        pagareY += 5;
        doc.text(`artículos correlativos, por no ser pagaré domiciliario.`, 20, pagareY, { maxWidth: 180 });
        pagareY += 10;
        
        // Firma
        doc.text('Nombre: _______________________________', 20, pagareY);
        pagareY += 7;
        doc.text('Dirección: _______________________________', 20, pagareY);
        pagareY += 7;
        doc.text('Población: _______________________________', 20, pagareY);
        pagareY += 7;
        doc.text('C. P. ______ Teléfono: ______', 20, pagareY);
        pagareY += 7;
        doc.text('Acepto de Conformidad: _______________________________', 20, pagareY);
        
        // Guardar PDF
        doc.save(`Nota_Remision_${cotizacion.folio}.pdf`);
    }
    </script>
</body>
</html>