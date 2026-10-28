<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

include_once "conexion.php";
$conexion = conexion();

$usuarioId = $_SESSION["usuario_id"];

$sql = "SELECT id, nombre, email, created_at
        FROM usuarios
        WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

if (!$usuario) {
    header("Location: logout.php");
    exit();
}

$sql = "SELECT materias.nombre AS materia, calificaciones.calificacion
        FROM calificaciones
        INNER JOIN materias ON materias.id = calificaciones.materia_id
        WHERE calificaciones.usuario_id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$calificaciones = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi panel</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="contenedor">
        <h1>Bienvenido, <?php echo htmlspecialchars($usuario["nombre"]); ?></h1>

        <h2>Mis datos</h2>
        <p><strong>ID:</strong> <?php echo (int) $usuario["id"]; ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario["email"]); ?></p>
        <p><strong>Fecha de registro:</strong> <?php echo htmlspecialchars($usuario["created_at"]); ?></p>

        <hr>

        <h2>Mis materias</h2>
        <?php if ($calificaciones->num_rows === 0) { ?>
            <p class="mensaje">Aún no tienes calificaciones registradas.</p>
        <?php } else { ?>
            <div class="tabla-scroll">
                <table>
                    <tr>
                        <th>Materia</th>
                        <th>Calificación</th>
                    </tr>
                    <?php while ($fila = $calificaciones->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fila["materia"]); ?></td>
                            <td><?php echo htmlspecialchars($fila["calificacion"]); ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        <?php } ?>

        <div class="acciones">
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </main>
</body>
</html>
<?php $conexion->close(); ?>