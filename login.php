<?php
session_start();

if (isset($_SESSION["usuario_id"])) {
    header("Location: dashboard.php");
    exit();
}

include_once "conexion.php";

$mensaje = "";
$enlaceVerificacion = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $conexion = conexion();

    $sql = "SELECT id, nombre, password, email_verificado, token_verificacion
            FROM usuarios WHERE email = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario || !password_verify($password, $usuario["password"])) {
        $mensaje = "Email o contraseña incorrectos.";
    } elseif (!$usuario["email_verificado"]) {
        $token = $usuario["token_verificacion"];

        if (empty($token)) {
            $token = bin2hex(random_bytes(32));
            $sql = "UPDATE usuarios SET token_verificacion = ? WHERE id = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("si", $token, $usuario["id"]);
            $stmt->execute();
        }

        $mensaje = "Primero confirma tu email.";
        $enlaceVerificacion = "verificar.php?token=" . urlencode($token);
    } else {
        session_regenerate_id(true);
        $_SESSION["usuario_id"] = $usuario["id"];
        $_SESSION["nombre"] = $usuario["nombre"];
        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="contenedor angosto">
        <h1>Iniciar sesión</h1>
        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Contraseña</label>
            <input type="password" name="password" required>
            <button type="submit">Entrar</button>
        </form>

        <p class="mensaje"><?php echo htmlspecialchars($mensaje); ?></p>

        <?php if ($enlaceVerificacion !== "") { ?>
            <p>
                <a href="<?php echo htmlspecialchars($enlaceVerificacion); ?>">Confirmar correo ahora</a>
            </p>
        <?php } ?>

        <div class="acciones">
            <a href="registro.php">Crear cuenta</a>
        </div>
    </main>
</body>
</html>