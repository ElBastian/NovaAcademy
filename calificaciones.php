<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

require "conexion.php";
$conexion = conexion();

$sql = "SELECT calificaciones.id,
               usuarios.nombre AS nombre,
               materias.nombre AS materia,
               calificaciones.calificacion
        FROM calificaciones
        INNER JOIN usuarios ON usuarios.id = calificaciones.usuario_id
        INNER JOIN materias ON materias.id = calificaciones.materia_id
        ORDER BY usuarios.nombre, materias.nombre";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calificaciones</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="contenedor">
        <h1>Lista de calificaciones</h1>
        <div class="tabla-scroll">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Materia</th>
                    <th>Calificación</th>
                </tr>
                <?php while ($fila = $resultado->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo (int) $fila["id"]; ?></td>
                        <td><?php echo htmlspecialchars($fila["nombre"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["materia"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["calificacion"]); ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
        <div class="acciones">
            <a href="dashboard.php">Volver</a>
        </div>
    </main>
</body>
</html>
<?php $conexion->close(); ?>