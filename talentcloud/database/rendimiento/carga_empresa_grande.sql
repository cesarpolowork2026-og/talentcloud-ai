-- Una sola empresa muy grande: 10 000 vacantes, ~100 000 postulaciones repartidas y UNA vacante con
-- 15 000 postulaciones. Para medir qué pasa cuando una empresa (Nimbus Corp, id 1) crece mucho.
-- Nunca en la base de la aplicación: se usa una copia.
--
--   docker exec talentcloud-pgsql psql -U postgres -c "CREATE DATABASE talentcloud_carga"
--   docker exec talentcloud-pgsql sh -c "pg_dump -U postgres talentcloud | psql -U postgres -d talentcloud_carga -q"
--   docker exec -i talentcloud-pgsql psql -U postgres -d talentcloud_carga < database/rendimiento/carga_empresa_grande.sql
--   DB_DATABASE=talentcloud_carga php artisan ...      (y al terminar: DROP DATABASE talentcloud_carga)
\timing off
SET client_min_messages = warning;

-- 10 000 vacantes de Nimbus (la 1 es la «grande»), 6 requisitos cada una.
INSERT INTO vacantes (empresa_id, creada_por, titulo, descripcion, ubicacion, modalidad, estado, publicada_at, created_at, updated_at)
SELECT 1, 3, (ARRAY['Analista de Datos','Desarrollador Backend','Ejecutivo Comercial','Contador','Enfermera','Jefe de Tienda','Diseñador UX','Asistente de RR. HH.','Barista','Analista de Logística'])[1 + v % 10] || ' #' || v,
       'Descripción de la vacante de carga.', 'Lima', 'hibrido',
       (ARRAY['abierta','abierta','abierta','en_revision','cerrada'])[1 + (v % 5)],
       now() - ((v % 300) || ' days')::interval, now() - ((v % 300) + 1 || ' days')::interval, now()
FROM generate_series(1, 10000) v;

INSERT INTO requisitos (empresa_id, vacante_id, competencia_id, tipo, peso, orden, embedding, embedding_modelo, created_at, updated_at)
SELECT va.empresa_id, va.id, c.id, CASE WHEN c.rn <= 3 THEN 'obligatorio' ELSE 'deseable' END, 1 + c.rn % 5, c.rn,
       (SELECT embedding FROM requisitos WHERE embedding IS NOT NULL LIMIT 1), 'm', now(), now()
FROM vacantes va
CROSS JOIN LATERAL (SELECT id, row_number() OVER () AS rn FROM competencias ORDER BY random() LIMIT 6) c
WHERE va.titulo LIKE '% #%' AND va.empresa_id = 1;

-- 15 000 candidatos con CV.
INSERT INTO candidatos (nombre, email, titular, consentimiento_at, created_at, updated_at)
SELECT 'Candidato ' || g, 'grande' || g || '@carga.test', 'Titular ' || (g % 50), now() - interval '150 days', now() - interval '150 days', now()
FROM generate_series(1, 15000) g;

INSERT INTO cvs (candidato_id, disco, ruta, nombre_original, mime, tamano, texto_extraido, estado_procesamiento, procesado_at, created_at, updated_at)
SELECT c.id, 'cvs', 'carga/' || c.id || '.pdf', 'cv.pdf', 'application/pdf', 1000, repeat('Experiencia con Java y SQL. ', 80), 'procesado', now(), c.created_at, now()
FROM candidatos c WHERE c.email LIKE 'grande%@carga.test';

-- La vacante «grande» (la de menor id de la carga) recibe a los 15 000.
WITH grande AS (SELECT min(id) AS id FROM vacantes WHERE titulo LIKE '% #1' AND empresa_id = 1)
INSERT INTO postulaciones (empresa_id, vacante_id, candidato_id, cv_id, etapa, orden, etapa_cambiada_at, match_score, match_detalle, match_motor, match_calculado_at, created_at, updated_at, origen)
SELECT 1, grande.id, c.id, cv.id,
       (ARRAY['postulado','postulado','postulado','en_revision','entrevista','oferta','contratado','descartado'])[1 + c.id % 8], c.id % 50,
       now() - (c.id % 120 || ' days')::interval, 20 + (c.id * 7) % 80,
       (SELECT match_detalle FROM postulaciones WHERE match_detalle IS NOT NULL LIMIT 1), 'http',
       now() - (c.id % 120 || ' days')::interval, now() - (c.id % 120 || ' days')::interval, now(), 'postulante'
FROM candidatos c JOIN cvs cv ON cv.candidato_id = c.id CROSS JOIN grande
WHERE c.email LIKE 'grande%@carga.test';

-- ~10 postulaciones en cada una de las demás vacantes de carga.
WITH vacs AS (SELECT array_agg(id ORDER BY id) AS ids FROM vacantes WHERE titulo LIKE '% #%' AND empresa_id = 1 AND titulo NOT LIKE '% #1')
INSERT INTO postulaciones (empresa_id, vacante_id, candidato_id, cv_id, etapa, orden, etapa_cambiada_at, match_score, match_detalle, match_motor, match_calculado_at, created_at, updated_at, origen)
SELECT DISTINCT ON (vacs.ids[i], c.id) 1, vacs.ids[i], c.id, cv.id,
       (ARRAY['postulado','postulado','en_revision','entrevista','oferta','contratado','descartado'])[1 + (c.id + k) % 7], 0,
       now() - ((c.id + k) % 120 || ' days')::interval, 20 + (c.id * 7 + k) % 80,
       (SELECT match_detalle FROM postulaciones WHERE match_detalle IS NOT NULL LIMIT 1), 'http',
       now() - ((c.id + k) % 120 || ' days')::interval, now() - ((c.id + k) % 120 || ' days')::interval, now(), 'equipo'
FROM candidatos c JOIN cvs cv ON cv.candidato_id = c.id
CROSS JOIN generate_series(1, 7) k CROSS JOIN vacs
CROSS JOIN LATERAL (SELECT 1 + ((c.id * 31 + k * 977) % array_length(vacs.ids, 1)) AS i) x
WHERE c.email LIKE 'grande%@carga.test'
ON CONFLICT DO NOTHING;

INSERT INTO postulacion_etapas (empresa_id, postulacion_id, etapa, desde)
SELECT p.empresa_id, p.id, e.etapa, p.created_at + (e.n || ' days')::interval
FROM postulaciones p CROSS JOIN LATERAL (VALUES ('postulado', 0), ('en_revision', 2)) e(etapa, n)
WHERE p.candidato_id IN (SELECT id FROM candidatos WHERE email LIKE 'grande%@carga.test');

INSERT INTO entrevistas (empresa_id, postulacion_id, entrevistador_id, inicia_at, duracion_min, modalidad, estado, created_at, updated_at)
SELECT p.empresa_id, p.id, 3, p.created_at + interval '5 days', 45, 'videollamada', 'completada', p.created_at, p.created_at
FROM postulaciones p WHERE p.etapa IN ('entrevista', 'oferta', 'contratado') AND p.candidato_id IN (SELECT id FROM candidatos WHERE email LIKE 'grande%@carga.test');

INSERT INTO actividades (empresa_id, actor_id, tipo, descripcion, sujeto_type, sujeto_id, created_at, updated_at)
SELECT p.empresa_id, NULL, 'postulacion.registrada', 'Carga: postulación ' || p.id, 'App\Models\Postulacion', p.id, p.created_at, p.created_at
FROM postulaciones p WHERE p.candidato_id IN (SELECT id FROM candidatos WHERE email LIKE 'grande%@carga.test');

ANALYZE;
SELECT relname, n_live_tup FROM pg_stat_user_tables WHERE n_live_tup > 5000 ORDER BY n_live_tup DESC;
