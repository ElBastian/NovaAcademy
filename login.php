<?php
session_start();
require "conexion.php";
require "sesion.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: " . paginaRol($_SESSION["rol"]));
    exit();
}

$errores = [];
$avisos = [
    "director" => "Tu solicitud como director fue enviada. Podrás iniciar sesión cuando el administrador la acepte.",
    "ponente"  => "Tu solicitud como ponente fue enviada. Podrás iniciar sesión cuando un director o el administrador la acepte.",
];
$aviso = $avisos[$_GET["solicitud"] ?? ""] ?? "";
$correo = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo   = trim($_POST["correo"] ?? "");
    $password = $_POST["password"] ?? "";

    $con = conexion();
    $buscar = $con->prepare("SELECT id, nombre, password, rol, estado FROM usuarios WHERE correo = ?");
    $buscar->execute([$correo]);
    $usuario = $buscar->fetch();

    if (!$usuario || !password_verify($password, $usuario["password"])) {
        $errores[] = "Correo o contraseña incorrectos.";
    } elseif ($usuario["estado"] === "pendiente") {
        $errores[] = "Tu solicitud como " . $usuario["rol"] . " aún no ha sido aceptada.";
    } elseif ($usuario["estado"] === "rechazado") {
        $errores[] = "Tu solicitud como " . $usuario["rol"] . " fue rechazada.";
    } else {
        session_regenerate_id(true);
        $_SESSION["usuario_id"] = $usuario["id"];
        $_SESSION["nombre"]     = $usuario["nombre"];
        $_SESSION["rol"]        = $usuario["rol"];

        header("Location: " . paginaRol($usuario["rol"]));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Nova Academy</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="principal.css">
    <link rel="stylesheet" href="registro.css">
</head>
<body>

<header class="navbar">
    <div class="navbar-contenido">
        <a href="principal.php" class="logo">
            <img src="imagenes/3.png" alt="" class="logo-icono">
            <img src="imagenes/2.png" alt="Nova Academy" class="logo-texto">
        </a>

        <nav class="menu">
            <a href="principal.php">Inicio</a>
            <a href="principal.php#acerca">¿Cómo funciona?</a>
            <a href="principal.php#contacto">Contacto</a>
        </nav>

        <div class="acciones-nav">
            <a href="registro.php" class="btn btn-primario">Registrarse</a>
        </div>
    </div>
</header>

<main class="registro login-fondo">
    <div class="registro-tarjeta" style="max-width: 460px">
        <h1>Iniciar sesión</h1>
        <p class="registro-sub">Bienvenido de nuevo a Nova Academy.</p>

        <?php if ($aviso): ?>
            <p class="registro-aviso"><?= htmlspecialchars($aviso) ?></p>
        <?php endif; ?>

        <?php if ($errores): ?>
            <ul class="registro-errores">
                <?php foreach ($errores as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" novalidate>
            <label class="campo">
                <span>Correo electrónico</span>
                <input type="email" name="correo" maxlength="150" required autocomplete="email"
                       value="<?= htmlspecialchars($correo) ?>">
            </label>
            <label class="campo">
                <span>Contraseña</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="btn btn-primario registro-boton">Entrar</button>
        </form>

        <p class="registro-pie">¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
    </div>
</main>

<footer class="pie">
    <img src="imagenes/3.png" alt="" class="pie-icono">
    <p>© <?= date("Y") ?> Nova Academy. Todos los derechos reservados por Bastian :p.</p>
</footer>

</body>
</html>
