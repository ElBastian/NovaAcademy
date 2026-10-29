<?php
session_start();
require "conexion.php";
require "sesion.php";

requiereSesion();
if (!in_array($_SESSION["rol"], ["ponente", "director", "admin"], true)) {
    header("Location: " . paginaRol($_SESSION["rol"]));
    exit();
}

$con   = conexion();
$id    = (int) ($_GET["id"] ?? 0);
$curso = buscarCurso($con, $id);

if (!$curso || ($_SESSION["rol"] === "ponente" && !puedeEditarCurso($curso))) {
    header("Location: " . paginaRol($_SESSION["rol"]));
    exit();
}

$editar   = puedeEditarCurso($curso);
$comentar = puedeComentarCurso();
$esDueno  = $_SESSION["rol"] === "ponente";
$volver   = "curso.php?id=$id";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    revisarToken();
    $accion = $_POST["accion"] ?? "";
    $msg    = "";
    $ancla  = "";
    $titulo = trim($_POST["titulo"] ?? "");

    $bloqueId = 0;
    if (isset($_POST["bloque_id"])) {
        $q = $con->prepare("SELECT id FROM bloques WHERE id = ? AND curso_id = ?");
        $q->execute([(int) $_POST["bloque_id"], $id]);
        $bloqueId = (int) $q->fetchColumn();
    }
    $tarea = null;
    if (isset($_POST["tarea_id"])) {
        $q = $con->prepare("SELECT t.* FROM tareas t JOIN bloques b ON b.id = t.bloque_id WHERE t.id = ? AND b.curso_id = ?");
        $q->execute([(int) $_POST["tarea_id"], $id]);
        $tarea = $q->fetch();
    }

    if ($editar) {
        switch ($accion) {
            case "guardar_datos":
                $descripcion = trim($_POST["descripcion"] ?? "");
                $categoria   = $_POST["categoria"] ?? "";
                if ($titulo === "" || !in_array($categoria, categoriasCursos(), true)) {
                    $msg = "Escribe un título y elige una categoría.";
                    break;
                }
                $con->prepare("UPDATE cursos SET titulo = ?, descripcion = ?, categoria = ? WHERE id = ?")
                    ->execute([$titulo, $descripcion, $categoria, $id]);
                if ($descripcion !== (string) $curso["descripcion"]) {
                    registrarCambio($con, "Cambió la descripción del curso \"$titulo\": \""
                        . ($curso["descripcion"] ?: "(vacía)") . "\" → \"" . ($descripcion ?: "(vacía)") . "\".",
                        $id, (string) $curso["descripcion"]);
                }
                if ($titulo !== $curso["titulo"] || $categoria !== $curso["categoria"]) {
                    registrarCambio($con, "Cambió los datos del curso \"{$curso["titulo"]}\" → \"$titulo\" ($categoria).", $id);
                }
                $msg = "Datos del curso guardados.";
                break;

            case "nuevo_bloque":
                if ($titulo === "") { $msg = "Escribe el nombre del bloque."; break; }
                $con->prepare("INSERT INTO bloques (curso_id, titulo, orden)
                               VALUES (?, ?, (SELECT COALESCE(MAX(orden), 0) + 1 FROM bloques WHERE curso_id = ?))")
                    ->execute([$id, $titulo, $id]);
                registrarCambio($con, "Agregó el bloque \"$titulo\" al curso \"{$curso["titulo"]}\".", $id);
                $msg   = "Bloque agregado.";
                $ancla = "#bloques";
                break;

            case "editar_bloque":
                if (!$bloqueId || $titulo === "") break;
                $con->prepare("UPDATE bloques SET titulo = ? WHERE id = ?")->execute([$titulo, $bloqueId]);
                $msg   = "Bloque actualizado.";
                $ancla = "#bloque-$bloqueId";
                break;

            case "mover_bloque":
                if (!$bloqueId) break;
                $ids = $con->prepare("SELECT id FROM bloques WHERE curso_id = ? ORDER BY orden, id");
                $ids->execute([$id]);
                $ids = $ids->fetchAll(PDO::FETCH_COLUMN);
                $i   = array_search($bloqueId, array_map("intval", $ids), true);
                $j   = ($_POST["direccion"] ?? "") === "arriba" ? $i - 1 : $i + 1;
                if ($i !== false && isset($ids[$j])) {
                    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                    $orden = $con->prepare("UPDATE bloques SET orden = ? WHERE id = ?");
                    foreach ($ids as $pos => $bid) $orden->execute([$pos + 1, $bid]);
                }
                $ancla = "#bloque-$bloqueId";
                break;

            case "eliminar_bloque":
                if (!$bloqueId) break;
                $q = $con->prepare("DELETE FROM bloques WHERE id = ? RETURNING titulo");
                $q->execute([$bloqueId]);
                registrarCambio($con, "Eliminó el bloque \"" . $q->fetchColumn() . "\" del curso \"{$curso["titulo"]}\".", $id);
                $msg   = "Bloque eliminado.";
                $ancla = "#bloques";
                break;

            case "nueva_tarea":
                if (!$bloqueId || $titulo === "") { $msg = "Escribe el título de la tarea."; break; }
                $con->prepare("INSERT INTO tareas (bloque_id, titulo, instrucciones) VALUES (?, ?, ?)")
                    ->execute([$bloqueId, $titulo, trim($_POST["instrucciones"] ?? "")]);
                registrarCambio($con, "Subió la tarea \"$titulo\" al curso \"{$curso["titulo"]}\".", $id);
                $msg   = "Tarea agregada.";
                $ancla = "#bloque-$bloqueId";
                break;

            case "editar_tarea":
                if (!$tarea || $titulo === "") break;
                $con->prepare("UPDATE tareas SET titulo = ?, instrucciones = ? WHERE id = ?")
                    ->execute([$titulo, trim($_POST["instrucciones"] ?? ""), $tarea["id"]]);
                registrarCambio($con, "Modificó la tarea \"$titulo\" del curso \"{$curso["titulo"]}\".", $id);
                $msg   = "Tarea actualizada.";
                $ancla = "#tarea-{$tarea["id"]}";
                break;

            case "eliminar_tarea":
                if (!$tarea) break;
                $con->prepare("DELETE FROM tareas WHERE id = ?")->execute([$tarea["id"]]);
                registrarCambio($con, "Eliminó la tarea \"{$tarea["titulo"]}\" del curso \"{$curso["titulo"]}\".", $id);
                $msg   = "Tarea eliminada.";
                $ancla = "#bloque-{$tarea["bloque_id"]}";
                break;

            case "nueva_pregunta":
                $texto    = trim($_POST["texto"] ?? "");
                $opciones = [];
                foreach (["a", "b", "c", "d"] as $l) {
                    $opciones[$l] = mb_substr(trim($_POST["opcion_$l"] ?? ""), 0, 255);
                }
                $correcta = $_POST["correcta"] ?? "";
                $ancla    = "#examen";
                if ($texto === "" || $opciones["a"] === "" || $opciones["b"] === "") {
                    $msg = "Escribe la pregunta y al menos las opciones A y B.";
                    break;
                }
                if (!isset($opciones[$correcta]) || $opciones[$correcta] === "") {
                    $msg = "La respuesta correcta tiene que ser una opción con texto.";
                    break;
                }
                $con->prepare("INSERT INTO examenes (curso_id) VALUES (?) ON CONFLICT (curso_id) DO NOTHING")->execute([$id]);
                $con->prepare("INSERT INTO preguntas (curso_id, texto, opcion_a, opcion_b, opcion_c, opcion_d, correcta)
                               VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$id, $texto, $opciones["a"], $opciones["b"],
                               $opciones["c"] ?: null, $opciones["d"] ?: null, $correcta]);
                registrarCambio($con, "Agregó una pregunta al examen del curso \"{$curso["titulo"]}\".", $id);
                $msg = "Pregunta agregada al examen.";
                break;

            case "eliminar_pregunta":
                $con->prepare("DELETE FROM preguntas WHERE id = ? AND curso_id = ?")->execute([(int) ($_POST["pregunta_id"] ?? 0), $id]);
                registrarCambio($con, "Eliminó una pregunta del examen del curso \"{$curso["titulo"]}\".", $id);
                $msg   = "Pregunta eliminada.";
                $ancla = "#examen";
                break;
        }
    }

    $comentarioId = (int) ($_POST["comentario_id"] ?? 0);
    if ($accion === "comentar" && $comentar) {
        $texto = trim($_POST["texto"] ?? "");
        if ($texto !== "") {
            $con->prepare("INSERT INTO comentarios_curso (curso_id, autor_id, autor, rol, texto) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id, $_SESSION["usuario_id"], autorActual($con), $_SESSION["rol"], $texto]);
            $msg = "Comentario enviado al ponente.";
        }
        $ancla = "#comentarios";
    }
    if ($accion === "atender_comentario" && $editar) {
        $con->prepare("UPDATE comentarios_curso SET atendido = NOT atendido WHERE id = ? AND curso_id = ?")->execute([$comentarioId, $id]);
        $ancla = "#comentarios";
    }
    if ($accion === "eliminar_comentario") {
        $con->prepare("DELETE FROM comentarios_curso WHERE id = ? AND curso_id = ? AND (autor_id = ? OR ? = 'admin')")
            ->execute([$comentarioId, $id, $_SESSION["usuario_id"], $_SESSION["rol"]]);
        $ancla = "#comentarios";
    }

    $_SESSION["mensaje"] = $msg;
    header("Location: $volver$ancla");
    exit();
}

$mensaje = $_SESSION["mensaje"] ?? "";
unset($_SESSION["mensaje"]);
$token = e(tokenFormulario());

$bloques    = bloquesCurso($con, $id);
$preguntas  = preguntasExamen($con, $id);
$totalTareas = array_sum(array_map(fn($b) => count($b["tareas"]), $bloques));

$q = $con->prepare("SELECT * FROM comentarios_curso WHERE curso_id = ? ORDER BY atendido, creado_en DESC");
$q->execute([$id]);
$comentarios = $q->fetchAll();
$pendientes  = count(array_filter($comentarios, fn($c) => !$c["atendido"]));

$q = $con->prepare("SELECT e.tarea_id, COUNT(*) FROM entregas e JOIN tareas t ON t.id = e.tarea_id
                    JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = ? GROUP BY e.tarea_id");
$q->execute([$id]);
$entregasPorTarea = $q->fetchAll(PDO::FETCH_KEY_PAIR);

$q = $con->prepare("SELECT u.nombre, u.apellidos, i.completado_en,
                           (SELECT COUNT(*) FROM entregas e JOIN tareas t ON t.id = e.tarea_id JOIN bloques b ON b.id = t.bloque_id
                             WHERE b.curso_id = i.curso_id AND e.alumno_id = u.id) AS entregadas,
                           (SELECT MAX(calificacion) FROM intentos_examen x WHERE x.curso_id = i.curso_id AND x.alumno_id = u.id) AS mejor,
                           (SELECT COUNT(*) FROM intentos_examen x WHERE x.curso_id = i.curso_id AND x.alumno_id = u.id) AS intentos
                    FROM inscripciones i JOIN usuarios u ON u.id = i.alumno_id
                    WHERE i.curso_id = ? ORDER BY u.nombre");
$q->execute([$id]);
$alumnos = $q->fetchAll();

$entregas = [];
if ($editar) {
    $q = $con->prepare("SELECT e.tarea_id, e.respuesta, e.entregado_en, u.nombre, u.apellidos
                        FROM entregas e JOIN usuarios u ON u.id = e.alumno_id
                        JOIN tareas t ON t.id = e.tarea_id JOIN bloques b ON b.id = t.bloque_id
                        WHERE b.curso_id = ? ORDER BY e.entregado_en DESC");
    $q->execute([$id]);
    foreach ($q->fetchAll() as $en) $entregas[$en["tarea_id"]][] = $en;
}

$temas    = ["ponente" => "tema-ponente", "director" => "tema-director", "admin" => "tema-admin"];
$etiqueta = ["ponente" => "Ponente", "director" => "Director", "admin" => "Super admin"][$_SESSION["rol"]];
$letras   = ["a" => "A", "b" => "B", "c" => "C", "d" => "D"];

function formTarea($token, $bloqueId, $tarea = null) {
    ?>
    <form method="post" class="form-contenido">
        <input type="hidden" name="token" value="<?= $token ?>">
        <?php if ($tarea): ?>
            <input type="hidden" name="accion" value="editar_tarea">
            <input type="hidden" name="tarea_id" value="<?= $tarea["id"] ?>">
        <?php else: ?>
            <input type="hidden" name="accion" value="nueva_tarea">
            <input type="hidden" name="bloque_id" value="<?= $bloqueId ?>">
        <?php endif; ?>
        <label class="campo"><span>Título de la tarea</span>
            <input type="text" name="titulo" maxlength="150" required value="<?= e($tarea["titulo"] ?? "") ?>"></label>
        <label class="campo"><span>Instrucciones</span>
            <textarea name="instrucciones" rows="4"><?= e($tarea["instrucciones"] ?? "") ?></textarea></label>
        <button class="btn btn-primario registro-boton"><?= $tarea ? "Guardar tarea" : "Subir tarea" ?></button>
    </form>
    <?php
}
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
<body class="<?= $temas[$_SESSION["rol"]] ?>">

<?php navbarPanel($etiqueta); ?>

<main class="curso-pagina">
    <a href="<?= paginaRol($_SESSION["rol"]) . ($_SESSION["rol"] === "admin" ? "?seccion=cursos" : "") ?>" class="volver">← Volver al panel</a>

    <div class="curso-cabecera">
        <span class="chip-categoria"><?= e($curso["categoria"]) ?></span>
        <h1><?= e($curso["titulo"]) ?></h1>
        <p class="registro-sub">
            <?= $curso["nombre"] ? "Ponente: " . e($curso["nombre"] . " " . $curso["apellidos"]) : "Sin ponente asignado" ?>
            · <?= count($bloques) ?> <?= count($bloques) === 1 ? "bloque" : "bloques" ?>
            · <?= $totalTareas ?> <?= $totalTareas === 1 ? "tarea" : "tareas" ?>
            · <?= count($preguntas) ?> <?= count($preguntas) === 1 ? "pregunta" : "preguntas" ?> en el examen
        </p>
        <?php if (!$editar): ?>
            <p class="registro-sub">Como director puedes revisar el curso y dejarle comentarios al ponente.</p>
        <?php elseif ($_SESSION["rol"] === "admin"): ?>
            <p class="registro-sub">Como super admin puedes modificar el curso y dejarle comentarios al ponente.</p>
        <?php endif; ?>
    </div>

    <?php if ($mensaje): ?>
        <p class="registro-aviso"><?= e($mensaje) ?></p>
    <?php endif; ?>

    <section class="admin-tarjeta" id="comentarios">
        <h2 class="bloque-titulo">Comentarios para el ponente
            <?php if ($pendientes): ?><span class="admin-contador"><?= $pendientes ?></span><?php endif; ?></h2>

        <?php if ($comentar): ?>
            <form method="post" class="form-contenido">
                <input type="hidden" name="token" value="<?= $token ?>">
                <input type="hidden" name="accion" value="comentar">
                <label class="campo"><span>Nuevo comentario</span>
                    <textarea name="texto" rows="3" required placeholder="¿Qué debería cambiar el ponente en este curso?"></textarea></label>
                <button class="btn btn-primario registro-boton">Enviar comentario</button>
            </form>
        <?php endif; ?>

        <?php if (!$comentarios): ?>
            <p class="admin-vacio">No hay comentarios para este curso.</p>
        <?php else: ?>
            <ul class="comentarios">
                <?php foreach ($comentarios as $c): ?>
                    <li class="comentario <?= $c["atendido"] ? "atendido" : "" ?>">
                        <div class="comentario-cabecera">
                            <b><?= e($c["autor"]) ?></b>
                            <span class="etiqueta-rol"><?= $c["rol"] === "admin" ? "Super admin" : e(ucfirst($c["rol"])) ?></span>
                            <span class="comentario-fecha"><?= e(date("d/m/Y H:i", strtotime($c["creado_en"]))) ?></span>
                            <?php if ($c["atendido"]): ?><span class="etiqueta-ok">Atendido</span><?php endif; ?>
                        </div>
                        <p><?= nl2br(e($c["texto"])) ?></p>
                        <div class="admin-acciones izquierda">
                            <form method="post">
                                <input type="hidden" name="token" value="<?= $token ?>">
                                <input type="hidden" name="comentario_id" value="<?= $c["id"] ?>">
                                <?php if ($editar): ?>
                                    <button name="accion" value="atender_comentario" class="btn-mini exito">
                                        <?= $c["atendido"] ? "Marcar como pendiente" : "Marcar como atendido" ?></button>
                                <?php endif; ?>
                                <?php if ((int) $c["autor_id"] === (int) $_SESSION["usuario_id"] || $_SESSION["rol"] === "admin"): ?>
                                    <button name="accion" value="eliminar_comentario" class="btn-mini peligro"
                                            onclick="return confirm('¿Eliminar este comentario?')">Eliminar</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($editar): ?>
        <details class="admin-tarjeta desplegable">
            <summary>Editar datos del curso</summary>
            <form method="post" class="form-contenido">
                <input type="hidden" name="token" value="<?= $token ?>">
                <input type="hidden" name="accion" value="guardar_datos">
                <div class="campos-doble">
                    <label class="campo"><span>Título</span>
                        <input type="text" name="titulo" maxlength="150" required value="<?= e($curso["titulo"]) ?>"></label>
                    <label class="campo"><span>Categoría</span>
                        <select name="categoria" required>
                            <?php foreach (categoriasCursos() as $cat): ?>
                                <option <?= $cat === $curso["categoria"] ? "selected" : "" ?>><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                </div>
                <label class="campo"><span>Descripción</span>
                    <textarea name="descripcion" rows="3"><?= e($curso["descripcion"]) ?></textarea></label>
                <button class="btn btn-primario registro-boton">Guardar datos</button>
            </form>
        </details>
    <?php elseif ($curso["descripcion"]): ?>
        <p class="curso-descripcion"><?= nl2br(e($curso["descripcion"])) ?></p>
    <?php endif; ?>

    <h2 class="admin-subtitulo" id="bloques">Bloques y tareas</h2>
    <p class="registro-sub">Los alumnos entregan cada tarea y, al terminar todas, presentan el examen final.</p>

    <?php if (!$bloques): ?>
        <p class="panel-vacio">Este curso todavía no tiene bloques.</p>
    <?php endif; ?>

    <?php $n = 0; foreach ($bloques as $b): $n++; ?>
        <section class="admin-tarjeta bloque" id="bloque-<?= $b["id"] ?>">
            <div class="bloque-encabezado">
                <h3 class="bloque-titulo"><span class="bloque-num">Bloque <?= $n ?></span> <?= e($b["titulo"]) ?></h3>
                <?php if ($editar): ?>
                    <form method="post" class="admin-acciones">
                        <input type="hidden" name="token" value="<?= $token ?>">
                        <input type="hidden" name="bloque_id" value="<?= $b["id"] ?>">
                        <input type="hidden" name="accion" value="mover_bloque">
                        <?php if ($n > 1): ?><button name="direccion" value="arriba" class="btn-mini" title="Subir">↑</button><?php endif; ?>
                        <?php if ($n < count($bloques)): ?><button name="direccion" value="abajo" class="btn-mini" title="Bajar">↓</button><?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!$b["tareas"]): ?>
                <p class="admin-vacio">Este bloque no tiene tareas.</p>
            <?php endif; ?>

            <?php foreach ($b["tareas"] as $t): ?>
                <article class="tarea" id="tarea-<?= $t["id"] ?>">
                    <div class="tarea-cabecera">
                        <h4><?= e($t["titulo"]) ?></h4>
                        <span class="tarea-entregas"><?= (int) ($entregasPorTarea[$t["id"]] ?? 0) ?> de <?= count($alumnos) ?> entregas</span>
                    </div>
                    <?php if ($t["instrucciones"]): ?>
                        <p class="tarea-texto"><?= nl2br(e($t["instrucciones"])) ?></p>
                    <?php endif; ?>

                    <?php if ($editar): ?>
                        <?php if (!empty($entregas[$t["id"]])): ?>
                            <details class="desplegable-mini">
                                <summary>Ver entregas (<?= count($entregas[$t["id"]]) ?>)</summary>
                                <ul class="entregas">
                                    <?php foreach ($entregas[$t["id"]] as $en): ?>
                                        <li>
                                            <b><?= e($en["nombre"] . " " . $en["apellidos"]) ?></b>
                                            <span class="comentario-fecha"><?= e(date("d/m/Y H:i", strtotime($en["entregado_en"]))) ?></span>
                                            <p><?= nl2br(e($en["respuesta"])) ?></p>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                        <details class="desplegable-mini">
                            <summary>Editar tarea</summary>
                            <?php formTarea($token, $b["id"], $t); ?>
                        </details>
                        <form method="post" onsubmit="return confirm('¿Eliminar esta tarea y las entregas de los alumnos?')">
                            <input type="hidden" name="token" value="<?= $token ?>">
                            <input type="hidden" name="accion" value="eliminar_tarea">
                            <input type="hidden" name="tarea_id" value="<?= $t["id"] ?>">
                            <button class="btn-mini peligro">Eliminar tarea</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if ($editar): ?>
                <details class="desplegable-mini nueva">
                    <summary>+ Subir tarea a este bloque</summary>
                    <?php formTarea($token, $b["id"]); ?>
                </details>
                <details class="desplegable-mini">
                    <summary>Renombrar o eliminar bloque</summary>
                    <form method="post" class="form-contenido fila">
                        <input type="hidden" name="token" value="<?= $token ?>">
                        <input type="hidden" name="accion" value="editar_bloque">
                        <input type="hidden" name="bloque_id" value="<?= $b["id"] ?>">
                        <label class="campo"><span>Nombre del bloque</span>
                            <input type="text" name="titulo" maxlength="150" required value="<?= e($b["titulo"]) ?>"></label>
                        <button class="btn btn-primario registro-boton">Guardar</button>
                    </form>
                    <form method="post" onsubmit="return confirm('¿Eliminar el bloque con todas sus tareas y entregas?')">
                        <input type="hidden" name="token" value="<?= $token ?>">
                        <input type="hidden" name="accion" value="eliminar_bloque">
                        <input type="hidden" name="bloque_id" value="<?= $b["id"] ?>">
                        <button class="btn-mini peligro">Eliminar bloque</button>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <?php if ($editar): ?>
        <form method="post" class="admin-tarjeta form-contenido fila">
            <input type="hidden" name="token" value="<?= $token ?>">
            <input type="hidden" name="accion" value="nuevo_bloque">
            <label class="campo"><span>Nuevo bloque</span>
                <input type="text" name="titulo" maxlength="150" required placeholder="Ej. Bloque 1: Introducción"></label>
            <button class="btn btn-primario registro-boton">Agregar bloque</button>
        </form>
    <?php endif; ?>

    <h2 class="admin-subtitulo" id="examen">Examen final</h2>
    <p class="registro-sub">
        El alumno lo presenta al terminar todas las tareas. No hay calificación mínima: al presentarlo completa el curso
        y puede reintentarlo las veces que quiera.
    </p>

    <section class="admin-tarjeta">
        <?php if (!$preguntas): ?>
            <p class="admin-vacio">El examen todavía no tiene preguntas. Sin examen, los alumnos no pueden completar el curso.</p>
        <?php else: ?>
            <ol class="preguntas">
                <?php foreach ($preguntas as $p): ?>
                    <li>
                        <p class="pregunta-texto"><?= nl2br(e($p["texto"])) ?></p>
                        <ul class="opciones">
                            <?php foreach (opcionesPregunta($p) as $l => $op): ?>
                                <li class="<?= $l === $p["correcta"] ? "correcta" : "" ?>">
                                    <b><?= $letras[$l] ?>.</b> <?= e($op) ?>
                                    <?php if ($l === $p["correcta"]): ?><span class="etiqueta-ok">Correcta</span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($editar): ?>
                            <form method="post" onsubmit="return confirm('¿Eliminar esta pregunta?')">
                                <input type="hidden" name="token" value="<?= $token ?>">
                                <input type="hidden" name="accion" value="eliminar_pregunta">
                                <input type="hidden" name="pregunta_id" value="<?= $p["id"] ?>">
                                <button class="btn-mini peligro">Eliminar pregunta</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($editar): ?>
            <details class="desplegable-mini nueva" <?= !$preguntas ? "open" : "" ?>>
                <summary>+ Agregar pregunta</summary>
                <form method="post" class="form-contenido">
                    <input type="hidden" name="token" value="<?= $token ?>">
                    <input type="hidden" name="accion" value="nueva_pregunta">
                    <label class="campo"><span>Pregunta</span>
                        <textarea name="texto" rows="2" required></textarea></label>
                    <div class="campos-doble">
                        <?php foreach ($letras as $l => $L): ?>
                            <label class="campo"><span>Opción <?= $L ?><?= in_array($l, ["c", "d"]) ? " (opcional)" : "" ?></span>
                                <input type="text" name="opcion_<?= $l ?>" maxlength="255" <?= in_array($l, ["a", "b"]) ? "required" : "" ?>></label>
                        <?php endforeach; ?>
                    </div>
                    <label class="campo"><span>Respuesta correcta</span>
                        <select name="correcta" required>
                            <?php foreach ($letras as $l => $L): ?><option value="<?= $l ?>">Opción <?= $L ?></option><?php endforeach; ?>
                        </select></label>
                    <button class="btn btn-primario registro-boton">Agregar pregunta</button>
                </form>
            </details>
        <?php endif; ?>
    </section>

    <h2 class="admin-subtitulo">Alumnos inscritos</h2>
    <div class="admin-tarjeta">
        <?php if (!$alumnos): ?>
            <p class="admin-vacio">Todavía no hay alumnos inscritos.</p>
        <?php else: ?>
            <table class="admin-tabla">
                <thead><tr><th>Alumno</th><th>Tareas entregadas</th><th>Intentos de examen</th><th>Mejor calificación</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($alumnos as $a): ?>
                    <tr>
                        <td><?= e($a["nombre"] . " " . $a["apellidos"]) ?></td>
                        <td><?= (int) $a["entregadas"] ?> / <?= $totalTareas ?></td>
                        <td><?= (int) $a["intentos"] ?></td>
                        <td><?= $a["mejor"] !== null ? (int) $a["mejor"] : "—" ?></td>
                        <td><?= $a["completado_en"]
                                ? '<span class="etiqueta-ok">Completado</span>'
                                : '<span class="comentario-fecha">En curso</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php piePanel(); ?>

</body>
</html>
