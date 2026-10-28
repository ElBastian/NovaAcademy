# Consulta de cuentas

## Ver los correos registrados

Conéctate a la base de datos `escuela` con phpMyAdmin o MySQL y ejecuta:

```sql
SELECT id, nombre, email, email_verificado
FROM usuarios
ORDER BY nombre;
```

La columna `email_verificado` indica si el usuario confirmó su correo (`1` = sí, `0` = no).

## Contraseñas

No es posible consultar las contraseñas originales. Al registrarse, la aplicación guarda en `usuarios.password` un hash generado con `password_hash()`. El login comprueba la contraseña ingresada con `password_verify()`; no la descifra ni la muestra.

No guardes contraseñas en texto plano en este archivo ni en la base de datos. Si alguien olvidó su contraseña, debe implementarse un flujo seguro para restablecerla; actualmente el proyecto no cuenta con esa función.
