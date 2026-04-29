<?php
// Este archivo guarda la lógica original para encriptar contraseñas 
// y migrarlas a hashes seguros si en algún momento deseas reactivarlo.

/*
$clave = (string)$userData['us_clave'];

// Lógica de ProteoERP: Si la clave no es un hash, se hashea y se actualiza en la DB
$info = password_get_info($clave);
if ($info['algo'] === null) {
    $claveHash = password_hash($clave, PASSWORD_DEFAULT);
    // $updateStmt = $pdo->prepare("UPDATE usuario SET us_clave = ? WHERE us_codigo = ?");
    // $updateStmt->execute([$claveHash, $userData['us_codigo']]);
    $clave = $claveHash;
}

// Verificación de la clave usando password_verify
if (password_verify($pass, $clave)) {
    $valid = true;
}
*/
