<?php
session_start();
require "conexion.php";
require "sesion.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: " . paginaRol($_SESSION["rol"]));
    exit();
}

$roles = [
    "alumno"   => ["titulo" => "Alumno",   "texto" => "Quiero tomar cursos y aprender."],
    "ponente"  => ["titulo" => "Ponente",  "texto" => "Quiero impartir cursos y compartir lo que sé. (Requiere aprobación)"],
    "director" => ["titulo" => "Director", "texto" => "Quiero administrar cursos, ponentes y alumnos. (Requiere aprobación)"],
];

$errores = [];
$datos = ["nombre" => "", "apellidos" => "", "correo" => "", "rol" => "alumno"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $datos["nombre"]    = trim($_POST["nombre"] ?? "");
    $datos["apellidos"] = trim($_POST["apellidos"] ?? "");
    $datos["correo"]    = trim($_POST["correo"] ?? "");
    $datos["rol"]       = $_POST["rol"] ?? "";
    $password           = $_POST["password"] ?? "";
    $confirmar          = $_POST["confirmar"] ?? "";

    if ($datos["nombre"] === "")    $errores[] = "Escribe tu nombre.";
    if ($datos["apellidos"] === "") $errores[] = "Escribe tus apellidos.";
    if (!filter_var($datos["correo"], FILTER_VALIDATE_EMAIL)) $errores[] = "El correo no es válido.";
    if (!array_key_exists($datos["rol"], $roles)) $errores[] = "Elige cómo te quieres registrar.";
    if (strlen($password) < 8)      $errores[] = "La contraseña debe tener al menos 8 caracteres.";
    if ($password !== $confirmar)   $errores[] = "Las contraseñas no coinciden.";

    if (!$errores) {
        $con = conexion();

        $buscar = $con->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $buscar->execute([$datos["correo"]]);

        if ($buscar->fetch()) {
            $errores[] = "Ya existe una cuenta con ese correo.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $estado = in_array($datos["rol"], ["director", "ponente"], true) ? "pendiente" : "activo";
            $insertar = $con->prepare(
                "INSERT INTO usuarios (nombre, apellidos, correo, password, rol, estado)
                 VALUES (?, ?, ?, ?, ?, ?) RETURNING id"
            );
            $insertar->execute([$datos["nombre"], $datos["apellidos"], $datos["correo"], $hash, $datos["rol"], $estado]);
            $nuevoId = $insertar->fetchColumn();

            if ($estado === "pendiente") {
                header("Location: login.php?solicitud=" . $datos["rol"]);
                exit();
            }

            session_regenerate_id(true);
            $_SESSION["usuario_id"] = $nuevoId;
            $_SESSION["nombre"]     = $datos["nombre"];
            $_SESSION["rol"]        = $datos["rol"];

            header("Location: " . paginaRol($datos["rol"]));
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse | Nova Academy</title>
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
            <a href="login.php" class="btn btn-borde">Iniciar sesión</a>
            <a href="registro.php" class="btn btn-primario">Registrarse</a>
        </div>
    </div>
</header>

<main class="registro registro-fondo">
    <div class="registro-tarjeta">
        <h1>Crea tu cuenta</h1>
        <p class="registro-sub">Es gratis y te toma menos de un minuto.</p>

        <?php if ($errores): ?>
            <ul class="registro-errores">
                <?php foreach ($errores as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" novalidate>
            <fieldset class="roles">
                <legend>¿Cómo te quieres registrar?</legend>
                <div class="grid-roles">
                    <?php foreach ($roles as $clave => $rol): ?>
                        <label class="rol">
                            <input type="radio" name="rol" value="<?= $clave ?>"
                                <?= $datos["rol"] === $clave ? "checked" : "" ?> required>
                            <span class="rol-caja">
                                <strong><?= $rol["titulo"] ?></strong>
                                <small><?= $rol["texto"] ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="campos-doble">
                <label class="campo">
                    <span>Nombre</span>
                    <input type="text" name="nombre" maxlength="80" required
                           value="<?= htmlspecialchars($datos["nombre"]) ?>">
                </label>
                <label class="campo">
                    <span>Apellidos</span>
                    <input type="text" name="apellidos" maxlength="120" required
                           value="<?= htmlspecialchars($datos["apellidos"]) ?>">
                </label>
            </div>

            <label class="campo">
                <span>Correo electrónico</span>
                <input type="email" name="correo" maxlength="150" required autocomplete="email"
                       value="<?= htmlspecialchars($datos["correo"]) ?>">
            </label>

            <div class="campos-doble">
                <label class="campo">
                    <span>Contraseña</span>
                    <input type="password" name="password" minlength="8" required autocomplete="new-password">
                </label>
                <label class="campo">
                    <span>Confirmar contraseña</span>
                    <input type="password" name="confirmar" minlength="8" required autocomplete="new-password">
                </label>
            </div>

            <button type="submit" class="btn btn-primario registro-boton">Crear cuenta</button>
        </form>

        <p class="registro-pie">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
    </div>
</main>

<footer class="pie">
    <img src="imagenes/3.png" alt="" class="pie-icono">
    <p>© <?= date("Y") ?> Nova Academy. Todos los derechos reservados por Bastian :p.</p>
</footer>

</body>
</html>
