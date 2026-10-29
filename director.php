<?php
session_start();
require "conexion.php";
require "sesion.php";

requiereRol("director");

if (empty($_SESSION["token"])) {
    $_SESSION["token"] = bin2hex(random_bytes(32));
}

$con = conexion();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["token"], $_POST["token"] ?? "")) {
        http_response_code(400);
        die("Solicitud no válida.");
    }

    if (($_POST["accion"] ?? "") === "crear_curso") {
        $_SESSION["mensaje_curso"] = crearCurso($con, null);
        header("Location: director.php#nuevo-curso");
        exit();
    }

    if (($_POST["accion"] ?? "") === "eliminar_curso") {
        $_SESSION["mensaje_curso"] = eliminarCurso($con, (int) ($_POST["id"] ?? 0));
        header("Location: director.php#nuevo-curso");
        exit();
    }

    $id     = (int) ($_POST["id"] ?? 0);
    $estados = ["aceptar" => "activo", "rechazar" => "rechazado"];
    $accion = $_POST["accion"] ?? "";

    if (isset($estados[$accion])) {
        $q = $con->prepare("UPDATE usuarios SET estado = ? WHERE id = ? AND rol = 'ponente' AND estado = 'pendiente'
                            RETURNING nombre || ' ' || apellidos");
        $q->execute([$estados[$accion], $id]);
        if ($nombre = $q->fetchColumn()) {
            registrarCambio($con, ($accion === "aceptar" ? "Aceptó" : "Rechazó") . " la solicitud del ponente $nombre.");
        }
        $_SESSION["mensaje"] = $accion === "aceptar" ? "Ponente aceptado." : "Solicitud rechazada.";
    }

    header("Location: director.php");
    exit();
}

$mensaje = $_SESSION["mensaje"] ?? "";
$mensajeCurso = $_SESSION["mensaje_curso"] ?? "";
unset($_SESSION["mensaje"], $_SESSION["mensaje_curso"]);

$solicitudes = $con->query("SELECT id, nombre, apellidos, correo, creado_en FROM usuarios
                            WHERE rol = 'ponente' AND estado = 'pendiente' ORDER BY creado_en")->fetchAll();
$token = e($_SESSION["token"]);

$totales = $con->query("SELECT
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'alumno')  AS alumnos,
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'ponente' AND estado = 'activo') AS ponentes,
        (SELECT COUNT(*) FROM cursos WHERE eliminado_en IS NULL) AS cursos,
        (SELECT COUNT(*) FROM cursos WHERE eliminado_en IS NULL AND ponente_id IS NULL) AS sin_ponente")->fetch();

$cursos = $con->query("SELECT c.id, c.titulo, c.categoria, u.nombre, u.apellidos,
                              (SELECT COUNT(*) FROM comentarios_curso k WHERE k.curso_id = c.id AND NOT k.atendido) AS comentarios
                       FROM cursos c LEFT JOIN usuarios u ON u.id = c.ponente_id
                       WHERE c.eliminado_en IS NULL
                       ORDER BY c.creado_en DESC")->fetchAll();

$ponentes = $con->query("SELECT u.nombre, u.apellidos, COUNT(c.id) AS total
                         FROM usuarios u LEFT JOIN cursos c ON c.ponente_id = u.id AND c.eliminado_en IS NULL
                         WHERE u.rol = 'ponente' AND u.estado = 'activo'
                         GROUP BY u.id ORDER BY total DESC, u.nombre")->fetchAll();

$cambiosPonentes = $con->query("SELECT autor, rol, descripcion, creado_en FROM cambios
                                WHERE rol = 'ponente' ORDER BY creado_en DESC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del director | Nova Academy</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="principal.css">
    <link rel="stylesheet" href="registro.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="paneles.css">
</head>
<body class="tema-director">

<?php navbarPanel("Director"); ?>

<section class="panel-hero">
    <div class="hero-contenido">
        <p class="hero-etiqueta">Panel del director</p>
        <h1>Hola, <?= e($_SESSION["nombre"]) ?>.<br><span>Así va Nova Academy</span></h1>
        <p class="hero-texto">Crea cursos y revisa los ponentes y los alumnos de la plataforma.</p>
    </div>
</section>

<main>
    <section class="panel-seccion">
        <div class="seccion-encabezado">
            <h2>Solicitudes de ponentes
                <?php if ($solicitudes): ?><span class="admin-contador"><?= count($solicitudes) ?></span><?php endif; ?>
            </h2>
        </div>

        <?php if ($mensaje): ?>
            <p class="registro-aviso"><?= e($mensaje) ?></p>
        <?php endif; ?>

        <?php if (!$solicitudes): ?>
            <p class="panel-vacio">No hay solicitudes pendientes.</p>
        <?php else: ?>
            <div class="panel-tarjeta">
                <table class="panel-tabla">
                    <thead><tr><th>Nombre</th><th>Correo</th><th>Registro</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($solicitudes as $s): ?>
                        <tr>
                            <td><?= e($s["nombre"] . " " . $s["apellidos"]) ?></td>
                            <td><?= e($s["correo"]) ?></td>
                            <td><?= e(date("d/m/Y", strtotime($s["creado_en"]))) ?></td>
                            <td class="admin-acciones">
                                <form method="post">
                                    <input type="hidden" name="token" value="<?= $token ?>">
                                    <input type="hidden" name="id" value="<?= $s["id"] ?>">
                                    <button name="accion" value="aceptar" class="btn-mini exito">Aceptar</button>
                                    <button name="accion" value="rechazar" class="btn-mini peligro">Rechazar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel-seccion">
        <div class="grid-stats">
            <div class="stat"><b><?= (int) $totales["alumnos"] ?></b><span>Alumnos</span></div>
            <div class="stat"><b><?= (int) $totales["ponentes"] ?></b><span>Ponentes</span></div>
            <div class="stat"><b><?= (int) $totales["cursos"] ?></b><span>Cursos</span></div>
            <div class="stat"><b><?= (int) $totales["sin_ponente"] ?></b><span>Cursos sin ponente</span></div>
        </div>
    </section>

    <section class="panel-seccion" id="nuevo-curso">
        <?php if ($mensajeCurso): ?>
            <p class="registro-aviso"><?= e($mensajeCurso) ?></p>
        <?php endif; ?>
        <?php formNuevoCurso($token); ?>
    </section>

    <div class="grid-doble">
        <section class="panel-seccion">
            <div class="seccion-encabezado" id="cursos"><h2>Cursos</h2></div>
            <?php if (!$cursos): ?>
                <p class="panel-vacio">Todavía no hay cursos.</p>
            <?php else: ?>
                <div class="panel-tarjeta">
                    <table class="panel-tabla">
                        <thead><tr><th>Curso</th><th>Categoría</th><th>Ponente</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($cursos as $c): ?>
                            <tr>
                                <td><?= e($c["titulo"]) ?></td>
                                <td><?= e($c["categoria"]) ?></td>
                                <td><?= $c["nombre"]
                                        ? e($c["nombre"] . " " . $c["apellidos"])
                                        : '<span class="sin-asignar">Sin asignar</span>' ?></td>
                                <td class="admin-acciones">
                                    <a href="curso.php?id=<?= $c["id"] ?>" class="btn-mini">Revisar y comentar
                                        <?php if ($c["comentarios"]): ?><span class="admin-contador"><?= (int) $c["comentarios"] ?></span><?php endif; ?></a>
                                    <form method="post" onsubmit="return confirm('¿Eliminar este curso? Solo el administrador podrá recuperarlo.')">
                                        <input type="hidden" name="token" value="<?= $token ?>">
                                        <input type="hidden" name="accion" value="eliminar_curso">
                                        <input type="hidden" name="id" value="<?= $c["id"] ?>">
                                        <button class="btn-mini peligro">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel-seccion">
            <div class="seccion-encabezado"><h2>Ponentes</h2></div>
            <?php if (!$ponentes): ?>
                <p class="panel-vacio">Todavía no hay ponentes registrados.</p>
            <?php else: ?>
                <div class="panel-tarjeta">
                    <table class="panel-tabla">
                        <thead><tr><th>Nombre</th><th>Cursos</th></tr></thead>
                        <tbody>
                        <?php foreach ($ponentes as $p): ?>
                            <tr>
                                <td><?= e($p["nombre"] . " " . $p["apellidos"]) ?></td>
                                <td><?= (int) $p["total"] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="panel-seccion panel-final">
        <div class="seccion-encabezado">
            <div>
                <h2>Cambios de ponentes</h2>
                <p>Lo que los ponentes han modificado en sus cursos.</p>
            </div>
        </div>
        <?php tablaCambios($cambiosPonentes, false); ?>
    </section>
</main>

<?php piePanel(); ?>

</body>
</html>
