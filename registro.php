<?php
include_once "conexion.php";

$mensaje = "";
$enlaceVerificacion = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int) ($_POST["id"] ?? 0);
    $nombre = trim($_POST["nombre"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($id < 1 || $nombre === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $mensaje = "Revisa los datos. La contraseña debe tener al menos 8 caracteres.";
    } else {
        $conexion = conexion();
        $sql = "SELECT id FROM usuarios WHERE id = ? OR email = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $id, $email);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $mensaje = "Ese número de control o email ya está registrado.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));

            $sql = "INSERT INTO usuarios (id, nombre, email, password, token_verificacion)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("issss", $id, $nombre, $email, $passwordHash, $token);

            try {
                $stmt->execute();
                $mensaje = "Usuario registrado correctamente.";
                $enlaceVerificacion = "verificar.php?token=" . urlencode($token);
            } catch (mysqli_sql_exception $e) {
                error_log("Error al registrar: " . $e->getMessage());
                $mensaje = "No se pudo registrar el usuario. Intenta de nuevo.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="contenedor angosto">
        <h1>Registro de alumno</h1>
        <form method="POST">
            <label>Número de control</label>
            <input type="number" name="id" min="1" required>
            <label>Nombre</label>
            <input type="text" name="nombre" required>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Contraseña</label>
            <input type="password" name="password" minlength="8" required>
            <button type="submit">Registrarme</button>
        </form>

        <p class="mensaje"><?php echo htmlspecialchars($mensaje); ?></p>

        <?php if ($enlaceVerificacion !== "") { ?>
            <p>Para confirmar tu correo:
                <a href="<?php echo htmlspecialchars($enlaceVerificacion); ?>">Confirmar correo</a>
            </p>
        <?php } ?>

        <div class="acciones">
            <a href="login.php">Iniciar sesión</a>
        </div>
    </main>
</body>
</html>