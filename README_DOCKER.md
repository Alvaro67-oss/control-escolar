# Control Escolar con Docker

## Ejecutar

Abre una terminal dentro de esta carpeta y usa:

```bash
docker compose down -v
docker compose up --build
```

Ya incluye un archivo `.env` con contraseñas generadas — no necesitas tocar nada para levantarlo. Si prefieres las tuyas, edítalo (o parte de `.env.example`) antes de levantar los contenedores.

## Abrir

- Control: http://localhost:8080
- QR: http://localhost:8080/qr
- phpMyAdmin: http://localhost:8081 *(solo desde esta misma computadora)*

## Usuarios de prueba (login de la app)

- Usuario: b23 / Contraseña: 1234
- Usuario: b27 / Contraseña: 1234

Estos son registros de la tabla `usuarios`, no tienen relación con las contraseñas de MySQL. Están en texto plano en la base — bien para pruebas, cámbialos (o hashéalos) antes de usar esto con datos reales de alumnos.

## Base de datos

- Host interno para PHP: `mysql`
- Base: `control_escolar`
- Usuario app: `appuser`
- Contraseña app, contraseña root y demás: en `.env` (no se sube a git)

## Nota

Si ya tenías una versión anterior corriendo, usa primero:

```bash
docker compose down -v
```

Luego vuelve a ejecutar:

```bash
docker compose up --build
```

`down -v` borra el volumen de la base — solo úsalo si quieres reiniciar los datos desde cero (por ejemplo, si cambiaste las contraseñas en `.env` y quieres que se apliquen).

## Arranque automático en Windows

- `archivodeinicio.bat` — levanta los contenedores y abre el panel en el navegador.
- `archivodeiniciodirectologin.bat` — igual, pero abre login y el módulo QR directo.
- `inicio_automatico.bat` — espera a que Docker Desktop esté listo, levanta los contenedores y abre el navegador. Pensado para correr solo, sin que nadie lo dispare a mano.
- `instalar_inicio_automatico.bat` — créalo una sola vez: pone un acceso directo a `inicio_automatico.bat` en el Startup de Windows, para que la app arranque sola cuando prendas la computadora dedicada.

## Para ponerlo en internet

Ver [`PRODUCCION.md`](./PRODUCCION.md) — ahí está el paso a paso para dejarlo corriendo en una computadora dedicada, accesible desde cualquier dispositivo, con Cloudflare Tunnel.
