<?php

function validarCUIT($cuit) {
    // Elimina guiones y espacios
    $cuitLimpio = str_replace(['-', ' '], '', $cuit);
    
    // Valida que tenga exactamente 11 dígitos
    if (!preg_match('/^[0-9]{11}$/', $cuitLimpio)) {
        return [
            "valido" => false,
            "mensaje" => "El CUIT debe contener exactamente 11 dígitos",
            "cuit_normalizado" => null
        ];
    }
    
    // Separa en partes
    $tipo = (int)substr($cuitLimpio, 0, 2);
    $verificador = (int)substr($cuitLimpio, 10, 1);
    
    // Valida tipo de CUIT
    $tiposValidos = [20, 23, 24, 27, 30, 33, 34];
    if (!in_array($tipo, $tiposValidos)) {
        return [
            "valido" => false,
            "mensaje" => "Tipo de CUIT inválido. Debe comenzar con: 20, 23, 24, 27, 30, 33 o 34",
            "cuit_normalizado" => null
        ];
    }
    
    // Calcula dígito verificador
    $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    
    for ($i = 0; $i < 10; $i++) {
        $suma += (int)$cuitLimpio[$i] * $multiplicadores[$i];
    }
    
    $resto = $suma % 11;
    
    // Determinar dígito esperado
    if ($resto == 0) {
        $digitoEsperado = 0;
    } elseif ($resto == 1) {
        // Caso especial
        if ($tipo == 20) {
            $digitoEsperado = 9;
        } elseif ($tipo == 27) {
            $digitoEsperado = 4;
        } else {
            return [
                "valido" => false,
                "mensaje" => "CUIT inválido. El dígito verificador no puede calcularse para este tipo con resto 1",
                "cuit_normalizado" => null
            ];
        }
    } else {
        $digitoEsperado = 11 - $resto;
    }
    
    // Validar dígito verificador
    if ($verificador !== $digitoEsperado) {
        return [
            "valido" => false,
            "mensaje" => "Dígito verificador incorrecto. Esperado: {$digitoEsperado}, Recibido: {$verificador}",
            "cuit_normalizado" => null
        ];
    }
    
    return [
        "valido" => true,
        "mensaje" => "CUIT válido",
        "cuit_normalizado" => $cuitLimpio
    ];
}
?>