<?php
session_start();
require "conexion.php";
require "sesion.php";

requiereRol("ponente");

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
        $_SESSION["mensaje"] = crearCurso($con, $_SESSION["usuario_id"]);
        header("Location: ponente.php");
        exit();
    }

    if (($_POST["accion"] ?? "") === "eliminar_curso") {
        $_SESSION["mensaje"] = eliminarCurso($con, (int) ($_POST["id"] ?? 0), $_SESSION["usuario_id"]);
        header("Location: ponente.php");
        exit();
    }

    $id          = (int) ($_POST["id"] ?? 0);
    $descripcion = trim($_POST["descripcion"] ?? "");

    $q = $con->prepare("SELECT titulo, descripcion FROM cursos WHERE id = ? AND ponente_id = ? AND eliminado_en IS NULL");
    $q->execute([$id, $_SESSION["usuario_id"]]);
    $curso = $q->fetch();

    if ($curso && $descripcion !== (string) $curso["descripcion"]) {
        $con->prepare("UPDATE cursos SET descripcion = ? WHERE id = ?")->execute([$descripcion, $id]);
        registrarCambio($con, "Cambió la descripción del curso \"{$curso["titulo"]}\": \""
            . ($curso["descripcion"] ?: "(vacía)") . "\" → \"" . ($descripcion ?: "(vacía)") . "\".",
            $id, (string) $curso["descripcion"]);
        $_SESSION["mensaje"] = "Descripción actualizada.";
    }

    header("Location: ponente.php");
    exit();
}

$mensaje = $_SESSION["mensaje"] ?? "";
unset($_SESSION["mensaje"]);
$token = e($_SESSION["token"]);

$q = $con->prepare("SELECT c.id, c.titulo, c.descripcion, c.categoria, c.creado_en,
                           (SELECT COUNT(*) FROM bloques b WHERE b.curso_id = c.id) AS bloques,
                           (SELECT COUNT(*) FROM preguntas p WHERE p.curso_id = c.id) AS preguntas,
                           (SELECT COUNT(*) FROM comentarios_curso k WHERE k.curso_id = c.id AND NOT k.atendido) AS comentarios
                    FROM cursos c
                    WHERE c.ponente_id = ? AND c.eliminado_en IS NULL ORDER BY c.creado_en DESC");
$q->execute([$_SESSION["usuario_id"]]);
$cursos = $q->fetchAll();

$q = $con->prepare("SELECT k.curso_id, k.autor, k.rol, k.texto, k.creado_en, c.titulo
                    FROM comentarios_curso k JOIN cursos c ON c.id = k.curso_id
                    WHERE c.ponente_id = ? AND c.eliminado_en IS NULL AND NOT k.atendido
                    ORDER BY k.creado_en DESC");
$q->execute([$_SESSION["usuario_id"]]);
$comentarios = $q->fetchAll();

$totalCategorias = count(array_unique(array_column($cursos, "categoria")));
$totalPlataforma = (int) $con->query("SELECT COUNT(*) FROM cursos WHERE eliminado_en IS NULL")->fetchColumn();
$totalAlumnos    = (int) $con->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'alumno'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del ponente | Nova Academy</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="principal.css">
    <link rel="stylesheet" href="registro.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="paneles.css">
</head>
<body class="tema-ponente">

<?php navbarPanel("Ponente"); ?>

<section class="panel-hero">
    <div class="hero-contenido">
        <p class="hero-etiqueta">Panel del ponente</p>
        <h1>Hola, <?= e($_SESSION["nombre"]) ?>.<br><span>Comparte lo que sabes</span></h1>
        <p class="hero-texto">Crea tus cursos, sube tareas por bloques y un examen final para que tus alumnos lo completen.</p>
    </div>
</section>

<main>
    <section class="panel-seccion">
        <div class="grid-stats">
            <div class="stat"><b><?= count($cursos) ?></b><span>Cursos asignados</span></div>
            <div class="stat"><b><?= $totalCategorias ?></b><span>Categorías que impartes</span></div>
            <div class="stat"><b><?= $totalAlumnos ?></b><span>Alumnos en la plataforma</span></div>
            <div class="stat"><b><?= $totalPlataforma ?></b><span>Cursos en Nova Academy</span></div>
        </div>
    </section>

    <?php if ($comentarios): ?>
    <section class="panel-seccion">
        <div class="seccion-encabezado">
            <div>
                <h2>Comentarios por atender <span class="admin-contador"><?= count($comentarios) ?></span></h2>
                <p>Lo que los directores y el administrador te piden cambiar en tus cursos.</p>
            </div>
        </div>
        <ul class="comentarios">
            <?php foreach ($comentarios as $k): ?>
                <li class="comentario">
                    <div class="comentario-cabecera">
                        <b><?= e($k["autor"]) ?></b>
                        <span class="etiqueta-rol"><?= $k["rol"] === "admin" ? "Super admin" : e(ucfirst($k["rol"])) ?></span>
                        <span class="comentario-fecha">en <a href="curso.php?id=<?= $k["curso_id"] ?>#comentarios"><?= e($k["titulo"]) ?></a>
                            · <?= e(date("d/m/Y H:i", strtotime($k["creado_en"]))) ?></span>
                    </div>
                    <p><?= nl2br(e($k["texto"])) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="panel-seccion panel-final">
        <div class="seccion-encabezado">
            <div>
                <h2>Mis cursos</h2>
                <p>Cursos en los que eres el ponente.</p>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <p class="registro-aviso"><?= e($mensaje) ?></p>
        <?php endif; ?>

        <?php formNuevoCurso($token); ?>

        <?php if (!$cursos): ?>
            <p class="panel-vacio">Aún no tienes cursos.<br>
                Crea uno con el formulario de arriba o espera a que te asignen uno.</p>
        <?php else: ?>
            <div class="grid-cursos">
                <?php foreach ($cursos as $c): ?>
                    <article class="curso">
                        <div class="curso-portada <?= colorCategoria($c["categoria"]) ?>">
                            <span class="chip"><?= e($c["categoria"]) ?></span>
                        </div>
                        <div class="curso-info">
                            <h3><?= e($c["titulo"]) ?></h3>
                            <?php if ($c["descripcion"]): ?>
                                <p class="curso-desc"><?= e($c["descripcion"]) ?></p>
                            <?php endif; ?>
                            <p class="curso-resumen">
                                <?= (int) $c["bloques"] ?> <?= $c["bloques"] == 1 ? "bloque" : "bloques" ?> ·
                                <?= (int) $c["preguntas"] ?> <?= $c["preguntas"] == 1 ? "pregunta" : "preguntas" ?> en el examen
                                <?php if ($c["comentarios"]): ?>
                                    · <span class="etiqueta"><?= (int) $c["comentarios"] ?> <?= $c["comentarios"] == 1 ? "comentario" : "comentarios" ?></span>
                                <?php endif; ?>
                            </p>
                            <a href="curso.php?id=<?= $c["id"] ?>" class="btn btn-primario curso-gestionar">Tareas, examen y comentarios</a>
                            <details class="curso-editar">
                                <summary>Editar descripción</summary>
                                <form method="post">
                                    <input type="hidden" name="token" value="<?= $token ?>">
                                    <input type="hidden" name="id" value="<?= $c["id"] ?>">
                                    <textarea name="descripcion" rows="4"><?= e($c["descripcion"]) ?></textarea>
                                    <button class="btn btn-primario">Guardar</button>
                                </form>
                            </details>
                            <form method="post" class="curso-eliminar"
                                  onsubmit="return confirm('¿Eliminar este curso? Solo el administrador podrá recuperarlo.')">
                                <input type="hidden" name="token" value="<?= $token ?>">
                                <input type="hidden" name="accion" value="eliminar_curso">
                                <input type="hidden" name="id" value="<?= $c["id"] ?>">
                                <button class="btn-mini peligro">Eliminar curso</button>
                            </form>
                            <p class="instructor">Creado el <?= e(date("d/m/Y", strtotime($c["creado_en"]))) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php piePanel(); ?>

</body>
</html>
