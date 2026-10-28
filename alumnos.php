<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

require "conexion.php";
$conexion = conexion();

$sql = "SELECT id, matricula, nombre, apellido, email, fecha_nacimiento, grupo FROM alumnos";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alumnos</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="contenedor">
        <h1>Lista de alumnos</h1>
        <div class="tabla-scroll">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Matrícula</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Email</th>
                    <th>Fecha de nacimiento</th>
                    <th>Grupo</th>
                </tr>
                <?php while ($fila = $resultado->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo (int) $fila["id"]; ?></td>
                        <td><?php echo htmlspecialchars($fila["matricula"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["nombre"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["apellido"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["email"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["fecha_nacimiento"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["grupo"]); ?></td>
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