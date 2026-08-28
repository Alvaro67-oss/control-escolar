# Poner Control Escolar en producción

Guía rápida específica de este proyecto. La versión ilustrada, con diagrama y explicación completa de cada paso, está aquí:
**https://claude.ai/code/artifact/1a0c3278-04b9-4679-8c00-e1d52ccf9004**

## Qué ya está listo en este zip

- `.env` con contraseñas generadas (root de MySQL, usuario `appuser`, y su contraseña sincronizada con `initdb/control_escolar.sql`).
- `src/conexion.php` y `src/qr/conexion.php` leen las credenciales de variables de entorno (`DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`) en vez de tenerlas escritas en el código.
- `docker-compose.yml`:
  - MySQL (puerto 3307) y phpMyAdmin (puerto 8081) solo escuchan en `127.0.0.1` — no quedan expuestos a la red ni a internet.
  - Todos los servicios tienen `restart: unless-stopped`, para que sobrevivan un reinicio de la computadora.
  - Hay un servicio `cloudflared` ya definido, apagado por default (perfil `tunnel`).
- `.gitignore` con `.env` incluido, para que nunca subas las contraseñas reales a GitHub.

## Pasos en la computadora dedicada

1. Instala Docker (`curl -fsSL https://get.docker.com | sh` en Linux, o Docker Desktop en Windows/macOS).
2. Copia esta carpeta a esa computadora (o sube el repo a GitHub sin el `.env`, y clónalo ahí — luego crea el `.env` manualmente con las mismas contraseñas o unas nuevas).
3. `docker compose up -d --build` — verifica con `docker compose ps` que los tres servicios (`web`, `mysql`, `phpmyadmin`) estén `healthy`/`running`.
4. Confirma en local: `curl http://localhost:8080` debe responder.

## Exponerlo a internet (Cloudflare Tunnel)

1. Dominio agregado a una cuenta gratuita de Cloudflare.
2. **Zero Trust → Networks → Tunnels → Create a tunnel** (tipo Cloudflared). Copia el token.
3. Pon ese token en `.env`:
   ```
   CLOUDFLARE_TUNNEL_TOKEN=el-token-que-copiaste
   ```
4. Arranca también el túnel (no lo hace `docker compose up` normal, por eso tiene su propio perfil):
   ```bash
   docker compose --profile tunnel up -d
   ```
5. En el dashboard del túnel, pestaña **Public Hostname**: subdominio + dominio, tipo `HTTP`, URL del servicio → `web:80` (el nombre del servicio de Docker, no un puerto de la computadora).
6. En 1–2 minutos, `https://tu-subdominio.tudominio.com` sirve la app, con HTTPS automático. `https://tu-subdominio.tudominio.com/qr` sirve el módulo QR.

## Antes de compartir la URL con alguien más

- [ ] `.env` no se subió a git (revisa `git status` — no debería aparecer).
- [ ] Cambiaste (o al menos revisaste) los usuarios de prueba `b23`/`b27` de la tabla `usuarios` — están en texto plano.
- [ ] Programaste un respaldo del volumen `db_data` (por ejemplo, `docker compose exec mysql mysqldump -uroot -p$MYSQL_ROOT_PASSWORD control_escolar > backup.sql` por cron).
- [ ] `docker compose ps` muestra los tres servicios corriendo y `healthy`.

## Recomendación aparte (no aplicada en este zip)

La tabla `usuarios` guarda las contraseñas del login de la app en texto plano (`1234` para los usuarios de prueba). Funciona, pero si vas a usarlo con datos reales de alumnos vale la pena migrar a contraseñas hasheadas (`password_hash()` / `password_verify()` en PHP) en `login.php`. Lo dejé fuera de estos cambios porque toca la lógica de autenticación, no solo la infraestructura — avísame si quieres que lo haga.
