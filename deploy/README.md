# Despliegue en un VPS

TalentCloud AI completo (aplicación, cola, programador, base de datos con pgvector y servicio de IA) en **un solo servidor** con Docker Compose. Probado con 4 GB de RAM: con la IA analizando CV a plena carga, el conjunto usa unos 1,8 GB.

```
Internet ──► Caddy (HTTPS, puertos 80/443) ──► app (PHP-FPM) ──► db (PostgreSQL + pgvector)
                                                  │  └─► queue (cola)   └─► scheduler (tareas diarias)
                                                  └──► ia (FastAPI + modelo de embeddings)
```

Solo Caddy se publica a internet. La base de datos y el servicio de IA viven en la red interna de Docker.

| Archivo | Para qué sirve |
| --- | --- |
| `docker-compose.prod.yml` | Los seis servicios, con límites de memoria |
| `.env.example` | Plantilla de configuración (se copia a `.env`, que no se sube a GitHub) |
| `respaldo.sh` | Copia de la base y de los CV |
| `../talentcloud/Dockerfile` | Imágenes `app` y `web` |
| `../.github/workflows/despliegue.yml` | Construye las imágenes en GitHub y, si se pide, actualiza el servidor |

## 1. Preparar el servidor (una sola vez)

Ubuntu 24.04 LTS o Debian 13, mínimo 4 GB de RAM.

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
| `IA_API_KEY` | `openssl rand -base64 32` |
| `MAIL_*` | Un SMTP real (por ejemplo Gmail con una contraseña de aplicación). `MAIL_FROM_ADDRESS` **no puede quedar vacío**: sin remitente, cada aviso por correo falla en la cola. |

## 3. Obtener las imágenes

Compilar PyTorch y el frontend no cabe en un servidor de 4 GB, así que se construyen en GitHub:

1. En GitHub: **Actions → despliegue → Run workflow** (deja "desplegar" sin marcar la primera vez).
2. En el servidor, iniciar sesión en el registro con un token personal que tenga el permiso `read:packages`:
   ```bash
   echo <TOKEN> | docker login ghcr.io -u <usuario-de-github> --password-stdin
   ```

## 4. Arrancar

```bash
cd ~/talentcloud/deploy
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
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

> **Seguridad:** las cuentas de demostración usan la contraseña `password`, y el servidor es público. Cambiar al menos la del administrador:
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
$C ps                                                         # los seis servicios en "Up" (app, db e ia "healthy")
curl -s -o /dev/null -w "%{http_code}
" https://<SITE_ADDRESS>/up        # 200
$C exec -T app php -r 'echo file_get_contents("http://ia:8080/health");'   # {"estado":"ok", ...}
$C exec -T db psql -U talentcloud -d talentcloud -tAc "select count(*) from failed_jobs"   # 0
```

Después, iniciar sesión con una cuenta de la tabla de la raíz, subir un CV y comprobar que se analiza y que el matching aparece en la postulación.

### Recursos medidos

Pila completa en Docker limitada a 2 núcleos (como un VPS-1) y a 4 (como un VPS-2), con los 2 261 CV de la demostración. La medición se hizo en un PC de escritorio: los núcleos de un VPS pueden ser algo más lentos.

| Prueba | 2 núcleos | 4 núcleos |
| --- | --- | --- |
| Analizar un CV nuevo con IA (mediana) | 123 ms | 108 ms |
| Cola de 40 análisis | 8 s | 7 s |
| Web con la IA trabajando: mediana / peor caso | 28 / 307 ms | 29 / 287 ms |
| Recrear los 2 261 archivos de CV | 8 s | 8 s |
| Memoria total en plena carga | ~1,1 GB | ~1,1 GB |

El servicio de IA es lo más pesado (~0,95 GB). Con 4 GB de RAM y 2 GB de swap sobra margen.

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
