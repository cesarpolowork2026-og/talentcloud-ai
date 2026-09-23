"""Conjunto de evaluación del matching (evaluacion/): CV de demostración, requisitos y niveles esperados."""

import json
from dataclasses import dataclass
from datetime import date
from functools import cached_property
from pathlib import Path
from typing import Any

from talentcloud_ai.calibracion import Caso
from talentcloud_ai.contrato.comun import NivelCoincidencia
from talentcloud_ai.contrato.matching import MatchInput
from talentcloud_ai.matching.evaluador import EvaluadorDeMatching


@dataclass(frozen=True)
class ConjuntoDeEvaluacion:
    raiz: Path  # talentcloud-ai/

    @cached_property
    def _datos(self) -> dict[str, Any]:
        datos: dict[str, Any] = json.loads((self.raiz / "evaluacion" / "conjunto.json").read_text(encoding="utf-8"))
        return datos

    @property
    def fecha_referencia(self) -> date:
        return date.fromisoformat(self._datos["fecha_referencia"])

    @property
    def cvs(self) -> list[str]:
        cvs: list[str] = self._datos["cvs"]
        return cvs

    @property
    def requisitos(self) -> list[dict[str, Any]]:
        requisitos: list[dict[str, Any]] = self._datos["requisitos"]
        return requisitos

    def texto_cv(self, cv: str) -> str:
        return (self.raiz / "tests" / "datos" / "cvs" / f"{cv}.txt").read_text(encoding="utf-8")

    def entrada(self, cv: str) -> MatchInput:
        """Todos los requisitos contra un CV (tipo y peso no influyen en el nivel)."""
        return MatchInput.model_validate(
            {
                "version": 1,
                "postulacion_id": 1,
                "vacante": {"titulo": "Conjunto de evaluación"},
                "requisitos": [{**r, "tipo": "obligatorio", "peso": 3} for r in self.requisitos],
                "cv": {"id": 1, "texto": self.texto_cv(cv)},
            }
        )

    def esperado(self, cv: str, requisito_id: int) -> NivelCoincidencia:
        requisito = next(r for r in self.requisitos if r["id"] == requisito_id)
        return NivelCoincidencia(requisito["esperado"][cv])

    def via(self, requisito_id: int) -> str:
        """ "catalogo" (la competencia aparece tal cual) o "semantica" (redactado sin el término literal)."""
        via: str = next(r for r in self.requisitos if r["id"] == requisito_id)["via"]
        return via

    def casos(self, evaluador: EvaluadorDeMatching) -> list[Caso]:
        """Un caso por CV × requisito, con su diagnóstico (el modelo se usa una vez por CV)."""
        return [
            Caso(cv, diagnostico, self.esperado(cv, diagnostico.requisito.id))
            for cv in self.cvs
            for diagnostico in evaluador.diagnosticar(self.entrada(cv))
        ]

    def competencias_esperadas(self) -> dict[str, set[str]]:
        datos = json.loads((self.raiz / "evaluacion" / "competencias_esperadas.json").read_text(encoding="utf-8"))
        return {cv: set(nombres) for cv, nombres in datos["cvs"].items()}
