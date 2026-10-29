<?php
function paginaRol($rol) {
    $paginas = [
        "alumno"   => "estudiante.php",
        "ponente"  => "ponente.php",
        "director" => "director.php",
        "admin"    => "admin.php",
    ];
    return $paginas[$rol] ?? "principal.php";
}

function requiereRol($rol) {
    if (!isset($_SESSION["usuario_id"])) {
        header("Location: login.php");
        exit();
    }
    if ($_SESSION["rol"] !== $rol) {
        header("Location: " . paginaRol($_SESSION["rol"]));
        exit();
    }
}

function autorActual($con) {
    $q = $con->prepare("SELECT nombre || ' ' || apellidos FROM usuarios WHERE id = ?");
    $q->execute([$_SESSION["usuario_id"]]);
    return $q->fetchColumn() ?: $_SESSION["nombre"];
}
function registrarCambio($con, $descripcion, $cursoId = null, $valorAnterior = null) {
    $con->prepare("INSERT INTO cambios (usuario_id, autor, rol, descripcion, curso_id, valor_anterior)
                   VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$_SESSION["usuario_id"], autorActual($con), $_SESSION["rol"], $descripcion, $cursoId, $valorAnterior]);
}

function eliminarCurso($con, $id, $ponenteId = null) {
    $sql    = "UPDATE cursos SET eliminado_en = CURRENT_TIMESTAMP, eliminado_por = ?
               WHERE id = ? AND eliminado_en IS NULL";
    $params = [autorActual($con), $id];
    if ($ponenteId !== null) {
        $sql     .= " AND ponente_id = ?";
        $params[] = $ponenteId;
    }
    $q = $con->prepare("$sql RETURNING titulo");
    $q->execute($params);
    if ($titulo = $q->fetchColumn()) {
        registrarCambio($con, "Eliminó el curso \"$titulo\".", $id);
        return "Curso eliminado.";
    }
    return "";
}

function tablaCambios($cambios, $mostrarRol = true) {
    if (!$cambios) {
        echo '<p class="panel-vacio">Todavía no hay cambios registrados.</p>';
        return;
    }
    ?>
<div class="panel-tarjeta">
    <table class="panel-tabla">
        <thead><tr><th>Fecha</th><th>Usuario</th><?php if ($mostrarRol): ?><th>Rol</th><?php endif; ?><th>Cambio</th></tr></thead>
        <tbody>
        <?php foreach ($cambios as $c): ?>
            <tr>
                <td><?= e(date("d/m/Y H:i", strtotime($c["creado_en"]))) ?></td>
                <td><?= e($c["autor"]) ?></td>
                <?php if ($mostrarRol): ?><td><?= e(ucfirst($c["rol"])) ?></td><?php endif; ?>
                <td class="cambio-texto"><?= e($c["descripcion"]) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <?php
}

function e($texto) { return htmlspecialchars((string) $texto); }

function colorCategoria($categoria) {
    $colores = [
        "Programación" => "azul",
        "Negocios"     => "morado",
        "Diseño"       => "naranja",
        "Tecnología"   => "cian",
        "Idiomas"      => "rosa",
        "Marketing"               => "naranja",
        "Finanzas"                => "verde",
        "Ciencia de datos"        => "cian",
        "Inteligencia artificial" => "morado",
        "Ciberseguridad"          => "azul",
        "Fotografía y video"      => "rosa",
        "Música"                  => "morado",
        "Desarrollo personal"     => "verde",
    ];
    return $colores[$categoria] ?? "azul";
}

function navbarPanel($etiquetaRol) {
    $pagina = paginaRol($_SESSION["rol"]);
    ?>
<header class="navbar">
    <div class="navbar-contenido">
        <a href="principal.php" class="logo">
            <img src="imagenes/3.png" alt="" class="logo-icono">
            <img src="imagenes/2.png" alt="Nova Academy" class="logo-texto">
        </a>

        <nav class="menu">
            <a href="principal.php">Inicio</a>
            <a href="<?= $pagina ?>" class="activo">Mi panel</a>
            <a href="principal.php#contacto">Contacto</a>
        </nav>

        <div class="acciones-nav">
            <span class="rol-chip"><?= e($etiquetaRol) ?></span>
            <a href="logout.php" class="btn btn-borde btn-salir">Cerrar sesión</a>
        </div>
    </div>
</header>
    <?php
}

function piePanel() {
    ?>
<footer class="pie">
    <img src="imagenes/3.png" alt="" class="pie-icono">
    <p>© <?= date("Y") ?> Nova Academy. Todos los derechos reservados por Bastian :p.</p>
</footer>
    <?php
}

function categoriasCursos() {
    return [
        "Sin asignar",
        "Programación", "Negocios", "Diseño", "Tecnología", "Idiomas",
        "Marketing", "Finanzas", "Ciencia de datos", "Inteligencia artificial",
        "Ciberseguridad", "Fotografía y video", "Música", "Desarrollo personal",
        "Otra",
    ];
}

function crearCurso($con, $ponenteId) {
    $titulo      = trim($_POST["titulo"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $categoria   = $_POST["categoria"] ?? "";

    if ($titulo === "" || !in_array($categoria, categoriasCursos(), true)) {
        return "Escribe un título y elige una categoría.";
    }

    $con->prepare("INSERT INTO cursos (titulo, descripcion, categoria, ponente_id) VALUES (?, ?, ?, ?)")
        ->execute([$titulo, $descripcion, $categoria, $ponenteId]);
    registrarCambio($con, "Creó el curso \"$titulo\" ($categoria).");
    return "Curso creado.";
}

function formNuevoCurso($token, $ponentes = null) {
    ?>
<form method="post" class="admin-tarjeta admin-form">
    <h2>Nuevo curso</h2>
    <input type="hidden" name="token" value="<?= $token ?>">
    <input type="hidden" name="accion" value="crear_curso">
    <div class="campos-doble">
        <label class="campo"><span>Título</span>
            <input type="text" name="titulo" maxlength="150" required></label>
        <label class="campo"><span>Categoría</span>
            <select name="categoria" required>
                <?php foreach (categoriasCursos() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
            </select></label>
    </div>
    <label class="campo"><span>Descripción</span>
        <textarea name="descripcion" rows="3"></textarea></label>
    <?php if ($ponentes !== null): ?>
        <label class="campo"><span>Ponente</span>
            <select name="ponente_id">
                <option value="">Sin asignar</option>
                <?php foreach ($ponentes as $p): ?>
                    <option value="<?= $p["id"] ?>"><?= e($p["nombre"] . " " . $p["apellidos"]) ?></option>
                <?php endforeach; ?>
            </select></label>
    <?php endif; ?>
    <button type="submit" class="btn btn-primario registro-boton">Crear curso</button>
</form>
    <?php
}

function requiereSesion() {
    if (!isset($_SESSION["usuario_id"])) {
        header("Location: login.php");
        exit();
    }
}

function tokenFormulario() {
    if (empty($_SESSION["token"])) {
        $_SESSION["token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["token"];
}
function revisarToken() {
    if (!hash_equals(tokenFormulario(), $_POST["token"] ?? "")) {
        http_response_code(400);
        die("Solicitud no válida.");
    }
}

function buscarCurso($con, $id) {
    $q = $con->prepare("SELECT c.*, u.nombre, u.apellidos
                        FROM cursos c LEFT JOIN usuarios u ON u.id = c.ponente_id
                        WHERE c.id = ? AND c.eliminado_en IS NULL");
    $q->execute([$id]);
    return $q->fetch();
}

function puedeEditarCurso($curso) {
    return $_SESSION["rol"] === "admin"
        || ($_SESSION["rol"] === "ponente" && (int) $curso["ponente_id"] === (int) $_SESSION["usuario_id"]);
}

function puedeComentarCurso() {
    return in_array($_SESSION["rol"], ["director", "admin"], true);
}

function bloquesCurso($con, $cursoId) {
    $q = $con->prepare("SELECT id, titulo FROM bloques WHERE curso_id = ? ORDER BY orden, id");
    $q->execute([$cursoId]);
    $bloques = [];
    foreach ($q->fetchAll() as $b) {
        $b["tareas"] = [];
        $bloques[$b["id"]] = $b;
    }
    $q = $con->prepare("SELECT t.* FROM tareas t JOIN bloques b ON b.id = t.bloque_id
                        WHERE b.curso_id = ? ORDER BY t.creado_en, t.id");
    $q->execute([$cursoId]);
    foreach ($q->fetchAll() as $t) {
        $bloques[$t["bloque_id"]]["tareas"][] = $t;
    }
    return $bloques;
}

function preguntasExamen($con, $cursoId) {
    $q = $con->prepare("SELECT * FROM preguntas WHERE curso_id = ? ORDER BY id");
    $q->execute([$cursoId]);
    return $q->fetchAll();
}

function opcionesPregunta($p) {
    $opciones = [];
    foreach (["a", "b", "c", "d"] as $letra) {
        if (($p["opcion_$letra"] ?? "") !== "") $opciones[$letra] = $p["opcion_$letra"];
    }
    return $opciones;
}

function progresoAlumno($con, $alumnoId, $cursoId) {
    $q = $con->prepare("SELECT
            (SELECT COUNT(*) FROM tareas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = :c) AS tareas,
            (SELECT COUNT(*) FROM entregas e JOIN tareas t ON t.id = e.tarea_id JOIN bloques b ON b.id = t.bloque_id
              WHERE b.curso_id = :c AND e.alumno_id = :a) AS entregadas,
            (SELECT COUNT(*) FROM preguntas WHERE curso_id = :c) AS preguntas,
            (SELECT MAX(calificacion) FROM intentos_examen WHERE curso_id = :c AND alumno_id = :a) AS mejor,
            (SELECT completado_en FROM inscripciones WHERE curso_id = :c AND alumno_id = :a) AS completado_en");
    $q->execute(["c" => $cursoId, "a" => $alumnoId]);
    $p = $q->fetch();
    $p["tareas"]       = (int) $p["tareas"];
    $p["entregadas"]   = (int) $p["entregadas"];
    $p["preguntas"]    = (int) $p["preguntas"];
    $p["examen_listo"] = $p["preguntas"] > 0 && $p["entregadas"] >= $p["tareas"];
    return $p;
}
