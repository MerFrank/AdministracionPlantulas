<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
// Iniciar sesión si no está iniciada


require_once __DIR__ . '/../../includes/config.php';

// Función mejorada para convertir números a letras
function formatoCantidadLetras($cantidad) {
    $unidades = ["", "un", "dos", "tres", "cuatro", "cinco", "seis", "siete", "ocho", "nueve"];
    $decenas = ["", "diez", "veinte", "treinta", "cuarenta", "cincuenta", "sesenta", "setenta", "ochenta", "noventa"];
    $especiales = ["once", "doce", "trece", "catorce", "quince", "dieciséis", "diecisiete", "dieciocho", "diecinueve"];
    $centenas = ["", "ciento", "doscientos", "trescientos", "cuatrocientos", "quinientos", 
                "seiscientos", "setecientos", "ochocientos", "novecientos"];
    
    $entero = intval($cantidad);
    $decimales = intval(round(($cantidad - $entero) * 100));
    
    $texto = "";
    
    // Convertir parte entera
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
        $texto = "cien millones"; // Máximo soportado
    }
    
    // Agregar parte decimal
    $texto .= " pesos";
    if ($decimales > 0) {
        $texto .= " " . ($decimales == 1 ? "un" : convertirMenorMil($decimales)) . " centavo" . ($decimales > 1 ? "s" : "");
        }
    
    return ucfirst($texto);
}

// Función auxiliar para convertir números menores a 1000
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

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener ID del egreso desde GET o POST
    $id_egreso = $_GET['id'] ?? $_POST['id_egreso'] ?? null;
    
    if ($id_egreso) {
        // Consultar la base de datos para obtener los datos del egreso incluyendo el proveedor
        $sql = "SELECT e.*, s.nombre AS sucursal, p.nombre_proveedor AS proveedor 
                FROM egresos e 
                LEFT JOIN sucursales s ON e.id_sucursal = s.id_sucursal 
                LEFT JOIN proveedores p ON e.id_proveedor = p.id_proveedor
                WHERE e.id_egreso = ?";
        $stmt = $con->prepare($sql);
        $stmt->execute([$id_egreso]);
        $egreso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($egreso) {
            // Datos del egreso
            $datos = [
                'invernadero' => $egreso['sucursal'] ?? 'Sucursal no especificada',
                'folio' => 'EG-' . str_pad($id_egreso, 4, '0', STR_PAD_LEFT),
                'monto' => floatval($egreso['monto'] ?? 0),
                'fecha' => $egreso['fecha'] ?? date('Y-m-d'),
                'concepto' => $egreso['concepto'] ?? 'Concepto no especificado',
                'autorizado_por' => $_SESSION['usuario_nombre'] ?? 'Responsable de Finanzas',
                'recibido_por' => $egreso['proveedor'] ?? 'Receptor no especificado'
            ];
        } else {
            // Si no se encuentra el egreso, usar datos por defecto
            $datos = [
                'invernadero' => 'Sucursal no especificada',
                'folio' => '0000',
                'monto' => 0,
                'fecha' => date('Y-m-d'),
                'concepto' => 'Concepto no especificado',
                'autorizado_por' => 'Responsable de Finanzas',
                'recibido_por' => 'Receptor no especificado'
            ];
        }
    } else {
        // Si no hay ID, usar datos de POST o valores por defecto
        $datos = [
            'invernadero' => $_POST['invernadero'] ?? 'Sucursal no especificada',
            'folio' => $_POST['folio'] ?? '0000',
            'monto' => floatval($_POST['monto'] ?? 0),
            'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
            'concepto' => $_POST['concepto'] ?? 'Concepto no especificado',
            'autorizado_por' => $_POST['autorizado_por'] ?? 'Responsable de Finanzas',
            'recibido_por' => $_POST['recibido_por'] ?? 'Receptor no especificado'
        ];
    }

    // Sanitizar todos los datos
    foreach ($datos as $key => $value) {
        if (is_string($value)) {
            $datos[$key] = htmlspecialchars($value);
        }
    }
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vale Provisional de Caja</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 20px;
        }
        .vale-container {
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
            border: 1px solid #ccc;
        }
        .vale-header {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
            text-align: center;
        }
        .vale-title {
            font-weight: bold;
            text-decoration: underline;
            margin: 5px 0 10px 0;
            font-size: 16px;
            text-align: center;
        }
        .vale-field {
            font-weight: bold;
            margin-top: 3px;
            text-align: left;
            font-size: 12px;
        }
        .vale-value {
            margin-bottom: 8px;
            text-align: left;
            font-size: 12px;
        }
        .firmas {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        .firma {
            width: 45%;
        }
        .firma-line {
            border-top: 1px solid #000;
            width: 100%;
            margin-top: 25px;
        }
        .encabezado {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .datos-egreso {
            margin-bottom: 10px;
        }
        .monto-fecha {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 8px;
        }
        .monto-numero {
            font-weight: bold;
            font-size: 14px;
        }
        button {
            margin: 10px;
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        @media print {
            button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="vale-container" id="vale-content">
        <div class="vale-header">Plantulas Agrodex S.C. de P de R.L. de C.V.</div>
        <div class="vale-title">VALE PROVISIONAL DE CAJA</div>

        <div class="encabezado">
            <div>
                <div class="vale-field">SUCURSAL:</div>
                <div class="vale-value"><?= $datos['invernadero'] ?></div>
            </div>
            <div>
                <div class="vale-field">FOLIO:</div>
                <div class="vale-value"><?= $datos['folio'] ?></div>
            </div>
        </div>

        <div class="datos-egreso">
            <div class="monto-fecha">
                <div>
                    <div class="vale-field">MONTO:</div>
                    <div class="monto-numero">$<?= number_format($datos['monto'], 2) ?></div>
                </div>
                <div>
                    <div class="vale-field">FECHA:</div>
                    <div class="vale-value"><?= date('d/m/y', strtotime($datos['fecha'])) ?></div>
                </div>
            </div>
            
            <div class="vale-field">IMPORTE CON LETRA:</div>
            <div class="vale-value" id="importe-letras"><?= formatoCantidadLetras($datos['monto']) ?></div>
            
            <div class="vale-field">CONCEPTO:</div>
            <div class="vale-value"><?= $datos['concepto'] ?></div>
        </div>

        <div class="firmas">
            <div class="firma">
                <div class="firma-line"></div>
                <div class="vale-field">AUTORIZADO POR:</div>
                <div id="autorizado-por"><?= $datos['autorizado_por'] ?></div>
            </div>

            <div class="firma">
                <div class="firma-line"></div>
                <div class="vale-field">RECIBIDO POR:</div>
                <div id="recibido-por"><?= $datos['recibido_por'] ?></div>
            </div>
        </div>
    </div>

    <button onclick="solicitarAutorizacionYGenerarPDF()">Generar PDF</button>
    <button onclick="window.print()">Imprimir Vale</button>
    <button onclick="window.close()">Cerrar</button>

    <script>
        // Configurar jsPDF
        const { jsPDF } = window.jspdf;
        
        function solicitarAutorizacionYGenerarPDF() {
            // Solicitar nombre de quien autoriza mediante prompt
            const autorizadoPor = prompt("Ingrese el nombre de quien autoriza el vale:", 
                                        document.getElementById('autorizado-por').textContent || "Responsable de Finanzas");
            
            if (autorizadoPor === null) return; // El usuario canceló
            
            // Actualizar el valor en pantalla
            document.getElementById('autorizado-por').textContent = autorizadoPor;
            
            // Generar el PDF
            generarPDF(autorizadoPor);
        }
        
        function generarPDF(autorizadoPor) {
            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: [80, 150] // Tamaño similar a un vale físico
            });

            // Agregar contenido al PDF
            doc.setFontSize(12);
            doc.text('Plantulas Agrodex S.C. de P de R.L. de C.V.', 40, 10, { align: 'center' });
            
            doc.setFontSize(14);
            doc.text('VALE PROVISIONAL DE CAJA', 40, 18, { align: 'center' });
            doc.setDrawColor(0);
            doc.setLineWidth(0.1);
            doc.line(10, 20, 70, 20);
            
            doc.setFontSize(10);
            let y = 28;
            
            // Encabezado con sucursal y folio
            doc.setFont(undefined, 'bold');
            doc.text('SUCURSAL:', 10, y);
            doc.text('FOLIO:', 50, y);
            doc.setFont(undefined, 'normal');
            doc.text('<?= addslashes($datos['invernadero']) ?>', 25, y);
            doc.text('<?= addslashes($datos['folio']) ?>', 60, y);
            y += 6;
            
            // Monto y fecha en la misma línea
            doc.setFont(undefined, 'bold');
            doc.text('MONTO:', 10, y);
            doc.text('FECHA:', 50, y);
            doc.setFont(undefined, 'normal');
            doc.text('$<?= number_format($datos['monto'], 2) ?>', 25, y);
            doc.text('<?= date('d/m/y', strtotime($datos['fecha'])) ?>', 60, y);
            y += 6;
            
            // Importe con letra
            doc.setFont(undefined, 'bold');
            doc.text('IMPORTE CON LETRA:', 10, y);
            doc.setFont(undefined, 'normal');
            const importeText = '<?= addslashes(formatoCantidadLetras($datos['monto'])) ?>';
            doc.text(importeText, 10, y + 3, { maxWidth: 70 });
            y += 12;
            
            // Concepto
            doc.setFont(undefined, 'bold');
            doc.text('CONCEPTO:', 10, y);
            doc.setFont(undefined, 'normal');
            doc.text('<?= addslashes($datos['concepto']) ?>', 30, y);
            y += 10;
            
            // Firmas
            doc.line(10, y, 40, y); // Línea para firma izquierda
            doc.line(50, y, 80, y); // Línea para firma derecha
            y += 5;
            
            doc.setFont(undefined, 'bold');
            doc.text('AUTORIZADO POR:', 10, y);
            doc.text('RECIBIDO POR:', 50, y);
            y += 4;
            
            doc.setFont(undefined, 'normal');
            doc.text(autorizadoPor, 10, y);
            doc.text('<?= addslashes($datos['recibido_por']) ?>', 50, y);
            y += 10;
            
            // Guardar el PDF
            doc.save('vale_provisional_<?= $datos['folio'] ?>.pdf');
        }

        // Auto-solicitar autorización al cargar (opcional)
        window.onload = function() {
            solicitarAutorizacionYGenerarPDF();
        };
    </script>
</body>
</html>