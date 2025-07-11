<?php
function numeros_a_letras($numero) {
    $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
    $decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    $especiales = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
    $veintis = ['veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];

    $entero = (int)$numero;
    $decimal = round(($numero - $entero) * 100);
    $letras = '';

    if ($entero == 0) {
        $letras = 'cero';
    } elseif ($entero < 10) {
        $letras = $unidades[$entero];
    } elseif ($entero < 20) {
        $letras = $especiales[$entero - 10];
    } elseif ($entero < 30) {
        $letras = $veintis[$entero - 20];
    } elseif ($entero < 100) {
        $letras = $decenas[(int)($entero / 10)];
        if ($entero % 10 != 0) {
            $letras .= ' y ' . $unidades[$entero % 10];
        }
    } elseif ($entero < 1000) {
        $centenas = (int)($entero / 100);
        $resto = $entero % 100;
        
        $letras = ($centenas == 1 ? 'cien' : $unidades[$centenas] . 'cientos');
        if ($resto != 0) {
            $letras .= ' ' . numeros_a_letras($resto);
        }
    } else {
        $millares = (int)($entero / 1000);
        $resto = $entero % 1000;
        
        $letras = ($millares == 1 ? 'mil' : numeros_a_letras($millares) . ' mil');
        if ($resto != 0) {
            $letras .= ' ' . numeros_a_letras($resto);
        }
    }

    // Agregar decimales
    if ($decimal > 0) {
        $letras .= ' punto ' . numeros_a_letras($decimal);
    }

    return ucfirst($letras) . ' pesos';
}