<?php
session_start();
require "conexion.php";
require "sesion.php";

requiereRol("alumno");

$tuyos = ($_GET["vista"] ?? "") === "tuyos";

$con = conexion();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    revisarToken();
    $cursoId = (int) ($_POST["curso_id"] ?? 0);
    if (($_POST["accion"] ?? "") === "inscribirse" && buscarCurso($con, $cursoId)) {
        $con->prepare("INSERT INTO inscripciones (alumno_id, curso_id) VALUES (?, ?) ON CONFLICT DO NOTHING")
            ->execute([$_SESSION["usuario_id"], $cursoId]);
        header("Location: curso_alumno.php?id=$cursoId");
        exit();
    }
    header("Location: estudiante.php");
    exit();
}

$token = e(tokenFormulario());

$sql = "SELECT c.id, c.titulo, c.descripcion, c.categoria, u.nombre, u.apellidos,
               i.alumno_id IS NOT NULL AS inscrito, i.completado_en
        FROM cursos c LEFT JOIN usuarios u ON u.id = c.ponente_id
        LEFT JOIN inscripciones i ON i.curso_id = c.id AND i.alumno_id = ?
        WHERE c.eliminado_en IS NULL";
$q = $con->prepare($tuyos ? "$sql AND i.alumno_id IS NOT NULL ORDER BY i.inscrito_en DESC"
                          : "$sql ORDER BY c.creado_en DESC");
$q->execute([$_SESSION["usuario_id"]]);
$cursos = $q->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi panel | Nova Academy</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="principal.css">
    <link rel="stylesheet" href="paneles.css">
</head>
<body class="tema-estudiante">

<?php navbarPanel("Estudiante"); ?>

<section class="panel-hero">
    <div class="hero-contenido">
        <p class="hero-etiqueta">Panel del estudiante</p>
        <h1>Hola, <?= e($_SESSION["nombre"]) ?>.<br><span>¿Qué aprendemos hoy?</span></h1>
        <p class="hero-texto">Explora los cursos disponibles y aprende a tu propio ritmo.</p>
    </div>
</section>

<main>
    <section class="panel-seccion panel-final" id="cursos">
        <div class="seccion-encabezado">
            <div>
                <h2>Cursos disponibles</h2>
                <p><?= count($cursos) ?> <?= count($cursos) === 1 ? "curso" : "cursos" ?><?= $tuyos ? (count($cursos) === 1 ? " inscrito" : " inscritos") : "" ?></p>
            </div>
        </div>

        <nav class="filtros">
            <a href="estudiante.php#cursos" class="<?= !$tuyos ? "activo" : "" ?>">Todos</a>
            <a href="?vista=tuyos#cursos" class="<?= $tuyos ? "activo" : "" ?>">Tuyos</a>
        </nav>

        <?php if (!$cursos): ?>
            <p class="panel-vacio"><?= $tuyos ? "Aún no estás inscrito en ningún curso." : "Todavía no hay cursos. Vuelve pronto." ?></p>
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
                            <p class="instructor">
                                <?= $c["nombre"] ? "Ponente: " . e($c["nombre"] . " " . $c["apellidos"]) : "Ponente por asignar" ?>
                            </p>
                            <div class="curso-accion">
                                <?php if ($c["completado_en"]): ?>
                                    <span class="etiqueta-ok">🎓 Completado</span>
                                    <a href="curso_alumno.php?id=<?= $c["id"] ?>" class="btn btn-borde">Ver curso</a>
                                <?php elseif ($c["inscrito"]): ?>
                                    <a href="curso_alumno.php?id=<?= $c["id"] ?>" class="btn btn-primario">Continuar curso</a>
                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="token" value="<?= $token ?>">
                                        <input type="hidden" name="accion" value="inscribirse">
                                        <input type="hidden" name="curso_id" value="<?= $c["id"] ?>">
                                        <button class="btn btn-primario registro-boton">Inscribirme</button>
                                    </form>
                                <?php endif; ?>
                            </div>
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
