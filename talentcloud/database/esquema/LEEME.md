# Volcados de la base de datos

- `talentcloud_esquema.sql`: solo estructura (31 tablas, índices, claves y restricciones).
- `talentcloud_completa.sql`: estructura **y datos de demostración** (todos sintéticos: empresas, candidatos y correos ficticios). Se omite el contenido de las tablas efímeras (sesiones, caché, colas y tokens de contraseña).

Restaurar en una base nueva con PostgreSQL 17 + pgvector:

```bash
createdb -U postgres talentcloud_copia
psql -U postgres -d talentcloud_copia -v ON_ERROR_STOP=1 -f talentcloud_completa.sql
```

La forma habitual de reconstruirla es `php artisan migrate:fresh --seed`; estos archivos permiten revisarla sin ejecutar la aplicación.
