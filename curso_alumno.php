<?php
session_start();
require "conexion.php";
require "sesion.php";

requiereRol("alumno");

$con    = conexion();
$id     = (int) ($_GET["id"] ?? 0);
$curso  = buscarCurso($con, $id);
$alumno = $_SESSION["usuario_id"];

$q = $con->prepare("SELECT 1 FROM inscripciones WHERE alumno_id = ? AND curso_id = ?");
$q->execute([$alumno, $id]);
if (!$curso || !$q->fetchColumn()) {
    header("Location: estudiante.php");
    exit();
}

$volver = "curso_alumno.php?id=$id";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    revisarToken();
    $accion = $_POST["accion"] ?? "";
    $msg    = "";
    $ancla  = "";

    if ($accion === "entregar") {
        $tareaId   = (int) ($_POST["tarea_id"] ?? 0);
        $respuesta = trim($_POST["respuesta"] ?? "");
        $q = $con->prepare("SELECT 1 FROM tareas t JOIN bloques b ON b.id = t.bloque_id WHERE t.id = ? AND b.curso_id = ?");
        $q->execute([$tareaId, $id]);
        if ($q->fetchColumn() && $respuesta !== "") {
            $con->prepare("INSERT INTO entregas (alumno_id, tarea_id, respuesta) VALUES (?, ?, ?)
                           ON CONFLICT (alumno_id, tarea_id)
                           DO UPDATE SET respuesta = EXCLUDED.respuesta, entregado_en = CURRENT_TIMESTAMP")
                ->execute([$alumno, $tareaId, $respuesta]);
            $msg = "Tarea entregada.";
        } else {
            $msg = "Escribe tu respuesta antes de entregar.";
        }
        $ancla = "#tarea-$tareaId";
    }

    if ($accion === "presentar_examen") {
        $p = progresoAlumno($con, $alumno, $id);
        $ancla = "#examen";
        if (!$p["examen_listo"]) {
            $msg = "Primero entrega todas las tareas del curso.";
        } else {
            $preguntas  = preguntasExamen($con, $id);
            $respuestas = $_POST["respuestas"] ?? [];
            $aciertos   = 0;
            foreach ($preguntas as $preg) {
                if (($respuestas[$preg["id"]] ?? "") === $preg["correcta"]) $aciertos++;
            }
            $calificacion = (int) round($aciertos / count($preguntas) * 100);

            $con->prepare("INSERT INTO intentos_examen (alumno_id, curso_id, calificacion, aprobado) VALUES (?, ?, ?, true)")
                ->execute([$alumno, $id, $calificacion]);
            $con->prepare("UPDATE inscripciones SET completado_en = CURRENT_TIMESTAMP
                           WHERE alumno_id = ? AND curso_id = ? AND completado_en IS NULL")->execute([$alumno, $id]);
            $_SESSION["ultimo_examen"] = ["calificacion" => $calificacion, "aciertos" => $aciertos, "total" => count($preguntas)];
            $msg = "";
        }
    }

    $_SESSION["mensaje"] = $msg;
    header("Location: $volver$ancla");
    exit();
}

$mensaje = $_SESSION["mensaje"] ?? "";
$ultimo  = $_SESSION["ultimo_examen"] ?? null; 
unset($_SESSION["mensaje"], $_SESSION["ultimo_examen"]);
$token = e(tokenFormulario());

$bloques   = bloquesCurso($con, $id);
$preguntas = preguntasExamen($con, $id);
$progreso  = progresoAlumno($con, $alumno, $id);

$q = $con->prepare("SELECT e.tarea_id, e.respuesta, e.entregado_en FROM entregas e
                    JOIN tareas t ON t.id = e.tarea_id JOIN bloques b ON b.id = t.bloque_id
                    WHERE e.alumno_id = ? AND b.curso_id = ?");
$q->execute([$alumno, $id]);
$misEntregas = [];
foreach ($q->fetchAll() as $en) $misEntregas[$en["tarea_id"]] = $en;

$q = $con->prepare("SELECT calificacion, creado_en FROM intentos_examen
                    WHERE alumno_id = ? AND curso_id = ? ORDER BY creado_en DESC");
$q->execute([$alumno, $id]);
$intentos = $q->fetchAll();

$porcentaje = $progreso["tareas"] ? (int) round($progreso["entregadas"] / $progreso["tareas"] * 100) : 0;
$letras     = ["a" => "A", "b" => "B", "c" => "C", "d" => "D"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($curso["titulo"]) ?> | Nova Academy</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="principal.css">
    <link rel="stylesheet" href="registro.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="paneles.css">
</head>
<body class="tema-estudiante">

<?php navbarPanel("Estudiante"); ?>

<main class="curso-pagina">
    <a href="estudiante.php?vista=tuyos#cursos" class="volver">← Mis cursos</a>

    <div class="curso-cabecera">
        <span class="chip-categoria"><?= e($curso["categoria"]) ?></span>
        <h1><?= e($curso["titulo"]) ?></h1>
        <p class="registro-sub">
            <?= $curso["nombre"] ? "Ponente: " . e($curso["nombre"] . " " . $curso["apellidos"]) : "Ponente por asignar" ?>
        </p>
        <?php if ($curso["descripcion"]): ?>
            <p class="curso-descripcion"><?= nl2br(e($curso["descripcion"])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($progreso["completado_en"]): ?>
        <div class="curso-completado">
            <b>🎓 ¡Curso completado!</b>
            <span>Lo terminaste el <?= e(date("d/m/Y", strtotime($progreso["completado_en"]))) ?>
                con <?= (int) $progreso["mejor"] ?> de calificación.</span>
        </div>
    <?php else: ?>
        <div class="progreso">
            <div class="progreso-texto">
                <span>Tareas entregadas: <b><?= $progreso["entregadas"] ?> de <?= $progreso["tareas"] ?></b></span>
                <span><?= $porcentaje ?>%</span>
            </div>
            <div class="progreso-barra"><div style="width: <?= $porcentaje ?>%"></div></div>
        </div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <p class="registro-aviso"><?= e($mensaje) ?></p>
    <?php endif; ?>

    <?php if (!$bloques): ?>
        <p class="panel-vacio">El ponente todavía está preparando el contenido de este curso. Vuelve pronto.</p>
    <?php endif; ?>

    <?php $n = 0; foreach ($bloques as $b): $n++; ?>
        <section class="admin-tarjeta bloque">
            <h2 class="bloque-titulo"><span class="bloque-num">Bloque <?= $n ?></span> <?= e($b["titulo"]) ?></h2>

            <?php if (!$b["tareas"]): ?>
                <p class="admin-vacio">Este bloque todavía no tiene tareas.</p>
            <?php endif; ?>

            <?php foreach ($b["tareas"] as $t): $entrega = $misEntregas[$t["id"]] ?? null; ?>
                <article class="tarea" id="tarea-<?= $t["id"] ?>">
                    <div class="tarea-cabecera">
                        <h3><?= e($t["titulo"]) ?></h3>
                        <?= $entrega ? '<span class="etiqueta-ok">Entregada</span>' : '<span class="etiqueta">Pendiente</span>' ?>
                    </div>
                    <?php if ($t["instrucciones"]): ?>
                        <p class="tarea-texto"><?= nl2br(e($t["instrucciones"])) ?></p>
                    <?php endif; ?>

                    <details class="desplegable-mini" <?= !$entrega ? "open" : "" ?>>
                        <summary><?= $entrega ? "Ver o cambiar mi entrega" : "Entregar tarea" ?></summary>
                        <form method="post" class="form-contenido">
                            <input type="hidden" name="token" value="<?= $token ?>">
                            <input type="hidden" name="accion" value="entregar">
                            <input type="hidden" name="tarea_id" value="<?= $t["id"] ?>">
                            <label class="campo"><span>Tu respuesta</span>
                                <textarea name="respuesta" rows="4" required><?= e($entrega["respuesta"] ?? "") ?></textarea></label>
                            <?php if ($entrega): ?>
                                <p class="comentario-fecha">Entregada el <?= e(date("d/m/Y H:i", strtotime($entrega["entregado_en"]))) ?></p>
                            <?php endif; ?>
                            <button class="btn btn-primario registro-boton"><?= $entrega ? "Actualizar entrega" : "Entregar" ?></button>
                        </form>
                    </details>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>

    <h2 class="admin-subtitulo" id="examen">Examen final</h2>
    <section class="admin-tarjeta">
        <?php if (!$preguntas): ?>
            <p class="admin-vacio">El examen final todavía no está disponible.</p>
        <?php elseif (!$progreso["examen_listo"]): ?>
            <p class="admin-vacio">🔒 Entrega todas las tareas para desbloquear el examen
                (te faltan <?= $progreso["tareas"] - $progreso["entregadas"] ?>).</p>
        <?php else: ?>
            <?php if ($intentos): $actual = $intentos[0]; ?>
                <div class="examen-resultado">
                    <span class="examen-resultado-titulo"><?= $ultimo ? "Tu calificación" : "Tu última calificación" ?></span>
                    <b class="examen-resultado-nota"><?= (int) $actual["calificacion"] ?><small>/100</small></b>
                    <?php if ($ultimo): ?>
                        <span><?= $ultimo["aciertos"] ?> de <?= $ultimo["total"] ?> respuestas correctas</span>
                    <?php endif; ?>
                    <span class="comentario-fecha">
                        <?= count($intentos) ?> <?= count($intentos) === 1 ? "intento" : "intentos" ?>
                        · Mejor calificación: <?= (int) $progreso["mejor"] ?>
                    </span>
                </div>
            <?php endif; ?>

            <details class="desplegable-mini" <?= !$intentos ? "open" : "" ?>>
                <summary><?= $intentos ? "Reintentar examen" : "Presentar examen" ?></summary>
                <p class="registro-sub">
                    <?= count($preguntas) ?> <?= count($preguntas) === 1 ? "pregunta" : "preguntas" ?>.
                    Al enviarlo completas el curso; puedes reintentarlo las veces que quieras para mejorar tu calificación.
                </p>
                <form method="post" class="form-contenido" onsubmit="return confirm('¿Enviar tus respuestas?')">
                    <input type="hidden" name="token" value="<?= $token ?>">
                    <input type="hidden" name="accion" value="presentar_examen">
                    <ol class="preguntas">
                        <?php foreach ($preguntas as $p): ?>
                            <li>
                                <p class="pregunta-texto"><?= nl2br(e($p["texto"])) ?></p>
                                <div class="opciones">
                                    <?php foreach (opcionesPregunta($p) as $l => $op): ?>
                                        <label class="opcion">
                                            <input type="radio" name="respuestas[<?= $p["id"] ?>]" value="<?= $l ?>" required>
                                            <b><?= $letras[$l] ?>.</b> <?= e($op) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <button class="btn btn-primario registro-boton">Enviar examen</button>
                </form>
            </details>
        <?php endif; ?>

        <?php if ($intentos): ?>
            <h3 class="intentos-titulo">Tus intentos</h3>
            <table class="admin-tabla">
                <thead><tr><th>Intento</th><th>Fecha</th><th>Calificación</th></tr></thead>
                <tbody>
                <?php foreach ($intentos as $n => $i): ?>
                    <tr>
                        <td>#<?= count($intentos) - $n ?></td>
                        <td><?= e(date("d/m/Y H:i", strtotime($i["creado_en"]))) ?></td>
                        <td><?= (int) $i["calificacion"] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>

<?php piePanel(); ?>

</body>
</html>
