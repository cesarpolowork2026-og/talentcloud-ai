# Despliegue en un VPS

TalentCloud AI (unidad I: aplicación, cola, planificador, base de datos y servidor web con HTTPS) en **un solo servidor** con Docker Compose. Probado en un servidor de 4 GB de RAM: en reposo los cinco contenedores usan unos 250 MB y sus límites de memoria suman 1,8 GB.

```
Internet ──► Caddy (HTTPS, puertos 80/443) ──► app (PHP-FPM) ──► db (PostgreSQL 17)
                                                  ├─► queue (cola de trabajos)
                                                  └─► scheduler (tareas diarias)
```

Solo Caddy se publica a internet. La base de datos, la cola y el planificador viven en la red interna de Docker.

La base de datos usa la imagen `pgvector/pgvector:pg17` (PostgreSQL 17 con la extensión pgvector): se conserva porque una de las migraciones habilita esa extensión, que se usará en la unidad II.

| Archivo | Para qué sirve |
| --- | --- |
| `docker-compose.prod.yml` | Los cinco servicios, con límites de memoria |
| `.env.example` | Plantilla de configuración (se copia a `.env`, que no se sube a GitHub) |
| `respaldo.sh` | Copia de la base y de los CV |
| `../talentcloud/Dockerfile` | Imágenes `app` y `web` |
| `../.github/workflows/despliegue.yml` | Opcional: construye las imágenes en GitHub y, si se pide, actualiza el servidor |

## 1. Preparar el servidor (una sola vez)

Ubuntu 24.04 LTS o Debian 13, con 4 GB de RAM.

```bash
ssh root@<IP>

# Usuario sin privilegios y acceso solo con clave SSH
adduser talentcloud && usermod -aG sudo talentcloud
mkdir -p /home/talentcloud/.ssh && cp ~/.ssh/authorized_keys /home/talentcloud/.ssh/
chown -R talentcloud:talentcloud /home/talentcloud/.ssh
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/; s/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart ssh

# Firewall: solo SSH y web
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp && ufw --force enable

# 2 GB de swap, para absorber picos de memoria
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Docker
curl -fsSL https://get.docker.com | sh
usermod -aG docker talentcloud
```

Antes de cerrar la sesión de `root`, comprobar desde otra terminal que `ssh talentcloud@<IP>` funciona.

## 2. Configurar

```bash
ssh talentcloud@<IP>
git clone https://github.com/cesarpolowork2026-og/talentcloud-ai.git talentcloud
cd talentcloud/deploy
cp .env.example .env
```

En `.env`, completar como mínimo:

| Variable | Valor |
| --- | --- |
| `SITE_ADDRESS` y `APP_URL` | Con la IP `203.0.113.10`, usar `203-0-113-10.sslip.io` y `https://203-0-113-10.sslip.io`. Con un dominio propio, ese dominio. |
| `APP_KEY` | `php -r 'echo "base64:".base64_encode(random_bytes(32));'` (o con Docker: `docker run --rm php:8.2-cli php -r '...'`) |
| `DB_PASSWORD` | `openssl rand -base64 24` |
| `MAIL_*` | Un SMTP real (por ejemplo Gmail con una contraseña de aplicación). `MAIL_FROM_ADDRESS` **no puede quedar vacío**: sin remitente, cada aviso por correo falla en la cola. |

## 3. Obtener las imágenes

Hay dos formas; la primera es la que se usa en el servidor de demostración.

**A. Construir en el propio servidor** (la imagen del servidor web incluye la compilación de la interfaz; tarda unos minutos la primera vez y menos con la caché):

```bash
cd ~/talentcloud/deploy
COMPOSE_PARALLEL_LIMIT=1 DOCKER_BUILDKIT=1 docker compose -f docker-compose.prod.yml build app web
```

**B. Construirlas en GitHub** (útil si el servidor es más pequeño):

1. En GitHub: **Actions → despliegue → Run workflow** (deja "desplegar" sin marcar la primera vez).
2. En el servidor, iniciar sesión en el registro con un token personal que tenga el permiso `read:packages`:
   ```bash
   echo <TOKEN> | docker login ghcr.io -u <usuario-de-github> --password-stdin
   docker compose -f docker-compose.prod.yml pull
   ```

## 4. Arrancar

```bash
cd ~/talentcloud/deploy
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
curl -s https://<SITE_ADDRESS>/up            # debe responder 200
```

### Datos de demostración

Para cargar los datos de demostración (volcado de `../talentcloud/database/esquema/`):

```bash
C="docker compose -f docker-compose.prod.yml"
$C exec -T db psql -U talentcloud -d talentcloud -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
$C exec -T db psql -U talentcloud -d talentcloud -v ON_ERROR_STOP=1 < ../talentcloud/database/esquema/talentcloud_completa.sql
$C exec -T app php artisan optimize:clear
```

> **Seguridad:** en el volcado todas las cuentas de demostración usan la contraseña `password`, y el servidor es público. Antes de exponerlo hay que cambiarlas (por ejemplo, una contraseña distinta por cuenta). Ejemplo para una cuenta:
> ```bash
> $C exec -T app php artisan tinker --execute="App\Models\User::where('email','admin@talentcloud.ai')->first()->forceFill(['password'=>bcrypt('NUEVA-CLAVE-LARGA')])->save();"
> ```
Un volcado lleva las filas de los CV pero no sus archivos, así que hay que recrearlos para que la descarga del CV original funcione en los datos de demostración:

```bash
$C exec -T app php artisan talentcloud:regenerar-cvs-demo      # ~10 s para los 2 261 CV
```

Los genera desde el texto guardado con el mismo generador de la siembra, solo para candidatos `@correo.com`, y nunca pisa un archivo que ya existe. Es seguro repetirlo.

### Prueba de humo

Tras arrancar, comprobar que todo responde y que no hay errores:

```bash
C="docker compose -f docker-compose.prod.yml"
$C ps                                                         # los cinco servicios en "Up" (app y db "healthy")
curl -s -o /dev/null -w "%{http_code}
" https://<SITE_ADDRESS>/up        # 200
curl -s https://<SITE_ADDRESS>/api/v1/salud                  # {"estado":"ok","base_de_datos":true,"version":"v1"}
$C exec -T db psql -U talentcloud -d talentcloud -tAc "select count(*) from failed_jobs"   # 0
```

Después, iniciar sesión con una cuenta de la tabla de la raíz, subir un CV y comprobar que pasa a «procesado» y que la ficha de la postulación muestra qué requisitos aparecen en él.

### Recursos medidos

Medidos el 22 y 23 de octubre de 2026 en el servidor de demostración (4 GB de RAM), con los datos de demostración (2 261 CV):

| Medición | Resultado |
| --- | --- |
| Memoria de los contenedores en reposo | app 56 MB, db 83 MB, queue 46 MB, scheduler 44 MB, web 14 MB (unos 244 MB en total) |
| Tiempo hasta el primer byte de las páginas principales | de 144 a 248 ms (mediana de 3 cargas) |
| Tiempo de carga completa de las páginas principales | de 206 a 401 ms |

Con 4 GB de RAM y los límites del `docker-compose.prod.yml` (1,8 GB en total) sobra margen.

## 5. Actualizar

Cada cambio nuevo en `main`:

- **Desde GitHub:** *Actions → despliegue → Run workflow*, con "desplegar" marcado. Requiere cuatro secretos en *Settings → Secrets and variables → Actions*: `SERVIDOR_HOST` (IP), `SERVIDOR_USUARIO` (`talentcloud`), `SERVIDOR_CLAVE_SSH` (clave privada de un par creado solo para esto) y `SERVIDOR_HUELLA` (salida de `ssh-keyscan -t ed25519 <IP>`).
- **A mano:** `git pull`, luego los comandos `pull`, `migrate` y `up -d` del paso 4.

Las migraciones corren con el código nuevo antes de reemplazar los contenedores, así que deben ser compatibles con la versión anterior. Una migración ya aplicada no se edita: se crea otra.

## 6. Copias de seguridad

```bash
chmod +x ~/talentcloud/deploy/respaldo.sh
crontab -e     # añadir:  30 2 * * * /home/talentcloud/talentcloud/deploy/respaldo.sh >> /home/talentcloud/respaldo.log 2>&1
```

Guarda la base (`base-AAAA-MM-DD.sql.gz`) y los CV (`cvs-AAAA-MM-DD.tar.gz`) en `~/respaldos`, durante 7 días. Para restaurar la base: `gunzip -c base-….sql.gz | docker compose -f docker-compose.prod.yml exec -T db psql -U talentcloud -d talentcloud`. OVH añade además su copia diaria del servidor entero.

## Diagnóstico

```bash
docker compose -f docker-compose.prod.yml ps                 # estado de cada servicio
docker compose -f docker-compose.prod.yml logs -f app queue  # registros
docker stats --no-stream                                     # memoria por contenedor
free -h                                                      # memoria y swap del servidor
```

- **Un servicio se reinicia solo:** probablemente superó su límite de memoria. `docker inspect <contenedor> --format '{{.State.OOMKilled}}'` lo confirma.
- **HTTPS no se emite:** los puertos 80 y 443 deben estar abiertos y `SITE_ADDRESS` debe apuntar a la IP del servidor.
- **Ver la base con pgAdmin:** la base no está publicada. Entrar con `docker compose -f docker-compose.prod.yml exec db psql -U talentcloud -d talentcloud`, o crear un túnel SSH hacia el contenedor.
