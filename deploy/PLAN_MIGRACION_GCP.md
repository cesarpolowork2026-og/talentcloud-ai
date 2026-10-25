# Plan de migración a Google Cloud (Compute Engine)

Este plan describe cómo llevar TalentCloud AI desde el servidor virtual donde hoy se ejecuta (un VPS con Ubuntu 24.04 y Docker Compose, accesible en `https://talentcloudia.online/`) a una máquina virtual de Google Compute Engine. El plan es previo a la migración: describe qué se hará, en qué orden y cómo se vuelve atrás si algo falla.

## 1. Inventario de la aplicación

| Elemento | Detalle |
|---|---|
| Aplicación | Laravel 12 sobre PHP 8.2, interfaz con Livewire 3 y Tailwind 3. |
| Dependencias | `composer.json`: Livewire, spatie/laravel-permission, maatwebsite/excel, stripe/stripe-php. Recursos de interfaz compilados con Node 22 y Vite en la construcción de la imagen. |
| Base de datos | PostgreSQL 17, 31 tablas, 55 migraciones. El volcado completo con datos de demostración está en `talentcloud/database/esquema/talentcloud_completa.sql`. |
| Archivos | Currículos en un volumen privado (`storage/app/private/cvs`); no están en la base. |
| Procesos | Cinco contenedores: `web` (Caddy), `app` (PHP-FPM), `queue` (cola), `scheduler` (tareas programadas) y `db` (PostgreSQL). |
| Servicios externos | Correo SMTP; Stripe en modo de prueba (con aviso firmado). |
| Configuración | Variables de entorno en `deploy/.env` (no se sube al repositorio): `APP_*`, `DB_*`, `MAIL_*`, `STRIPE_*`, `PAGOS_PASARELA`, `SOPORTE_CORREO`, `LEGAL_*`, `SITE_ADDRESS`. La plantilla es `deploy/.env.example`. |
| Imágenes | `app` y `web`, construidas con el `Dockerfile` del proyecto. |

## 2. Evaluación de compatibilidad

- **Sistema operativo:** la aplicación corre dentro de contenedores, así que solo exige Docker y Docker Compose en el anfitrión. Ubuntu 24.04 LTS en Compute Engine es el mismo sistema donde ya está probada.
- **Extensiones de PHP:** `pdo_pgsql`, `intl`, `zip`, `gd`, `opcache` y `bcmath`, ya incluidas en la imagen.
- **Memoria:** el conjunto usa menos de 1 GB en reposo; los contenedores tienen límites que suman 1,8 GB. Una máquina de 4 GB es suficiente.
- **Red:** HTTPS (443) y HTTP (80, para el certificado) abiertos a internet; SSH (22) solo por llave. La base de datos no se publica.
- **Zona horaria:** la aplicación guarda fechas sin zona y las interpreta en `America/Lima` (`APP_TIMEZONE`); debe mantenerse igual.
- **Correo:** el proveedor SMTP debe permitir el envío desde la nueva dirección IP; si no, se usa otro puerto o servicio.
- **Incompatibilidades conocidas:** ninguna. No hay dependencias del sistema operativo anfitrión.

## 3. Estrategia

Se elige **rehost** (llevar la aplicación tal como está a una máquina virtual en la nube), porque:
- no exige cambios de código ni de arquitectura;
- reduce el riesgo y el tiempo de migración;
- permite validar la aplicación en Google Cloud antes de decidir qué servicios gestionados incorporar.

Orden de migración: primero la base de datos y los currículos (los datos), luego la aplicación y, por último, el tráfico (dirección y certificado). En la unidad III se evaluará mover la base a un servicio gestionado (Cloud SQL) y la cola a servicios propios de la nube.

## 4. Arquitectura objetivo

```
Usuarios ──HTTPS──► IP estática ──► VM Compute Engine (e2-medium, Ubuntu 24.04)
                                      └─ Docker Compose: web (Caddy) → app (Laravel) → db (PostgreSQL)
                                                          queue · scheduler
                                      └─ Disco persistente (base de datos y currículos)
Correo SMTP y Stripe (externos)
```

- **Pasa a la nube:** la aplicación, la base de datos y los currículos, en una sola máquina virtual.
- **Se mantiene fuera:** el proveedor de correo y la pasarela de pago, que ya son servicios externos.
- **Se conserva temporalmente:** el servidor actual, como respaldo y como destino de reversión, hasta validar la migración.

## 5. Secuencia de migración

1. **Respaldo previo.** En el servidor actual, ejecutar `deploy/respaldo.sh` (base de datos y currículos) y copiar los archivos fuera de la máquina.
2. **Crear la máquina virtual.**

   ```
   gcloud compute instances create talentcloud-vm \
     --project=<ID_DEL_PROYECTO> --zone=southamerica-west1-a \
     --machine-type=e2-medium \
     --image-family=ubuntu-2404-lts-amd64 --image-project=ubuntu-os-cloud \
     --boot-disk-size=30GB --tags=http-server,https-server
   ```
3. **Reservar una IP estática** y asignarla a la máquina.
4. **Reglas de firewall mínimas:** permitir 80 y 443 desde cualquier origen y 22 solo desde direcciones autorizadas.
5. **Preparar la máquina:** crear un usuario sin privilegios, desactivar el acceso por contraseña y el acceso de `root` por SSH, instalar Docker y Docker Compose, activar el cortafuegos del sistema y configurar 2 GB de memoria de intercambio.
6. **Transferir el código:** clonar el repositorio de GitHub en la máquina.
7. **Configurar el entorno:** copiar `deploy/.env.example` a `deploy/.env` y completar los valores (clave de la aplicación, contraseña de la base de datos, credenciales de correo y de Stripe). `SITE_ADDRESS` y `APP_URL` apuntan al nuevo dominio.
8. **Construir y levantar:** `docker compose -f docker-compose.prod.yml build` y `up -d`.
9. **Importar los datos:** restaurar el respaldo de la base con `psql` y descomprimir los currículos en el volumen de almacenamiento. Ejecutar `php artisan migrate --force` por si hay migraciones nuevas.
10. **Probar** con la lista de aceptación (sección 7) antes de cambiar el tráfico.
11. **Cambiar el tráfico:** apuntar el dominio a la IP nueva y dejar que Caddy obtenga el certificado.
12. **Programar** el respaldo nocturno con `cron` y verificar que el planificador y la cola estén activos.

## 6. Seguridad

- **Acceso:** SSH solo con llave, sin contraseña ni acceso de `root`; protección contra intentos repetidos con fail2ban.
- **Red:** solo 80 y 443 abiertos al público; el puerto 22 restringido; la base de datos, la cola y el planificador no tienen puertos publicados.
- **Secretos:** el archivo `.env` vive solo en la máquina, con permisos de lectura restringidos, y no se sube al repositorio. Como mejora prevista, pasar los secretos a Secret Manager.
- **HTTPS:** certificado automático de Caddy, con redirección de HTTP a HTTPS y cabecera HSTS.
- **Permisos:** el volumen de currículos es privado (fuera del directorio público) y se descarga solo por una ruta autorizada. El código de la aplicación no corre como administrador: los procesos de PHP-FPM, la cola y el planificador se ejecutan como `www-data` y la base de datos como `postgres`. Solo arrancan como `root` el proceso maestro de PHP-FPM, el contenedor de Caddy (para abrir los puertos 80 y 443) y el envoltorio que lanza la cola y el planificador. Como mejora prevista, imágenes con un usuario sin privilegios también para esos procesos de arranque.
- **Respaldos:** copia nocturna de la base y de los currículos, con retención de 7 días, y copias del disco de la máquina.

## 7. Pruebas de aceptación tras migrar

| Prueba | Resultado esperado |
|---|---|
| El sitio abre por HTTPS con certificado válido | Portada visible, sin advertencias del navegador. |
| `GET /api/v1/salud` | `{"estado":"ok","base_de_datos":true}`. |
| Iniciar sesión con cada perfil | Cada uno llega a su pantalla de inicio. |
| Persistencia | Un dato creado antes de reiniciar los contenedores sigue ahí después. |
| Carga de un currículo | Se sube, se procesa y se puede descargar con permiso. |
| Flujo central | Crear vacante, postular, mover de etapa, programar entrevista y evaluar. |
| Correo | Un aviso de prueba llega al destinatario. |
| Cola y planificador | No hay trabajos fallidos y las tareas programadas están activas. |
| Datos | El número de filas de las tablas principales coincide con el servidor de origen. |
| Seguridad | El acceso por SSH con contraseña es rechazado y la base de datos no responde desde internet. |

## 8. Riesgos y reversión

| Riesgo | Mitigación |
|---|---|
| Pérdida de datos durante la transferencia | Respaldo verificado antes de empezar; el servidor de origen no se apaga ni se modifica hasta terminar. |
| Diferencias de configuración entre servidores | Mismas imágenes y mismo `.env.example`; la aplicación corre en contenedores. |
| El correo no sale desde la nueva IP | Probar un envío antes de cambiar el tráfico; alternativa de puerto o proveedor. |
| Certificado que no se emite | Verificar que el dominio apunte a la IP y que los puertos 80 y 443 estén abiertos. |
| Indisponibilidad al cambiar de servidor | Hacerlo fuera de horario y bajar antes el tiempo de vida (TTL) del registro DNS. |
| Costos inesperados | Presupuesto y alertas de facturación; apagar la máquina cuando no se use. |

**Reversión:** el servidor de origen se mantiene encendido y sin cambios hasta aprobar la migración. Si una prueba de aceptación falla, el dominio vuelve a apuntar al servidor de origen. Si los datos del origen cambiaron mientras tanto, se vuelve a ejecutar el respaldo y la restauración. Solo se retira el servidor de origen cuando la nueva máquina lleva operando sin incidentes un periodo acordado por el equipo.

## 9. Costos

No se fija una cifra exacta. Los recursos que generan costo en Google Cloud son:

| Recurso | Qué lo determina |
|---|---|
| Máquina virtual (e2-medium) | Horas de encendido y la región. |
| Disco persistente | Tamaño en GB (30 GB de arranque) y copias (snapshots). |
| Dirección IP estática | Se cobra si está reservada y sin uso. |
| Tráfico de salida a internet | Volumen de datos que la aplicación envía. |
| Dominio | Opcional; hoy se usa un nombre basado en la IP. |
| Servicios gestionados (unidad III) | Cloud SQL y otros, solo si se adoptan. |

Supuestos: una máquina encendida de forma continua, un volumen bajo de tráfico (uso de demostración) y un solo entorno. Para controlar el gasto se configura un presupuesto con alertas y se apaga la máquina cuando no se necesite.
