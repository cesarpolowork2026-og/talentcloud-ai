--
-- PostgreSQL database dump
--


-- Dumped from database version 17.11 (Debian 17.11-1.pgdg12+2)
-- Dumped by pg_dump version 17.11 (Debian 17.11-1.pgdg12+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: unaccent; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;


--
-- Name: EXTENSION unaccent; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION unaccent IS 'text search dictionary that removes accents';


--
-- Name: vector; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA public;


--
-- Name: EXTENSION vector; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION vector IS 'vector data type and ivfflat and hnsw access methods';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: actividades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.actividades (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    actor_id bigint,
    tipo character varying(60) NOT NULL,
    descripcion character varying(255) NOT NULL,
    sujeto_type character varying(255),
    sujeto_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT actividades_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['postulacion.etapa'::character varying, 'cv.analizado'::character varying, 'evaluacion.aprobada'::character varying, 'entrevista.programada'::character varying, 'entrevista.cancelada'::character varying, 'entrevista.reprogramada'::character varying, 'postulacion.nota'::character varying, 'vacante.creada'::character varying, 'postulacion.propia'::character varying, 'postulacion.registrada'::character varying, 'empresa.plan'::character varying, 'empresa.plan_solicitado'::character varying, 'postulacion.retirada'::character varying])::text[])))
);


--
-- Name: actividades_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.actividades_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: actividades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.actividades_id_seq OWNED BY public.actividades.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: candidato_competencia; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.candidato_competencia (
    id bigint NOT NULL,
    candidato_id bigint NOT NULL,
    competencia_id bigint NOT NULL,
    fuente character varying(20) DEFAULT 'manual'::character varying NOT NULL,
    anios numeric(4,1),
    evidencia text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    empresa_id bigint,
    CONSTRAINT candidato_competencia_anios_check CHECK (((anios IS NULL) OR (anios >= (0)::numeric))),
    CONSTRAINT candidato_competencia_fuente_check CHECK (((fuente)::text = ANY ((ARRAY['manual'::character varying, 'extraccion'::character varying])::text[])))
);


--
-- Name: candidato_competencia_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.candidato_competencia_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: candidato_competencia_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.candidato_competencia_id_seq OWNED BY public.candidato_competencia.id;


--
-- Name: candidatos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.candidatos (
    id bigint NOT NULL,
    user_id bigint,
    empresa_origen_id bigint,
    nombre character varying(255) NOT NULL,
    email character varying(255),
    telefono character varying(40),
    titular character varying(255),
    consentimiento_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: candidatos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.candidatos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: candidatos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.candidatos_id_seq OWNED BY public.candidatos.id;


--
-- Name: competencias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.competencias (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    nombre_normalizado character varying(255) NOT NULL,
    categoria character varying(255),
    sinonimos jsonb DEFAULT '[]'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: competencias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.competencias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: competencias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.competencias_id_seq OWNED BY public.competencias.id;


--
-- Name: cv_fragmentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cv_fragmentos (
    id bigint NOT NULL,
    cv_id bigint NOT NULL,
    seccion character varying(40),
    orden smallint DEFAULT '0'::smallint NOT NULL,
    texto text NOT NULL,
    embedding public.vector(384),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    embedding_modelo character varying(120)
);


--
-- Name: cv_fragmentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cv_fragmentos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cv_fragmentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cv_fragmentos_id_seq OWNED BY public.cv_fragmentos.id;


--
-- Name: cvs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cvs (
    id bigint NOT NULL,
    candidato_id bigint NOT NULL,
    subido_por bigint,
    disco character varying(40) NOT NULL,
    ruta character varying(255) NOT NULL,
    nombre_original character varying(255) NOT NULL,
    mime character varying(120) NOT NULL,
    tamano integer NOT NULL,
    texto_extraido text,
    datos_estructurados jsonb,
    estado_procesamiento character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    error text,
    procesado_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT cvs_estado_procesamiento_check CHECK (((estado_procesamiento)::text = ANY ((ARRAY['pendiente'::character varying, 'procesando'::character varying, 'procesado'::character varying, 'error'::character varying])::text[]))),
    CONSTRAINT cvs_tamano_check CHECK ((tamano >= 0))
);


--
-- Name: cvs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cvs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cvs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cvs_id_seq OWNED BY public.cvs.id;


--
-- Name: empresas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresas (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    sector character varying(255),
    sitio_web character varying(255),
    descripcion text,
    plan character varying(20) DEFAULT 'gratis'::character varying NOT NULL,
    estado character varying(20) DEFAULT 'activa'::character varying NOT NULL,
    onboarding jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    prueba_hasta timestamp(0) without time zone,
    plan_solicitado character varying(20),
    plan_solicitado_at timestamp(0) without time zone,
    pasarela_cliente_id character varying(255),
    pasarela_suscripcion_id character varying(255),
    suscripcion_estado character varying(20),
    suscripcion_periodicidad character varying(10),
    suscripcion_renueva_at timestamp(0) without time zone,
    suscripcion_cancela_al_final boolean DEFAULT false NOT NULL,
    CONSTRAINT empresas_estado_check CHECK (((estado)::text = ANY ((ARRAY['activa'::character varying, 'suspendida'::character varying])::text[]))),
    CONSTRAINT empresas_plan_check CHECK (((plan)::text = ANY ((ARRAY['gratis'::character varying, 'starter'::character varying, 'pro'::character varying, 'enterprise'::character varying])::text[]))),
    CONSTRAINT empresas_plan_solicitado_check CHECK (((plan_solicitado IS NULL) OR ((plan_solicitado)::text = ANY ((ARRAY['gratis'::character varying, 'starter'::character varying, 'pro'::character varying, 'enterprise'::character varying])::text[])))),
    CONSTRAINT empresas_suscripcion_estado_check CHECK (((suscripcion_estado IS NULL) OR ((suscripcion_estado)::text = ANY ((ARRAY['activa'::character varying, 'pago_pendiente'::character varying, 'incompleta'::character varying, 'terminada'::character varying])::text[])))),
    CONSTRAINT empresas_suscripcion_periodicidad_check CHECK (((suscripcion_periodicidad IS NULL) OR ((suscripcion_periodicidad)::text = ANY ((ARRAY['mensual'::character varying, 'anual'::character varying])::text[]))))
);


--
-- Name: empresas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: empresas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresas_id_seq OWNED BY public.empresas.id;


--
-- Name: entrevistas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.entrevistas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    postulacion_id bigint NOT NULL,
    entrevistador_id bigint NOT NULL,
    inicia_at timestamp(0) without time zone NOT NULL,
    duracion_min smallint DEFAULT '45'::smallint NOT NULL,
    modalidad character varying(20) DEFAULT 'videollamada'::character varying NOT NULL,
    enlace character varying(255),
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    notas text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT entrevistas_duracion_check CHECK ((duracion_min > 0)),
    CONSTRAINT entrevistas_estado_check CHECK (((estado)::text = ANY ((ARRAY['pendiente'::character varying, 'confirmada'::character varying, 'completada'::character varying, 'cancelada'::character varying])::text[]))),
    CONSTRAINT entrevistas_modalidad_check CHECK (((modalidad)::text = ANY ((ARRAY['videollamada'::character varying, 'presencial'::character varying, 'telefonica'::character varying])::text[])))
);


--
-- Name: entrevistas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.entrevistas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: entrevistas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.entrevistas_id_seq OWNED BY public.entrevistas.id;


--
-- Name: evaluaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.evaluaciones (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    postulacion_id bigint NOT NULL,
    evaluador_id bigint NOT NULL,
    criterios jsonb DEFAULT '[]'::jsonb NOT NULL,
    puntaje numeric(3,1),
    comentario text,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT evaluaciones_estado_check CHECK (((estado)::text = ANY ((ARRAY['pendiente'::character varying, 'en_revision'::character varying, 'aprobado'::character varying, 'rechazado'::character varying])::text[]))),
    CONSTRAINT evaluaciones_puntaje_check CHECK (((puntaje IS NULL) OR ((puntaje >= (0)::numeric) AND (puntaje <= (10)::numeric))))
);


--
-- Name: evaluaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.evaluaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: evaluaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.evaluaciones_id_seq OWNED BY public.evaluaciones.id;


--
-- Name: eventos_de_pago; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.eventos_de_pago (
    id character varying(255) NOT NULL,
    tipo character varying(255) NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: invitaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invitaciones (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    invitada_por bigint,
    email character varying(255) NOT NULL,
    rol character varying(20) DEFAULT 'reclutador'::character varying NOT NULL,
    token_hash character varying(64) NOT NULL,
    expira_at timestamp(0) without time zone NOT NULL,
    aceptada_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    nombre character varying(255),
    CONSTRAINT invitaciones_rol_check CHECK (((rol)::text = ANY ((ARRAY['admin'::character varying, 'empresa'::character varying, 'reclutador'::character varying, 'postulante'::character varying])::text[])))
);


--
-- Name: invitaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.invitaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invitaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invitaciones_id_seq OWNED BY public.invitaciones.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: match_feedback; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.match_feedback (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    postulacion_id bigint NOT NULL,
    requisito_id bigint NOT NULL,
    user_id bigint,
    comentario text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: match_feedback_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.match_feedback_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: match_feedback_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.match_feedback_id_seq OWNED BY public.match_feedback.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: notas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    postulacion_id bigint NOT NULL,
    autor_id bigint,
    texto text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: notas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notas_id_seq OWNED BY public.notas.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: postulacion_etapas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.postulacion_etapas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    postulacion_id bigint NOT NULL,
    etapa character varying(20) NOT NULL,
    desde timestamp(0) without time zone NOT NULL,
    CONSTRAINT postulacion_etapas_etapa_check CHECK (((etapa)::text = ANY ((ARRAY['postulado'::character varying, 'en_revision'::character varying, 'entrevista'::character varying, 'oferta'::character varying, 'contratado'::character varying, 'descartado'::character varying])::text[])))
);


--
-- Name: postulacion_etapas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.postulacion_etapas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: postulacion_etapas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.postulacion_etapas_id_seq OWNED BY public.postulacion_etapas.id;


--
-- Name: postulaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.postulaciones (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    vacante_id bigint NOT NULL,
    candidato_id bigint NOT NULL,
    cv_id bigint,
    etapa character varying(20) DEFAULT 'postulado'::character varying NOT NULL,
    orden integer DEFAULT 0 NOT NULL,
    etapa_cambiada_at timestamp(0) without time zone,
    match_score numeric(5,2),
    match_detalle jsonb,
    match_motor character varying(10),
    match_calculado_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    estado_avisado character varying(20),
    origen character varying(20) DEFAULT 'equipo'::character varying NOT NULL,
    retirada_at timestamp(0) without time zone,
    ia_cupo_at timestamp(0) without time zone,
    ia_sin_cupo boolean DEFAULT false NOT NULL,
    requisitos_total smallint,
    requisitos_cubiertos smallint,
    obligatorios_faltantes smallint,
    CONSTRAINT postulaciones_estado_avisado_check CHECK (((estado_avisado IS NULL) OR ((estado_avisado)::text = ANY ((ARRAY['postulado'::character varying, 'en_revision'::character varying, 'entrevista'::character varying, 'contratado'::character varying, 'no_seleccionado'::character varying, 'cerrada'::character varying, 'retirada'::character varying])::text[])))),
    CONSTRAINT postulaciones_etapa_check CHECK (((etapa)::text = ANY ((ARRAY['postulado'::character varying, 'en_revision'::character varying, 'entrevista'::character varying, 'oferta'::character varying, 'contratado'::character varying, 'descartado'::character varying])::text[]))),
    CONSTRAINT postulaciones_match_motor_check CHECK (((match_motor IS NULL) OR ((match_motor)::text = ANY ((ARRAY['http'::character varying, 'fake'::character varying])::text[])))),
    CONSTRAINT postulaciones_match_score_check CHECK (((match_score IS NULL) OR ((match_score >= (0)::numeric) AND (match_score <= (100)::numeric)))),
    CONSTRAINT postulaciones_orden_check CHECK ((orden >= 0)),
    CONSTRAINT postulaciones_origen_check CHECK (((origen)::text = ANY ((ARRAY['equipo'::character varying, 'postulante'::character varying])::text[]))),
    CONSTRAINT postulaciones_resumen_check CHECK (((requisitos_total IS NULL) OR ((requisitos_total >= 0) AND ((requisitos_cubiertos >= 0) AND (requisitos_cubiertos <= requisitos_total)) AND ((obligatorios_faltantes >= 0) AND (obligatorios_faltantes <= requisitos_total)))))
);


--
-- Name: postulaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.postulaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: postulaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.postulaciones_id_seq OWNED BY public.postulaciones.id;


--
-- Name: reclamaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reclamaciones (
    id bigint NOT NULL,
    numero integer NOT NULL,
    tipo character varying(10) NOT NULL,
    estado character varying(15) DEFAULT 'pendiente'::character varying NOT NULL,
    nombre character varying(255) NOT NULL,
    tipo_documento character varying(10) NOT NULL,
    numero_documento character varying(15) NOT NULL,
    domicilio character varying(255) NOT NULL,
    telefono character varying(20),
    email character varying(255) NOT NULL,
    es_menor boolean DEFAULT false NOT NULL,
    apoderado_nombre character varying(255),
    apoderado_tipo_documento character varying(10),
    apoderado_numero_documento character varying(15),
    servicio character varying(255) NOT NULL,
    monto numeric(10,2),
    detalle text NOT NULL,
    pedido text NOT NULL,
    fecha_limite date NOT NULL,
    respuesta text,
    respondida_at timestamp(0) without time zone,
    respondida_por bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT reclamaciones_apoderado_tipo_documento_check CHECK (((apoderado_tipo_documento IS NULL) OR ((apoderado_tipo_documento)::text = ANY ((ARRAY['dni'::character varying, 'ce'::character varying, 'pasaporte'::character varying])::text[])))),
    CONSTRAINT reclamaciones_estado_check CHECK (((estado)::text = ANY ((ARRAY['pendiente'::character varying, 'respondida'::character varying])::text[]))),
    CONSTRAINT reclamaciones_monto_check CHECK (((monto IS NULL) OR (monto >= (0)::numeric))),
    CONSTRAINT reclamaciones_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['reclamo'::character varying, 'queja'::character varying])::text[]))),
    CONSTRAINT reclamaciones_tipo_documento_check CHECK (((tipo_documento)::text = ANY ((ARRAY['dni'::character varying, 'ce'::character varying, 'pasaporte'::character varying])::text[])))
);


--
-- Name: reclamaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reclamaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reclamaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reclamaciones_id_seq OWNED BY public.reclamaciones.id;


--
-- Name: requisitos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.requisitos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    vacante_id bigint NOT NULL,
    competencia_id bigint NOT NULL,
    descripcion character varying(255),
    tipo character varying(20) DEFAULT 'obligatorio'::character varying NOT NULL,
    peso smallint DEFAULT '3'::smallint NOT NULL,
    anios_minimos smallint,
    orden smallint DEFAULT '0'::smallint NOT NULL,
    embedding public.vector(384),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    embedding_modelo character varying(120),
    CONSTRAINT requisitos_anios_minimos_check CHECK (((anios_minimos IS NULL) OR ((anios_minimos >= 0) AND (anios_minimos <= 40)))),
    CONSTRAINT requisitos_peso_check CHECK (((peso >= 1) AND (peso <= 5))),
    CONSTRAINT requisitos_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['obligatorio'::character varying, 'deseable'::character varying])::text[])))
);


--
-- Name: requisitos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.requisitos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: requisitos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.requisitos_id_seq OWNED BY public.requisitos.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    notificaciones_email boolean DEFAULT true NOT NULL,
    empresa_id bigint,
    desactivado_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: vacantes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vacantes (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    creada_por bigint,
    titulo character varying(255) NOT NULL,
    descripcion text,
    ubicacion character varying(255),
    modalidad character varying(20) DEFAULT 'remoto'::character varying NOT NULL,
    estado character varying(20) DEFAULT 'borrador'::character varying NOT NULL,
    publicada_at timestamp(0) without time zone,
    cerrada_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT vacantes_estado_check CHECK (((estado)::text = ANY ((ARRAY['borrador'::character varying, 'abierta'::character varying, 'en_revision'::character varying, 'cerrada'::character varying])::text[]))),
    CONSTRAINT vacantes_modalidad_check CHECK (((modalidad)::text = ANY ((ARRAY['remoto'::character varying, 'presencial'::character varying, 'hibrido'::character varying])::text[]))),
    CONSTRAINT vacantes_ubicacion_check CHECK ((((modalidad)::text = 'remoto'::text) OR ((ubicacion IS NOT NULL) AND (btrim((ubicacion)::text) <> ''::text))))
);


--
-- Name: vacantes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vacantes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vacantes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vacantes_id_seq OWNED BY public.vacantes.id;


--
-- Name: actividades id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actividades ALTER COLUMN id SET DEFAULT nextval('public.actividades_id_seq'::regclass);


--
-- Name: candidato_competencia id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidato_competencia ALTER COLUMN id SET DEFAULT nextval('public.candidato_competencia_id_seq'::regclass);


--
-- Name: candidatos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidatos ALTER COLUMN id SET DEFAULT nextval('public.candidatos_id_seq'::regclass);


--
-- Name: competencias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencias ALTER COLUMN id SET DEFAULT nextval('public.competencias_id_seq'::regclass);


--
-- Name: cv_fragmentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cv_fragmentos ALTER COLUMN id SET DEFAULT nextval('public.cv_fragmentos_id_seq'::regclass);


--
-- Name: cvs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cvs ALTER COLUMN id SET DEFAULT nextval('public.cvs_id_seq'::regclass);


--
-- Name: empresas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas ALTER COLUMN id SET DEFAULT nextval('public.empresas_id_seq'::regclass);


--
-- Name: entrevistas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrevistas ALTER COLUMN id SET DEFAULT nextval('public.entrevistas_id_seq'::regclass);


--
-- Name: evaluaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evaluaciones ALTER COLUMN id SET DEFAULT nextval('public.evaluaciones_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: invitaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitaciones ALTER COLUMN id SET DEFAULT nextval('public.invitaciones_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: match_feedback id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_feedback ALTER COLUMN id SET DEFAULT nextval('public.match_feedback_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: notas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas ALTER COLUMN id SET DEFAULT nextval('public.notas_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: postulacion_etapas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulacion_etapas ALTER COLUMN id SET DEFAULT nextval('public.postulacion_etapas_id_seq'::regclass);


--
-- Name: postulaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulaciones ALTER COLUMN id SET DEFAULT nextval('public.postulaciones_id_seq'::regclass);


--
-- Name: reclamaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reclamaciones ALTER COLUMN id SET DEFAULT nextval('public.reclamaciones_id_seq'::regclass);


--
-- Name: requisitos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requisitos ALTER COLUMN id SET DEFAULT nextval('public.requisitos_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: vacantes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacantes ALTER COLUMN id SET DEFAULT nextval('public.vacantes_id_seq'::regclass);


--
-- Name: actividades actividades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actividades
    ADD CONSTRAINT actividades_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: candidato_competencia candidato_competencia_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidato_competencia
    ADD CONSTRAINT candidato_competencia_pkey PRIMARY KEY (id);


--
-- Name: candidatos candidatos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidatos
    ADD CONSTRAINT candidatos_pkey PRIMARY KEY (id);


--
-- Name: candidatos candidatos_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidatos
    ADD CONSTRAINT candidatos_user_id_unique UNIQUE (user_id);


--
-- Name: competencias competencias_nombre_normalizado_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencias
    ADD CONSTRAINT competencias_nombre_normalizado_unique UNIQUE (nombre_normalizado);


--
-- Name: competencias competencias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencias
    ADD CONSTRAINT competencias_pkey PRIMARY KEY (id);


--
-- Name: cv_fragmentos cv_fragmentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cv_fragmentos
    ADD CONSTRAINT cv_fragmentos_pkey PRIMARY KEY (id);


--
-- Name: cvs cvs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cvs
    ADD CONSTRAINT cvs_pkey PRIMARY KEY (id);


--
-- Name: empresas empresas_pasarela_cliente_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_pasarela_cliente_id_unique UNIQUE (pasarela_cliente_id);


--
-- Name: empresas empresas_pasarela_suscripcion_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_pasarela_suscripcion_id_unique UNIQUE (pasarela_suscripcion_id);


--
-- Name: empresas empresas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_pkey PRIMARY KEY (id);


--
-- Name: entrevistas entrevistas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrevistas
    ADD CONSTRAINT entrevistas_pkey PRIMARY KEY (id);


--
-- Name: evaluaciones evaluaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evaluaciones
    ADD CONSTRAINT evaluaciones_pkey PRIMARY KEY (id);


--
-- Name: evaluaciones evaluaciones_postulacion_id_evaluador_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evaluaciones
    ADD CONSTRAINT evaluaciones_postulacion_id_evaluador_id_unique UNIQUE (postulacion_id, evaluador_id);


--
-- Name: eventos_de_pago eventos_de_pago_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.eventos_de_pago
    ADD CONSTRAINT eventos_de_pago_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: invitaciones invitaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitaciones
    ADD CONSTRAINT invitaciones_pkey PRIMARY KEY (id);


--
-- Name: invitaciones invitaciones_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitaciones
    ADD CONSTRAINT invitaciones_token_hash_unique UNIQUE (token_hash);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: match_feedback match_feedback_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_feedback
    ADD CONSTRAINT match_feedback_pkey PRIMARY KEY (id);


--
-- Name: match_feedback match_feedback_postulacion_id_requisito_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_feedback
    ADD CONSTRAINT match_feedback_postulacion_id_requisito_id_user_id_unique UNIQUE (postulacion_id, requisito_id, user_id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: notas notas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas
    ADD CONSTRAINT notas_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: postulacion_etapas postulacion_etapas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulacion_etapas
    ADD CONSTRAINT postulacion_etapas_pkey PRIMARY KEY (id);


--
-- Name: postulaciones postulaciones_empresa_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulaciones
    ADD CONSTRAINT postulaciones_empresa_id_id_unique UNIQUE (empresa_id, id);


--
-- Name: postulaciones postulaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulaciones
    ADD CONSTRAINT postulaciones_pkey PRIMARY KEY (id);


--
-- Name: postulaciones postulaciones_vacante_id_candidato_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulaciones
    ADD CONSTRAINT postulaciones_vacante_id_candidato_id_unique UNIQUE (vacante_id, candidato_id);


--
-- Name: reclamaciones reclamaciones_numero_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reclamaciones
    ADD CONSTRAINT reclamaciones_numero_unique UNIQUE (numero);


--
-- Name: reclamaciones reclamaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reclamaciones
    ADD CONSTRAINT reclamaciones_pkey PRIMARY KEY (id);


--
-- Name: requisitos requisitos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requisitos
    ADD CONSTRAINT requisitos_pkey PRIMARY KEY (id);


--
-- Name: requisitos requisitos_vacante_id_competencia_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requisitos
    ADD CONSTRAINT requisitos_vacante_id_competencia_id_unique UNIQUE (vacante_id, competencia_id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_empresa_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_empresa_id_id_unique UNIQUE (empresa_id, id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: vacantes vacantes_empresa_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacantes
    ADD CONSTRAINT vacantes_empresa_id_id_unique UNIQUE (empresa_id, id);


--
-- Name: vacantes vacantes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacantes
    ADD CONSTRAINT vacantes_pkey PRIMARY KEY (id);


--
-- Name: actividades_actor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX actividades_actor_id_index ON public.actividades USING btree (actor_id);


--
-- Name: actividades_empresa_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX actividades_empresa_id_created_at_index ON public.actividades USING btree (empresa_id, created_at);


--
-- Name: actividades_sujeto_type_sujeto_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX actividades_sujeto_type_sujeto_id_index ON public.actividades USING btree (sujeto_type, sujeto_id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: candidato_competencia_competencia_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX candidato_competencia_competencia_id_index ON public.candidato_competencia USING btree (competencia_id);


--
-- Name: candidato_competencia_empresa_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX candidato_competencia_empresa_id_index ON public.candidato_competencia USING btree (empresa_id);


--
-- Name: candidato_competencia_unica; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX candidato_competencia_unica ON public.candidato_competencia USING btree (candidato_id, competencia_id, fuente, empresa_id) NULLS NOT DISTINCT;


--
-- Name: candidatos_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX candidatos_email_index ON public.candidatos USING btree (email);


--
-- Name: candidatos_empresa_origen_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX candidatos_empresa_origen_id_index ON public.candidatos USING btree (empresa_origen_id);


--
-- Name: cv_fragmentos_cv_id_orden_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cv_fragmentos_cv_id_orden_index ON public.cv_fragmentos USING btree (cv_id, orden);


--
-- Name: cvs_candidato_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cvs_candidato_id_created_at_index ON public.cvs USING btree (candidato_id, created_at);


--
-- Name: cvs_subido_por_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cvs_subido_por_index ON public.cvs USING btree (subido_por);


--
-- Name: empresas_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX empresas_estado_index ON public.empresas USING btree (estado);


--
-- Name: entrevistas_empresa_id_inicia_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX entrevistas_empresa_id_inicia_at_index ON public.entrevistas USING btree (empresa_id, inicia_at);


--
-- Name: entrevistas_entrevistador_id_inicia_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX entrevistas_entrevistador_id_inicia_at_index ON public.entrevistas USING btree (entrevistador_id, inicia_at);


--
-- Name: entrevistas_postulacion_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX entrevistas_postulacion_id_index ON public.entrevistas USING btree (postulacion_id);


--
-- Name: evaluaciones_empresa_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX evaluaciones_empresa_id_estado_index ON public.evaluaciones USING btree (empresa_id, estado);


--
-- Name: evaluaciones_evaluador_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX evaluaciones_evaluador_id_index ON public.evaluaciones USING btree (evaluador_id);


--
-- Name: invitaciones_empresa_id_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invitaciones_empresa_id_email_index ON public.invitaciones USING btree (empresa_id, email);


--
-- Name: invitaciones_invitada_por_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invitaciones_invitada_por_index ON public.invitaciones USING btree (invitada_por);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: match_feedback_empresa_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX match_feedback_empresa_id_index ON public.match_feedback USING btree (empresa_id);


--
-- Name: match_feedback_requisito_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX match_feedback_requisito_id_index ON public.match_feedback USING btree (requisito_id);


--
-- Name: match_feedback_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX match_feedback_user_id_index ON public.match_feedback USING btree (user_id);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: notas_autor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notas_autor_id_index ON public.notas USING btree (autor_id);


--
-- Name: notas_empresa_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notas_empresa_id_index ON public.notas USING btree (empresa_id);


--
-- Name: notas_postulacion_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notas_postulacion_id_created_at_index ON public.notas USING btree (postulacion_id, created_at);


--
-- Name: notifications_notifiable_type_notifiable_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_created_at_index ON public.notifications USING btree (notifiable_type, notifiable_id, created_at);


--
-- Name: postulacion_etapas_empresa_id_postulacion_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulacion_etapas_empresa_id_postulacion_id_index ON public.postulacion_etapas USING btree (empresa_id, postulacion_id);


--
-- Name: postulacion_etapas_postulacion_id_etapa_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulacion_etapas_postulacion_id_etapa_index ON public.postulacion_etapas USING btree (postulacion_id, etapa);


--
-- Name: postulaciones_candidato_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulaciones_candidato_id_index ON public.postulaciones USING btree (candidato_id);


--
-- Name: postulaciones_cv_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulaciones_cv_id_index ON public.postulaciones USING btree (cv_id);


--
-- Name: postulaciones_empresa_id_candidato_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulaciones_empresa_id_candidato_id_index ON public.postulaciones USING btree (empresa_id, candidato_id);


--
-- Name: postulaciones_empresa_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulaciones_empresa_id_created_at_index ON public.postulaciones USING btree (empresa_id, created_at);


--
-- Name: postulaciones_empresa_id_ia_cupo_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulaciones_empresa_id_ia_cupo_at_index ON public.postulaciones USING btree (empresa_id, ia_cupo_at);


--
-- Name: postulaciones_tablero_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulaciones_tablero_index ON public.postulaciones USING btree (empresa_id, vacante_id, etapa, orden) INCLUDE (retirada_at, etapa_cambiada_at, created_at, match_score);


--
-- Name: postulaciones_vacante_match_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX postulaciones_vacante_match_index ON public.postulaciones USING btree (empresa_id, vacante_id, match_score DESC NULLS LAST, id);


--
-- Name: reclamaciones_estado_fecha_limite_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reclamaciones_estado_fecha_limite_index ON public.reclamaciones USING btree (estado, fecha_limite);


--
-- Name: reclamaciones_respondida_por_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reclamaciones_respondida_por_index ON public.reclamaciones USING btree (respondida_por);


--
-- Name: requisitos_competencia_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX requisitos_competencia_id_index ON public.requisitos USING btree (competencia_id);


--
-- Name: requisitos_empresa_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX requisitos_empresa_id_index ON public.requisitos USING btree (empresa_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: users_empresa_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_empresa_id_index ON public.users USING btree (empresa_id);


--
-- Name: vacantes_creada_por_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vacantes_creada_por_index ON public.vacantes USING btree (creada_por);


--
-- Name: vacantes_empresa_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vacantes_empresa_id_estado_index ON public.vacantes USING btree (empresa_id, estado);


--
-- Name: actividades actividades_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actividades
    ADD CONSTRAINT actividades_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: actividades actividades_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actividades
    ADD CONSTRAINT actividades_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: candidato_competencia candidato_competencia_candidato_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidato_competencia
    ADD CONSTRAINT candidato_competencia_candidato_id_foreign FOREIGN KEY (candidato_id) REFERENCES public.candidatos(id) ON DELETE CASCADE;


--
-- Name: candidato_competencia candidato_competencia_competencia_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidato_competencia
    ADD CONSTRAINT candidato_competencia_competencia_id_foreign FOREIGN KEY (competencia_id) REFERENCES public.competencias(id) ON DELETE RESTRICT;


--
-- Name: candidato_competencia candidato_competencia_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidato_competencia
    ADD CONSTRAINT candidato_competencia_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: candidatos candidatos_empresa_origen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidatos
    ADD CONSTRAINT candidatos_empresa_origen_id_foreign FOREIGN KEY (empresa_origen_id) REFERENCES public.empresas(id) ON DELETE SET NULL;


--
-- Name: candidatos candidatos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidatos
    ADD CONSTRAINT candidatos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: cv_fragmentos cv_fragmentos_cv_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cv_fragmentos
    ADD CONSTRAINT cv_fragmentos_cv_id_foreign FOREIGN KEY (cv_id) REFERENCES public.cvs(id) ON DELETE CASCADE;


--
-- Name: cvs cvs_candidato_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cvs
    ADD CONSTRAINT cvs_candidato_id_foreign FOREIGN KEY (candidato_id) REFERENCES public.candidatos(id) ON DELETE CASCADE;


--
-- Name: cvs cvs_subido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cvs
    ADD CONSTRAINT cvs_subido_por_foreign FOREIGN KEY (subido_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: entrevistas entrevistas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrevistas
    ADD CONSTRAINT entrevistas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: entrevistas entrevistas_entrevistador_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrevistas
    ADD CONSTRAINT entrevistas_entrevistador_id_empresa_foreign FOREIGN KEY (empresa_id, entrevistador_id) REFERENCES public.users(empresa_id, id) ON DELETE RESTRICT;


--
-- Name: entrevistas entrevistas_postulacion_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrevistas
    ADD CONSTRAINT entrevistas_postulacion_id_empresa_foreign FOREIGN KEY (empresa_id, postulacion_id) REFERENCES public.postulaciones(empresa_id, id) ON DELETE CASCADE;


--
-- Name: evaluaciones evaluaciones_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evaluaciones
    ADD CONSTRAINT evaluaciones_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: evaluaciones evaluaciones_evaluador_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evaluaciones
    ADD CONSTRAINT evaluaciones_evaluador_id_empresa_foreign FOREIGN KEY (empresa_id, evaluador_id) REFERENCES public.users(empresa_id, id) ON DELETE RESTRICT;


--
-- Name: evaluaciones evaluaciones_postulacion_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evaluaciones
    ADD CONSTRAINT evaluaciones_postulacion_id_empresa_foreign FOREIGN KEY (empresa_id, postulacion_id) REFERENCES public.postulaciones(empresa_id, id) ON DELETE CASCADE;


--
-- Name: invitaciones invitaciones_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitaciones
    ADD CONSTRAINT invitaciones_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: invitaciones invitaciones_invitada_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitaciones
    ADD CONSTRAINT invitaciones_invitada_por_foreign FOREIGN KEY (invitada_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: match_feedback match_feedback_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_feedback
    ADD CONSTRAINT match_feedback_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: match_feedback match_feedback_postulacion_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_feedback
    ADD CONSTRAINT match_feedback_postulacion_id_empresa_foreign FOREIGN KEY (empresa_id, postulacion_id) REFERENCES public.postulaciones(empresa_id, id) ON DELETE CASCADE;


--
-- Name: match_feedback match_feedback_requisito_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_feedback
    ADD CONSTRAINT match_feedback_requisito_id_foreign FOREIGN KEY (requisito_id) REFERENCES public.requisitos(id) ON DELETE CASCADE;


--
-- Name: match_feedback match_feedback_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_feedback
    ADD CONSTRAINT match_feedback_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: notas notas_autor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas
    ADD CONSTRAINT notas_autor_id_foreign FOREIGN KEY (autor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: notas notas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas
    ADD CONSTRAINT notas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: notas notas_postulacion_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas
    ADD CONSTRAINT notas_postulacion_id_empresa_foreign FOREIGN KEY (empresa_id, postulacion_id) REFERENCES public.postulaciones(empresa_id, id) ON DELETE CASCADE;


--
-- Name: postulacion_etapas postulacion_etapas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulacion_etapas
    ADD CONSTRAINT postulacion_etapas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: postulacion_etapas postulacion_etapas_postulacion_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulacion_etapas
    ADD CONSTRAINT postulacion_etapas_postulacion_id_empresa_foreign FOREIGN KEY (empresa_id, postulacion_id) REFERENCES public.postulaciones(empresa_id, id) ON DELETE CASCADE;


--
-- Name: postulaciones postulaciones_candidato_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulaciones
    ADD CONSTRAINT postulaciones_candidato_id_foreign FOREIGN KEY (candidato_id) REFERENCES public.candidatos(id) ON DELETE CASCADE;


--
-- Name: postulaciones postulaciones_cv_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulaciones
    ADD CONSTRAINT postulaciones_cv_id_foreign FOREIGN KEY (cv_id) REFERENCES public.cvs(id) ON DELETE SET NULL;


--
-- Name: postulaciones postulaciones_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulaciones
    ADD CONSTRAINT postulaciones_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: postulaciones postulaciones_vacante_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postulaciones
    ADD CONSTRAINT postulaciones_vacante_id_empresa_foreign FOREIGN KEY (empresa_id, vacante_id) REFERENCES public.vacantes(empresa_id, id) ON DELETE CASCADE;


--
-- Name: reclamaciones reclamaciones_respondida_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reclamaciones
    ADD CONSTRAINT reclamaciones_respondida_por_foreign FOREIGN KEY (respondida_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: requisitos requisitos_competencia_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requisitos
    ADD CONSTRAINT requisitos_competencia_id_foreign FOREIGN KEY (competencia_id) REFERENCES public.competencias(id) ON DELETE RESTRICT;


--
-- Name: requisitos requisitos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requisitos
    ADD CONSTRAINT requisitos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: requisitos requisitos_vacante_id_empresa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requisitos
    ADD CONSTRAINT requisitos_vacante_id_empresa_foreign FOREIGN KEY (empresa_id, vacante_id) REFERENCES public.vacantes(empresa_id, id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: users users_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: vacantes vacantes_creada_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacantes
    ADD CONSTRAINT vacantes_creada_por_foreign FOREIGN KEY (creada_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: vacantes vacantes_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vacantes
    ADD CONSTRAINT vacantes_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--


