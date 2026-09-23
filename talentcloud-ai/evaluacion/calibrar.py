"""Calibra los umbrales semánticos con el modelo real y deja el informe en evaluacion/calibracion.json.

    uv run --extra ml python evaluacion/calibrar.py      (en Windows, con PYTHONPATH=src)

Los valores resultantes se copian a mano en matching/umbrales.py (un test comprueba que coinciden):
cambiar un umbral es una decisión revisable, no un efecto secundario de ejecutar un script.
"""

import json
from pathlib import Path

from talentcloud_ai.calibracion import (
    SOLO_EXACTA,
    Caso,
    buscar_umbrales,
    matriz_de_confusion,
    medir,
    validacion_cruzada,
)
from talentcloud_ai.config import Settings
from talentcloud_ai.conjunto_de_evaluacion import ConjuntoDeEvaluacion
from talentcloud_ai.embeddings.fabrica import crear_embedder
from talentcloud_ai.matching.calificacion import CALIBRADOS, UmbralesSemanticos
from talentcloud_ai.matching.evaluador import EvaluadorDeMatching

RAIZ = Path(__file__).resolve().parents[1]
INFORME = RAIZ / "evaluacion" / "calibracion.json"


def detalle(caso: Caso, umbrales: UmbralesSemanticos) -> dict[str, object]:
    d = caso.diagnostico
    return {
        "cv": caso.grupo,
        "requisito": d.requisito.competencia,
        "esperado": caso.esperado.value,
        "predicho": caso.predecir(umbrales).value,
        "via": "exacta" if d.exacta else "semantica",
        "similitud": d.semantica.similitud if d.semantica else None,
        "fragmento": d.semantica.texto if d.semantica else (d.exacta.evidencia if d.exacta else None),
    }


def main() -> None:
    conjunto = ConjuntoDeEvaluacion(RAIZ)
    settings = Settings(entorno="testing", embeddings_backend="sentence-transformers")
    casos = conjunto.casos(EvaluadorDeMatching(conjunto.fecha_referencia, crear_embedder(settings)))
    semanticos = [c for c in casos if c.diagnostico.exacta is None]

    mejor = buscar_umbrales(casos)
    base = medir(casos, SOLO_EXACTA)
    vigentes = medir(casos, CALIBRADOS)
    cruzada = validacion_cruzada(casos)

    informe = {
        "modelo": settings.modelo_embeddings,
        "casos": len(casos),
        "casos_sin_mencion_literal": len(semanticos),
        "umbrales": {"revisar": mejor.umbrales.revisar, "coincide": mejor.umbrales.coincide},
        "exactitud": {
            "hibrido_calibrado": round(mejor.exactitud, 4),
            "solo_coincidencia_exacta": round(base.exactitud, 4),
            "umbrales_vigentes_en_codigo": round(vigentes.exactitud, 4),
            "validacion_cruzada_dejando_un_cv_fuera": round(cruzada, 4),
            "sin_mencion_literal": round(medir(semanticos, mejor.umbrales).exactitud, 4),
        },
        "falsos_coincide": {
            "hibrido_calibrado": mejor.falsos_coincide,
            "solo_coincidencia_exacta": base.falsos_coincide,
        },
        "matriz_de_confusion": matriz_de_confusion(casos, mejor.umbrales),
        "errores": [detalle(c, mejor.umbrales) for c in casos if c.predecir(mejor.umbrales) is not c.esperado],
        "casos_semanticos": sorted(
            (detalle(c, mejor.umbrales) for c in semanticos),
            key=lambda x: -(x["similitud"] or 0),  # type: ignore[operator]
        ),
    }
    INFORME.write_text(json.dumps(informe, ensure_ascii=False, indent=2) + "\n", encoding="utf-8", newline="\n")

    print(f"Umbrales: revisar ≥ {mejor.umbrales.revisar}, coincide ≥ {mejor.umbrales.coincide}")
    print(
        f"Exactitud híbrida {mejor.exactitud:.1%} ({mejor.aciertos}/{mejor.total}) · solo exacta {base.exactitud:.1%}"
    )
    print(f"Validación cruzada (un CV fuera): {cruzada:.1%} · falsos 'Coincide': {mejor.falsos_coincide}")
    print(f"Informe: {INFORME.relative_to(RAIZ)}")


if __name__ == "__main__":
    main()
