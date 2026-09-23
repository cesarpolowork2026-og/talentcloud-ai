from datetime import date

import pytest

from talentcloud_ai.extraccion.experiencia import Periodo, anios_cerca_de, anios_totales, leer_puesto
from talentcloud_ai.extraccion.texto import normalizar_alineado

HOY = date(2026, 9, 23)


@pytest.mark.parametrize(
    ("oracion", "termino", "anios"),
    [
        ("Desarrolladora backend con 5 años de experiencia con Java.", "java", 5.0),
        ("Un año y medio de experiencia en Java.", "java", 1.5),
        ("Ingeniera con 6 años construyendo servicios en Java.", "java", 6.0),
        ("Más de 10+ años usando Python.", "python", 10.0),
        ("5 años de experiencia en datos y 6 años de experiencia previa con Java.", "java", 6.0),
        ("Java: 4 años de experiencia.", "java", 4.0),
        ("Servicios con Java 21 en producción.", "java", None),
        ("Carrera de 5 años; uso Java.", "java", None),
    ],
)
def test_lee_los_anios_declarados_junto_a_una_competencia(oracion: str, termino: str, anios: float | None) -> None:
    normal = normalizar_alineado(oracion)
    assert anios_cerca_de(normal, normal.index(termino)) == anios


def test_lee_un_puesto_vigente_con_su_periodo() -> None:
    puesto = leer_puesto("Senior Backend Developer — Fintech Andina (2022 – actualidad)", HOY)

    assert puesto is not None
    assert (puesto.cargo, puesto.organizacion, puesto.desde, puesto.hasta) == (
        "Senior Backend Developer",
        "Fintech Andina",
        2022,
        None,
    )
    assert puesto.periodo.anios == pytest.approx(4.7, abs=0.05)


@pytest.mark.parametrize(
    ("linea", "cargo", "organizacion", "desde", "hasta", "anios"),
    [
        ("Backend Developer — Retail Digital (2020 – 2022)", "Backend Developer", "Retail Digital", 2020, 2022, 2.0),
        ("Prácticas profesionales — Municipalidad Distrital (2023)", "Prácticas profesionales",
         "Municipalidad Distrital", 2023, 2023, 1.0),
        ("Analista de datos, Banco Sur, marzo 2019 - junio 2021", "Analista de datos", "Banco Sur", 2019, 2021, 2.25),
        ("2018 - 2020 | QA Engineer | Orbita Labs", "QA Engineer", "Orbita Labs", 2018, 2020, 2.0),
        ("Desarrollador en Soluciones Web (2024 - presente)", "Desarrollador", "Soluciones Web", 2024, None, 2.7),
    ],
)  # fmt: skip
def test_lee_puestos_en_varios_formatos(
    linea: str, cargo: str, organizacion: str, desde: int, hasta: int | None, anios: float
) -> None:
    puesto = leer_puesto(linea, HOY)

    assert puesto is not None
    assert (puesto.cargo, puesto.organizacion, puesto.desde, puesto.hasta) == (cargo, organizacion, desde, hasta)
    assert puesto.periodo.anios == pytest.approx(anios, abs=0.05)


@pytest.mark.parametrize(
    "linea",
    [
        "Diseñé esquemas en PostgreSQL desde 2019 para el área de ventas y operaciones del banco.",
        "AWS Certified Developer – Associate",
        "Proyecto futuro (2031 – 2032)",
    ],
)
def test_no_confunde_otras_lineas_con_puestos(linea: str) -> None:
    assert leer_puesto(linea, HOY) is None


def test_suma_periodos_sin_contar_dos_veces_los_solapamientos() -> None:
    periodos = [Periodo(2018, 2021), Periodo(2020, 2022), Periodo(2024, 2025)]
    assert anios_totales(periodos) == 5.0
    assert anios_totales([]) == 0.0
