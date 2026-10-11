-- Carga sintética para medir consultas a escala: 300 empresas, 3 000 vacantes, 100 000 candidatos,
-- 300 000 postulaciones y su historia. Nunca en la base de la aplicación: se usa una copia.
--
--   docker compose exec -T pgsql psql -U postgres -c "CREATE DATABASE talentcloud_carga TEMPLATE talentcloud"
--   docker compose exec -T pgsql psql -U postgres -d talentcloud_carga < database/rendimiento/carga_sintetica.sql
--   DB_DATABASE=talentcloud_carga php artisan ...      (y al terminar: DROP DATABASE talentcloud_carga)
--
-- Parte de la base sembrada con la demo (copia un embedding y un matching de ella).
\timing off
SET client_min_messages = warning;

INSERT INTO empresas (nombre, sector, plan, estado, onboarding, created_at, updated_at)
SELECT 'Empresa de carga ' || g, 'Sector ' || (g % 12), 'pro', 'activa', '{}'::jsonb, now() - interval '200 days', now()
FROM generate_series(1, 300) g;

INSERT INTO users (name, email, password, empresa_id, created_at, updated_at, email_verified_at)
SELECT 'Reclutador ' || e.id, 'reclutador' || e.id || '@carga.test', 'x', e.id, now(), now(), now()
FROM empresas e WHERE e.nombre LIKE 'Empresa de carga %';

INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT (SELECT id FROM roles WHERE name = 'reclutador'), 'App\Models\User', u.id FROM users u WHERE u.email LIKE '%@carga.test';

INSERT INTO vacantes (empresa_id, creada_por, titulo, descripcion, ubicacion, modalidad, estado, publicada_at, created_at, updated_at)
SELECT e.id, u.id, (ARRAY['Analista de Datos','Desarrollador Backend','Ejecutivo Comercial','Contador','Enfermera','Jefe de Tienda','Diseñador UX','Asistente de RR. HH.','Barista','Analista de Logística'])[v],
       'Descripción de la vacante de carga.', 'Lima', 'hibrido', (ARRAY['abierta','abierta','abierta','en_revision','cerrada'])[1 + (v % 5)],
       now() - (v || ' days')::interval, now() - (v + 1 || ' days')::interval, now()
FROM empresas e JOIN users u ON u.empresa_id = e.id AND u.email LIKE '%@carga.test', generate_series(1, 10) v
WHERE e.nombre LIKE 'Empresa de carga %';

INSERT INTO requisitos (empresa_id, vacante_id, competencia_id, tipo, peso, orden, embedding, embedding_modelo, created_at, updated_at)
SELECT va.empresa_id, va.id, c.id, 'obligatorio', 3, c.rn, (SELECT embedding FROM requisitos WHERE embedding IS NOT NULL LIMIT 1), 'm', now(), now()
FROM vacantes va
CROSS JOIN LATERAL (SELECT id, row_number() OVER () AS rn FROM competencias ORDER BY random() LIMIT 6) c
WHERE va.titulo IN ('Analista de Datos','Desarrollador Backend','Ejecutivo Comercial','Contador','Enfermera','Jefe de Tienda','Diseñador UX','Asistente de RR. HH.','Barista','Analista de Logística')
  AND va.empresa_id IN (SELECT id FROM empresas WHERE nombre LIKE 'Empresa de carga %');

INSERT INTO candidatos (nombre, email, titular, consentimiento_at, created_at, updated_at)
SELECT (ARRAY['María','José','Lucía','Carlos','Rosa','Jorge','Ana','Luis','Sofía','Miguel'])[1 + g % 10] || ' ' ||
       (ARRAY['Quispe','Flores','Sánchez','Ramírez','Torres','Huamán','Mendoza','Rojas','Vargas','Castillo'])[1 + (g / 10) % 10] || ' ' || g,
       'candidato' || g || '@carga.test', 'Titular ' || (g % 50), now() - interval '150 days', now() - interval '150 days', now()
FROM generate_series(1, 100000) g;

INSERT INTO cvs (candidato_id, disco, ruta, nombre_original, mime, tamano, texto_extraido, estado_procesamiento, procesado_at, created_at, updated_at)
SELECT c.id, 'cvs', 'carga/' || c.id || '.pdf', 'cv.pdf', 'application/pdf', 1000, repeat('Experiencia con Java y SQL. ', 80), 'procesado', now(), c.created_at, now()
FROM candidatos c WHERE c.email LIKE '%@carga.test';

-- Tres postulaciones por candidato, en vacantes de carga al azar.
WITH vacs AS (SELECT array_agg(id ORDER BY id) AS ids, array_agg(empresa_id ORDER BY id) AS emps FROM vacantes WHERE titulo IS NOT NULL AND empresa_id IN (SELECT id FROM empresas WHERE nombre LIKE 'Empresa de carga %'))
INSERT INTO postulaciones (empresa_id, vacante_id, candidato_id, cv_id, etapa, orden, etapa_cambiada_at, match_score, match_detalle, match_motor, match_calculado_at, created_at, updated_at, origen)
SELECT DISTINCT ON (vacs.ids[i], c.id) vacs.emps[i], vacs.ids[i], c.id, cv.id,
       (ARRAY['postulado','en_revision','entrevista','oferta','contratado','descartado'])[1 + (c.id + k) % 6], 0,
       now() - ((c.id + k) % 120 || ' days')::interval, 30 + (c.id * 7 + k) % 70,
       (SELECT match_detalle FROM postulaciones WHERE match_detalle IS NOT NULL LIMIT 1), 'http',
       now() - ((c.id + k) % 120 || ' days')::interval, now() - ((c.id + k) % 120 || ' days')::interval, now(), 'equipo'
FROM candidatos c
JOIN cvs cv ON cv.candidato_id = c.id
CROSS JOIN generate_series(1, 3) k
CROSS JOIN vacs
CROSS JOIN LATERAL (SELECT 1 + ((c.id * 31 + k * 977) % array_length(vacs.ids, 1)) AS i) x
WHERE c.email LIKE '%@carga.test'
ON CONFLICT DO NOTHING;

INSERT INTO postulacion_etapas (empresa_id, postulacion_id, etapa, desde)
SELECT p.empresa_id, p.id, e.etapa, p.created_at + (e.n || ' days')::interval
FROM postulaciones p
CROSS JOIN LATERAL (VALUES ('postulado', 0), ('en_revision', 2)) e(etapa, n)
WHERE p.origen = 'equipo' AND p.candidato_id IN (SELECT id FROM candidatos WHERE email LIKE '%@carga.test');

INSERT INTO actividades (empresa_id, actor_id, tipo, descripcion, sujeto_type, sujeto_id, created_at, updated_at)
SELECT p.empresa_id, NULL, 'postulacion.creada', 'Carga: postulación ' || p.id, 'App\Models\Postulacion', p.id, p.created_at, p.created_at
FROM postulaciones p WHERE p.candidato_id IN (SELECT id FROM candidatos WHERE email LIKE '%@carga.test');

INSERT INTO entrevistas (empresa_id, postulacion_id, entrevistador_id, inicia_at, duracion_min, modalidad, estado, created_at, updated_at)
SELECT p.empresa_id, p.id, u.id, p.created_at + interval '5 days', 45, 'videollamada', 'completada', p.created_at, p.created_at
FROM postulaciones p JOIN users u ON u.empresa_id = p.empresa_id AND u.email LIKE '%@carga.test'
WHERE p.etapa IN ('entrevista', 'oferta', 'contratado');

INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at)
SELECT gen_random_uuid(), 'App\Notifications\CandidatoSePostuloAviso', 'App\Models\User', u.id, '{"texto":"Carga"}',
       CASE WHEN g % 3 = 0 THEN NULL ELSE now() END, now() - (g || ' minutes')::interval, now()
FROM users u CROSS JOIN generate_series(1, 1000) g WHERE u.email LIKE '%@carga.test';

ANALYZE;
SELECT relname, n_live_tup FROM pg_stat_user_tables WHERE n_live_tup > 1000 ORDER BY n_live_tup DESC;
