<?php
session_start();
require "conexion.php";
require "sesion.php";

if (($_SESSION["rol"] ?? "") !== "admin") {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION["token"])) {
    $_SESSION["token"] = bin2hex(random_bytes(32));
}

$secciones = [
    "solicitudes" => "Solicitudes",
    "directores"  => "Directores",
    "ponentes"    => "Ponentes",
    "alumnos"     => "Alumnos",
    "cursos"      => "Cursos",
    "cambios"     => "Historial de cambios",
    "papelera"    => "Papelera",
];
$rolesSeccion = ["directores" => "director", "ponentes" => "ponente", "alumnos" => "alumno"];
$categorias   = categoriasCursos();

$seccion = $_GET["seccion"] ?? "solicitudes";
if (!isset($secciones[$seccion])) $seccion = "solicitudes";

$con = conexion();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["token"], $_POST["token"] ?? "")) {
        http_response_code(400);
        die("Solicitud no válida.");
    }

    $accion = $_POST["accion"] ?? "";
    $id     = (int) ($_POST["id"] ?? 0);
    $msg    = "";

    switch ($accion) {
        case "aceptar":
            $con->prepare("UPDATE usuarios SET estado = 'activo' WHERE id = ? AND rol IN ('director', 'ponente')")->execute([$id]);
            $msg = "Solicitud aceptada.";
            break;
        case "rechazar":
            $con->prepare("UPDATE usuarios SET estado = 'rechazado' WHERE id = ? AND rol IN ('director', 'ponente')")->execute([$id]);
            $msg = "Solicitud rechazada.";
            break;
        case "eliminar_usuario":
            $con->prepare("DELETE FROM usuarios WHERE id = ? AND rol <> 'admin'")->execute([$id]);
            $msg = "Usuario eliminado.";
            break;
        case "crear_curso":
            $titulo      = trim($_POST["titulo"] ?? "");
            $descripcion = trim($_POST["descripcion"] ?? "");
            $categoria   = $_POST["categoria"] ?? "";
            $ponente     = (int) ($_POST["ponente_id"] ?? 0) ?: null;
            if ($titulo !== "" && in_array($categoria, $categorias, true)) {
                $con->prepare("INSERT INTO cursos (titulo, descripcion, categoria, ponente_id) VALUES (?, ?, ?, ?)")
                    ->execute([$titulo, $descripcion, $categoria, $ponente]);
                $msg = "Curso creado.";
            } else {
                $msg = "Escribe un título y elige una categoría.";
            }
            break;
        case "eliminar_curso":
            $con->prepare("DELETE FROM cursos WHERE id = ?")->execute([$id]);
            $msg = "Curso eliminado.";
            break;

        case "restaurar_curso":
            $con->prepare("UPDATE cursos SET eliminado_en = NULL, eliminado_por = NULL WHERE id = ?")->execute([$id]);
            $msg = "Curso restaurado.";
            break;
        case "borrar_definitivo":
            $con->prepare("DELETE FROM cursos WHERE id = ? AND eliminado_en IS NOT NULL")->execute([$id]);
            $msg = "Curso borrado definitivamente.";
            break;
        case "restaurar_descripcion":
            $q = $con->prepare("UPDATE cursos c SET descripcion = ch.valor_anterior
                                FROM cambios ch
                                WHERE ch.id = ? AND ch.curso_id = c.id AND ch.valor_anterior IS NOT NULL
                                  AND NOT ch.restaurado");
            $q->execute([$id]);
            if ($q->rowCount()) {
                $con->prepare("UPDATE cambios SET restaurado = true WHERE id = ?")->execute([$id]);
                $msg = "Descripción restaurada.";
            }
            break;
    }

    $_SESSION["mensaje"] = $msg;
    header("Location: admin.php?seccion=" . $seccion);
    exit();
}

$mensaje = $_SESSION["mensaje"] ?? "";
unset($_SESSION["mensaje"]);

$pendientes = (int) $con->query("SELECT COUNT(*) FROM usuarios WHERE rol IN ('director', 'ponente') AND estado = 'pendiente'")->fetchColumn();
$ponentes   = $con->query("SELECT id, nombre, apellidos FROM usuarios WHERE rol = 'ponente' AND estado = 'activo' ORDER BY nombre")->fetchAll();

$filas = [];
if ($seccion === "solicitudes") {
    $filas = $con->query("SELECT id, nombre, apellidos, correo, rol, creado_en FROM usuarios
                          WHERE rol IN ('director', 'ponente') AND estado = 'pendiente' ORDER BY creado_en")->fetchAll();
} elseif (isset($rolesSeccion[$seccion])) {
    $q = $con->prepare("SELECT id, nombre, apellidos, correo, estado, creado_en FROM usuarios
                        WHERE rol = ? AND estado <> 'pendiente' ORDER BY nombre");
    $q->execute([$rolesSeccion[$seccion]]);
    $filas = $q->fetchAll();
} elseif ($seccion === "cambios") {
    $rolFiltro = in_array($_GET["rol"] ?? "", ["director", "ponente"], true) ? $_GET["rol"] : "";
    $q = $con->prepare("SELECT autor, rol, descripcion, creado_en FROM cambios
                        WHERE rol IN ('director', 'ponente') AND (? = '' OR rol = ?)
                        ORDER BY creado_en DESC LIMIT 200");
    $q->execute([$rolFiltro, $rolFiltro]);
    $filas = $q->fetchAll();
} elseif ($seccion === "papelera") {
    $filas = $con->query("SELECT id, titulo, categoria, eliminado_por, eliminado_en FROM cursos
                          WHERE eliminado_en IS NOT NULL ORDER BY eliminado_en DESC")->fetchAll();
    $descripciones = $con->query("SELECT ch.id, ch.autor, ch.rol, ch.valor_anterior, ch.creado_en,
                                         c.titulo, c.descripcion AS actual
                                  FROM cambios ch JOIN cursos c ON c.id = ch.curso_id
                                  WHERE ch.valor_anterior IS NOT NULL AND NOT ch.restaurado
                                    AND ch.rol IN ('director', 'ponente') AND c.eliminado_en IS NULL
                                  ORDER BY ch.creado_en DESC LIMIT 100")->fetchAll();
} else {
    $filas = $con->query("SELECT c.id, c.titulo, c.categoria, c.ponente_id, u.nombre, u.apellidos
                          FROM cursos c LEFT JOIN usuarios u ON u.id = c.ponente_id
                          WHERE c.eliminado_en IS NULL
                          ORDER BY c.creado_en DESC")->fetchAll();
}

$token = e($_SESSION["token"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de administración | Nova Academy</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="principal.css">
    <link rel="stylesheet" href="registro.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="paneles.css">
</head>
<body class="tema-admin">

<header class="navbar">
    <div class="navbar-contenido">
        <a href="principal.php" class="logo">
            <img src="imagenes/3.png" alt="" class="logo-icono">
            <img src="imagenes/2.png" alt="Nova Academy" class="logo-texto">
        </a>

        <nav class="menu">
            <a href="principal.php">Inicio</a>
            <a href="admin.php" class="activo">Panel</a>
        </nav>

        <div class="acciones-nav">
            <a href="logout.php" class="btn btn-borde">Cerrar sesión</a>
        </div>
    </div>
</header>

<main class="admin">
    <h1>Panel de administración</h1>
    <p class="registro-sub">Hola, <?= e($_SESSION["nombre"]) ?>. Administra cursos, directores, ponentes y alumnos.</p>

    <nav class="admin-tabs">
        <?php foreach ($secciones as $clave => $titulo): ?>
            <a href="?seccion=<?= $clave ?>" class="<?= $clave === $seccion ? "activo" : "" ?>">
                <?= $titulo ?>
                <?php if ($clave === "solicitudes" && $pendientes): ?>
                    <span class="admin-contador"><?= $pendientes ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($mensaje): ?>
        <p class="registro-aviso"><?= e($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($seccion === "cursos"): ?>
        <form method="post" class="admin-tarjeta admin-form">
            <h2>Nuevo curso</h2>
            <input type="hidden" name="token" value="<?= $token ?>">
            <input type="hidden" name="accion" value="crear_curso">
            <div class="campos-doble">
                <label class="campo"><span>Título</span>
                    <input type="text" name="titulo" maxlength="150" required></label>
                <label class="campo"><span>Categoría</span>
                    <select name="categoria" required>
                        <?php foreach ($categorias as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
                    </select></label>
            </div>
            <label class="campo"><span>Descripción</span>
                <textarea name="descripcion" rows="3"></textarea></label>
            <label class="campo"><span>Ponente</span>
                <select name="ponente_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($ponentes as $p): ?>
                        <option value="<?= $p["id"] ?>"><?= e($p["nombre"] . " " . $p["apellidos"]) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <button type="submit" class="btn btn-primario registro-boton">Crear curso</button>
        </form>
    <?php endif; ?>

    <?php if ($seccion === "cambios"): ?>
        <nav class="filtros">
            <a href="?seccion=cambios" class="<?= $rolFiltro === "" ? "activo" : "" ?>">Todos</a>
            <a href="?seccion=cambios&rol=director" class="<?= $rolFiltro === "director" ? "activo" : "" ?>">Directores</a>
            <a href="?seccion=cambios&rol=ponente" class="<?= $rolFiltro === "ponente" ? "activo" : "" ?>">Ponentes</a>
        </nav>
        <?php tablaCambios($filas); ?>
    <?php elseif ($seccion === "papelera"): ?>
        <h2 class="admin-subtitulo">Cursos eliminados</h2>
        <p class="registro-sub">Cursos que borraron los directores o ponentes. Puedes regresarlos o borrarlos para siempre.</p>
        <div class="admin-tarjeta">
            <?php if (!$filas): ?>
                <p class="admin-vacio">La papelera está vacía.</p>
            <?php else: ?>
                <table class="admin-tabla">
                    <thead><tr><th>Curso</th><th>Categoría</th><th>Eliminado por</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($filas as $f): ?>
                        <tr>
                            <td><?= e($f["titulo"]) ?></td>
                            <td><?= e($f["categoria"]) ?></td>
                            <td><?= e($f["eliminado_por"] ?: "Desconocido") ?></td>
                            <td><?= e(date("d/m/Y H:i", strtotime($f["eliminado_en"]))) ?></td>
                            <td class="admin-acciones">
                                <form method="post">
                                    <input type="hidden" name="token" value="<?= $token ?>">
                                    <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                    <button name="accion" value="restaurar_curso" class="btn-mini exito">Restaurar</button>
                                    <button name="accion" value="borrar_definitivo" class="btn-mini peligro"
                                            onclick="return confirm('¿Borrar este curso para siempre? No se podrá recuperar.')">Borrar para siempre</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <h2 class="admin-subtitulo">Descripciones modificadas</h2>
        <p class="registro-sub">Si alguien cambió o borró la descripción de un curso por error, regrésala a como estaba.</p>
        <div class="admin-tarjeta">
            <?php if (!$descripciones): ?>
                <p class="admin-vacio">No hay descripciones para restaurar.</p>
            <?php else: ?>
                <table class="admin-tabla">
                    <thead><tr><th>Fecha</th><th>Curso</th><th>Cambió</th><th>Antes</th><th>Ahora</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($descripciones as $d): ?>
                        <tr>
                            <td><?= e(date("d/m/Y H:i", strtotime($d["creado_en"]))) ?></td>
                            <td><?= e($d["titulo"]) ?></td>
                            <td><?= e($d["autor"]) ?> <span class="etiqueta-rol"><?= e(ucfirst($d["rol"])) ?></span></td>
                            <td class="cambio-texto"><?= e($d["valor_anterior"] ?: "(vacía)") ?></td>
                            <td class="cambio-texto"><?= e($d["actual"] ?: "(vacía)") ?></td>
                            <td class="admin-acciones">
                                <form method="post" onsubmit="return confirm('¿Regresar la descripción a como estaba antes?')">
                                    <input type="hidden" name="token" value="<?= $token ?>">
                                    <input type="hidden" name="accion" value="restaurar_descripcion">
                                    <input type="hidden" name="id" value="<?= $d["id"] ?>">
                                    <button class="btn-mini exito">Restaurar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="admin-tarjeta">
        <?php if (!$filas): ?>
            <p class="admin-vacio">
                <?= $seccion === "solicitudes" ? "No hay solicitudes pendientes." : "Todavía no hay registros." ?>
            </p>
        <?php elseif ($seccion === "cursos"): ?>
            <table class="admin-tabla">
                <thead><tr><th>Curso</th><th>Categoría</th><th>Ponente</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($filas as $f): ?>
                    <tr>
                        <td><?= e($f["titulo"]) ?></td>
                        <td><?= e($f["categoria"]) ?></td>
                        <td><?= $f["nombre"] ? e($f["nombre"] . " " . $f["apellidos"]) : "Sin asignar" ?></td>
                        <td class="admin-acciones">
                            <a href="curso.php?id=<?= $f["id"] ?>" class="btn-mini">Editar contenido y comentar</a>
                            <form method="post" onsubmit="return confirm('¿Eliminar este curso?')">
                                <input type="hidden" name="token" value="<?= $token ?>">
                                <input type="hidden" name="accion" value="eliminar_curso">
                                <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                <button class="btn-mini peligro">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <table class="admin-tabla">
                <thead><tr><th>Nombre</th><th>Correo</th><th>Registro</th><th>Rol</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($filas as $f): ?>
                    <tr>
                        <td><?= e($f["nombre"] . " " . $f["apellidos"]) ?>
                            <?php if (($f["estado"] ?? "") === "rechazado"): ?><span class="etiqueta">Rechazado</span><?php endif; ?>
                        </td>
                        <td><?= e($f["correo"]) ?></td>
                        <td><?= e(date("d/m/Y", strtotime($f["creado_en"]))) ?></td>

                        <?php if ($seccion === "solicitudes"): ?>
                            <td><?= e(ucfirst($f["rol"])) ?></td>
                            <td class="admin-acciones">
                                <form method="post">
                                    <input type="hidden" name="token" value="<?= $token ?>">
                                    <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                    <button name="accion" value="aceptar" class="btn-mini exito">Aceptar</button>
                                    <button name="accion" value="rechazar" class="btn-mini peligro">Rechazar</button>
                                </form>
                            </td>
                        <?php else: ?>
                            <td><?= e(ucfirst($rolesSeccion[$seccion])) ?></td>
                            <td class="admin-acciones">
                                <form method="post" onsubmit="return confirm('¿Eliminar a este usuario?')">
                                    <input type="hidden" name="token" value="<?= $token ?>">
                                    <input type="hidden" name="accion" value="eliminar_usuario">
                                    <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                    <button class="btn-mini peligro">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<footer class="pie">
    <img src="imagenes/3.png" alt="" class="pie-icono">
    <p>© <?= date("Y") ?> Nova Academy. Todos los derechos reservados por Bastian :p.</p>
</footer>

</body>
</html>
