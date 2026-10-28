<?php
include_once "conexion.php";

$token = $_GET["token"] ?? "";
$verificado = false;

if ($token !== "") {
    $conexion = conexion();
    $sql = "SELECT id FROM usuarios WHERE token_verificacion = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if ($usuario) {
        $sql = "UPDATE usuarios
                SET email_verificado = 1, token_verificacion = NULL
                WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $usuario["id"]);
        $stmt->execute();
        $verificado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificar correo</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="contenedor angosto">
        <?php if ($verificado) { ?>
            <h1>Email confirmado</h1>
            <p>Tu cuenta ya está activa.</p>
            <div class="acciones">
                <a href="login.php">Iniciar sesión</a>
            </div>
        <?php } else { ?>
            <h1>Token inválido</h1>
            <p class="mensaje">El enlace no es válido o ya fue usado.</p>
        <?php } ?>
    </main>
</body>
</html>