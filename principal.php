<?php
session_start();
require "sesion.php";

$categorias = [
    ["nombre" => "Programación", "texto" => "Desarrolla tus ideas"],
    ["nombre" => "Negocios",     "texto" => "Lidera el cambio"],
    ["nombre" => "Diseño",       "texto" => "Da vida a tu creatividad"],
    ["nombre" => "Tecnología",   "texto" => "El futuro es digital"],
    ["nombre" => "Idiomas",      "texto" => "Conecta con el mundo"],
    ["nombre" => "Y más",        "texto" => "Descubre todo lo que puedes aprender"],
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio Nova Academy</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="principal.css">
</head>
<body>

<header class="navbar">
    <div class="navbar-contenido">
        <a href="principal.php" class="logo">
            <img src="imagenes/3.png" alt="" class="logo-icono">
            <img src="imagenes/2.png" alt="Nova Academy" class="logo-texto">
        </a>

        <nav class="menu">
            <a href="principal.php" class="activo">Inicio</a>
            <a href="#acerca">¿Cómo funciona?</a>
            <a href="#contacto">Contacto</a>
        </nav>

        <div class="acciones-nav">
            <?php if (isset($_SESSION["usuario_id"])): ?>
                <a href="<?= paginaRol($_SESSION["rol"]) ?>" class="btn btn-primario">Mi panel</a>
                <a href="logout.php" class="btn btn-borde">Cerrar sesión</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-borde">Iniciar sesión</a>
                <a href="registro.php" class="btn btn-primario">Registrarse</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<section class="hero">
    <div class="hero-contenido">
        <p class="hero-etiqueta">Tu futuro empieza aquí</p>
        <h1>Aprende hoy,<br><span>construye mañana</span></h1>
        <p class="hero-texto">
            Accede a cursos online, desde donde quieras y a tu propio ritmo.
            Desarrolla nuevas habilidades, impulsa tu carrera y alcanza tus metas.
        </p>
    </div>
</section>

<main>
    <section class="categorias" id="aprender">
        <div class="que-es">
            <h3>¿Qué es Nova Academy?</h3>
            <p>Nova Academy es una página web enfocada en ayudar a las personas a aprender.
            Reunimos cursos de distintas áreas para que cualquiera pueda estudiar gratis,
            a su propio ritmo y desde cualquier dispositivo. Nuestro objetivo es que aprender
            sea fácil, accesible y que puedas compartir lo que logras.</p>
        </div>
        <h2>Puedes aprender</h2>
        <div class="grid-categorias">
            <?php foreach ($categorias as $cat): ?>
                <div class="categoria">
                    <strong><?= htmlspecialchars($cat["nombre"]) ?></strong>
                    <small><?= htmlspecialchars($cat["texto"]) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="como-funciona" id="acerca">
        <h2>¿Cómo funciona?</h2>
        <div class="grid-pasos">
            <div class="paso">
                <span class="paso-numero">1</span>
                <strong>Regístrate</strong>
                <small>Crea tu cuenta gratis en menos de un minuto.</small>
            </div>
            <div class="paso">
                <span class="paso-numero">2</span>
                <strong>Elige un curso</strong>
                <small>Explora las categorías y encuentra lo que te interesa.</small>
            </div>
            <div class="paso">
                <span class="paso-numero">3</span>
                <strong>Aprende a tu ritmo</strong>
                <small>Estudia cuando quieras, desde cualquier dispositivo.</small>
            </div>
        </div>
    </section>

    <section class="contacto" id="contacto">
        <h2>Contacto</h2>
        <p>¿Tienes dudas? Llámanos o escríbenos por WhatsApp.</p>
        <a href="tel:+524435827619" class="contacto-numero">+52 443 376 3309</a>
    </section>
</main>

<footer class="pie">
    <img src="imagenes/3.png" alt="" class="pie-icono">
    <p>© <?= date("Y") ?> Nova Academy. Todos los derechos reservados por Bastian :p.</p>
</footer>

</body>
</html>
