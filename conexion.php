<?php

function conexion() {
    $host = "localhost";
    $usuario = "bastian";
    $password = "admin1234";   
    $baseDatos = "escuela";

    try {
        $conexion = new mysqli($host, $usuario, $password, $baseDatos);
        $conexion->set_charset("utf8mb4");
        return $conexion;
    } catch (mysqli_sql_exception $e) {
        error_log("Error de conexión: " . $e->getMessage());
        http_response_code(500);
        die("No se pudo conectar a la base de datos. Intenta más tarde.");
    }
}

// $host = "localhost";
// $usuario = "bastian";
// $password = "admin1234";   
// $baseDatos = "escuela";

// try {
//     $conexion = new mysqli($host, $usuario, $password, $baseDatos);
//     $conexion->set_charset("utf8mb4");
// } catch (mysqli_sql_exception $e) {

//     error_log("Error de conexión: " . $e->getMessage());
//     http_response_code(500);
//     die("No se pudo conectar a la base de datos. Intenta más tarde.");
// }
