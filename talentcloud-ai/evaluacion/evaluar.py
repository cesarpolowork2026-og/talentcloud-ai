"""Mide el servicio de IA frente al análisis básico de Laravel (FakeMatchingEngine) sobre el conjunto de evaluación.

    php artisan talentcloud:evaluar-motor-basico          (desde talentcloud/: predicciones del análisis básico)
    uv run --extra ml python evaluacion/evaluar.py        (en Windows, con PYTHONPATH=src)

Deja evaluacion/resultados.json (completo) y evaluacion/RESULTADOS.md (resumen legible).
"""

import json
from collections.abc import Callable
from pathlib import Path
from typing import Any

from talentcloud_ai.calibracion import SOLO_EXACTA, Caso
from talentcloud_ai.config import Settings
from talentcloud_ai.conjunto_de_evaluacion import ConjuntoDeEvaluacion
from talentcloud_ai.contrato.comun import NivelCoincidencia
from talentcloud_ai.contrato.cv import CompetenciaCatalogo
from talentcloud_ai.embeddings.fabrica import crear_embedder
from talentcloud_ai.extraccion.analizador import AnalizadorDeCv
from talentcloud_ai.matching.calificacion import CALIBRADOS
from talentcloud_ai.matching.evaluador import EvaluadorDeMatching
from talentcloud_ai.metricas import Niveles, PrecisionRecall, extraccion, niveles

RAIZ = Path(__file__).resolve().parents[1]
EVALUACION = RAIZ / "evaluacion"
PREDICCIONES_BASICO = EVALUACION / "predicciones_motor_basico.json"


def medir_extraccion(conjunto: ConjuntoDeEvaluacion) -> dict[str, Any]:
    catalogo = [
        CompetenciaCatalogo.model_validate(c)
        for c in json.loads((RAIZ / "tests" / "datos" / "catalogo.json").read_text(encoding="utf-8"))
    ]
    analizador = AnalizadorDeCv(catalogo, conjunto.fecha_referencia)
    esperadas = conjunto.competencias_esperadas()

    # Servicio: solo las acreditadas. Línea base: cualquier mención literal cuenta (como una búsqueda de palabras).
    servicio = {
        cv: {c.nombre for c in analizador.analizar(conjunto.texto_cv(cv)).competencias if not c.dudosa}
        for cv in conjunto.cvs
    }
    literal = {cv: {m.nombre for m in analizador.menciones(conjunto.texto_cv(cv))} for cv in conjunto.cvs}

    return {
        "servicio": _pr(extraccion(esperadas, servicio)),
        "mencion_literal": _pr(extraccion(esperadas, literal)),
        "diferencias_del_servicio": {
            cv: {"sobran": sorted(servicio[cv] - esperadas[cv]), "faltan": sorted(esperadas[cv] - servicio[cv])}
            for cv in conjunto.cvs
            if servicio[cv] != esperadas[cv]
        },
    }


def predicciones_del_basico(conjunto: ConjuntoDeEvaluacion) -> dict[tuple[str, int], NivelCoincidencia]:
    if not PREDICCIONES_BASICO.exists():
        raise SystemExit(
            "Falta el análisis básico: ejecuta `php artisan talentcloud:evaluar-motor-basico` en talentcloud/."
        )
    datos = json.loads(PREDICCIONES_BASICO.read_text(encoding="utf-8"))
    predichas = {(c["cv"], c["requisito_id"]): NivelCoincidencia(c["nivel"]) for c in datos["casos"]}
    esperadas = {(cv, r["id"]) for cv in conjunto.cvs for r in conjunto.requisitos}
    if set(predichas) != esperadas:
        raise SystemExit(
            "Las predicciones del análisis básico no corresponden al conjunto actual: vuelve a generarlas."
        )
    return predichas


def medir_niveles(conjunto: ConjuntoDeEvaluacion, casos: list[Caso]) -> dict[str, Any]:
    basico = predicciones_del_basico(conjunto)
    motores: dict[str, Callable[[Caso], NivelCoincidencia]] = {
        "servicio_hibrido": lambda c: c.predecir(CALIBRADOS),
        "servicio_solo_exacta": lambda c: c.predecir(SOLO_EXACTA),
        "analisis_basico_laravel": lambda c: basico[(c.grupo, c.diagnostico.requisito.id)],
    }

    def para(filtro: Callable[[Caso], bool]) -> dict[str, Any]:
        return {
            nombre: _niveles(niveles((c.esperado, predecir(c)) for c in casos if filtro(c)))
            for nombre, predecir in motores.items()
        }

    return {
        "todos": para(lambda _: True),
        "requisitos_del_catalogo": para(lambda c: conjunto.via(c.diagnostico.requisito.id) == "catalogo"),
        "requisitos_sin_termino_literal": para(lambda c: conjunto.via(c.diagnostico.requisito.id) == "semantica"),
    }


def _pr(medida: PrecisionRecall) -> dict[str, float | int]:
    return {
        "precision": round(medida.precision, 4),
        "recall": round(medida.recall, 4),
        "f1": round(medida.f1, 4),
        "verdaderos": medida.verdaderos,
        "falsos_positivos": medida.falsos_positivos,
        "falsos_negativos": medida.falsos_negativos,
    }


def _niveles(medida: Niveles) -> dict[str, Any]:
    return {
        "exactitud": round(medida.exactitud, 4),
        "aciertos": medida.aciertos,
        "casos": medida.total,
        "falsos_coincide": medida.falsos_coincide,
        "por_nivel": {n.value: _pr(medida.por_nivel(n)) for n in NivelCoincidencia},
        "matriz_de_confusion": medida.matriz_de_confusion(),
    }


def resumen_en_markdown(resultados: dict[str, Any]) -> str:
    def pct(valor: float) -> str:
        return f"{valor * 100:.1f} %".replace(".", ",")

    nombres = {
        "servicio_hibrido": "Servicio de IA (exacta + semántica)",
        "servicio_solo_exacta": "Servicio, solo coincidencia exacta",
        "analisis_basico_laravel": "Análisis básico (FakeMatchingEngine)",
    }
    filas_niveles = [
        f"| {nombres[motor]} | {pct(t['exactitud'])} | {pct(c['exactitud'])} | {pct(s['exactitud'])} "
        f"| {t['falsos_coincide']} | {pct(t['por_nivel']['coincide']['precision'])} |"
        for motor in nombres
        for t, c, s in [
            (
                resultados["niveles"]["todos"][motor],
                resultados["niveles"]["requisitos_del_catalogo"][motor],
                resultados["niveles"]["requisitos_sin_termino_literal"][motor],
            )
        ]
    ]
    ext = resultados["extraccion"]
    filas_extraccion = [
        f"| {nombre} | {pct(m['precision'])} | {pct(m['recall'])} | {pct(m['f1'])} |"
        for nombre, m in [
            ("Servicio (solo acreditadas)", ext["servicio"]),
            ("Toda mención literal", ext["mencion_literal"]),
        ]
    ]
    diferencias = [
        f"- {cv}: sobran {', '.join(d['sobran']) or '—'}; faltan {', '.join(d['faltan']) or '—'}."
        for cv, d in ext["diferencias_del_servicio"].items()
    ]
    total = resultados["niveles"]["todos"]["servicio_hibrido"]["casos"]

    return "\n".join(
        [
            "# Resultados de la evaluación del matching",
            "",
            f"Generado por `evaluacion/evaluar.py` con `{resultados['modelo']}`. Conjunto: {total} casos "
            "(`conjunto.json`, 29 requisitos × 5 CV) y `competencias_esperadas.json`, etiquetados por el equipo de "
            "desarrollo y pendientes de revisión por un reclutador. Es un conjunto pequeño: sirve para comparar "
            "motores y detectar regresiones, no como medida definitiva de calidad.",
            "",
            "## Nivel por requisito",
            "",
            "| Motor | Exactitud | Requisitos del catálogo | Sin término literal | Falsos «Coincide» "
            "| Precisión de «Coincide» |",
            "| --- | --- | --- | --- | --- | --- |",
            *filas_niveles,
            "",
            "Falso «Coincide»: dar por acreditada una competencia que el CV no tiene (el error más costoso).",
            "",
            "## Extracción de competencias (/cv/analizar)",
            "",
            "| Método | Precisión | Recall | F1 |",
            "| --- | --- | --- | --- |",
            *filas_extraccion,
            "",
            "Diferencias del servicio con lo esperado:",
            "",
            *(diferencias or ["- Ninguna."]),
            "",
        ]
    )


def main() -> None:
    conjunto = ConjuntoDeEvaluacion(RAIZ)
    settings = Settings(entorno="testing", embeddings_backend="sentence-transformers")
    casos = conjunto.casos(EvaluadorDeMatching(conjunto.fecha_referencia, crear_embedder(settings)))

    resultados = {
        "modelo": settings.modelo_embeddings,
        "umbrales": {"revisar": CALIBRADOS.revisar, "coincide": CALIBRADOS.coincide},
        "extraccion": medir_extraccion(conjunto),
        "niveles": medir_niveles(conjunto, casos),
    }

    (EVALUACION / "resultados.json").write_text(
        json.dumps(resultados, ensure_ascii=False, indent=2) + "\n", encoding="utf-8", newline="\n"
    )
    (EVALUACION / "RESULTADOS.md").write_text(resumen_en_markdown(resultados), encoding="utf-8", newline="\n")
    print(resumen_en_markdown(resultados))


if __name__ == "__main__":
    main()
