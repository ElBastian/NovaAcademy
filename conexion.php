<?php

function conexion() {
    // Render da DATABASE_URL (postgres://usuario:pass@host:puerto/db).
    // Si no existe, se usan DB_* o los valores locales por defecto.
    $url = getenv("DATABASE_URL");
    if ($url) {
        $p = parse_url($url);
        $host      = $p["host"];
        $puerto    = $p["port"] ?? "5432";
        $usuario   = urldecode($p["user"]);
        $password  = urldecode($p["pass"] ?? "");
        $baseDatos = ltrim($p["path"], "/");
    } else {
        $host      = getenv("DB_HOST")     ?: "localhost";
        $puerto    = getenv("DB_PORT")     ?: "5432";
        $usuario   = getenv("DB_USER")     ?: "postgres";
        $password  = getenv("DB_PASSWORD") ?: "admin";
        $baseDatos = getenv("DB_NAME")     ?: "Nova Academy";
    }
    $sslmode = getenv("DB_SSLMODE") ?: "prefer";

    try {
        $conexion = new PDO(
            "pgsql:host=$host;port=$puerto;dbname='$baseDatos';sslmode=$sslmode",
            $usuario,
            $password,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $conexion->exec("SET client_encoding TO 'UTF8'");
        return $conexion;
    } catch (PDOException $e) {
        error_log("Error de conexión: " . $e->getMessage());
        http_response_code(500);
        die("No se pudo conectar a la base de datos. Intenta más tarde.");
    }
}
