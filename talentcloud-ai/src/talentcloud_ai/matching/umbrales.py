"""Porcentajes y umbrales del matching por evidencia exacta. Se calibran con el conjunto de evaluación (día 14)."""

# Nivel: desde este porcentaje el requisito "Coincide"; por debajo (y > 0) queda para "Revisar".
UMBRAL_COINCIDE = 60
MAXIMO = 98  # la IA nunca afirma una coincidencia total: la decisión es del reclutador

# Requisito sin años mínimos.
BASE_MENCION = 75
BONO_EXPERIENCIA_LABORAL = 10  # la evidencia viene de un puesto o proyecto, no solo de una lista de habilidades
BONO_POR_ANIO = 2
MAX_BONO_ANIOS = 10

# Requisito con años mínimos.
CUMPLE_MINIMO = 90
BONO_POR_ANIO_EXTRA = 2
# Menos años de los pedidos queda siempre por debajo de UMBRAL_COINCIDE: 25 + 34 × (años / mínimo) ≤ 59.
BASE_ANIOS_INSUFICIENTES = 25
RANGO_ANIOS_INSUFICIENTES = 34
ANIOS_NO_INDICADOS = 55

# Solo mencionada como interés o nivel básico.
SOLO_INTERES = 20

# Evidencia semántica (sin mención literal): similitud coseno con el fragmento más parecido del CV.
# Calibrados con evaluacion/calibrar.py (paraphrase-multilingual-MiniLM-L12-v2, 145 casos): ver
# evaluacion/calibracion.json. Un test exige que coincidan con ese informe.
SIMILITUD_REVISAR = 0.54
SIMILITUD_COINCIDE = 0.56
SEMANTICA_REVISAR_MIN = 25
SEMANTICA_REVISAR_MAX = 55
SEMANTICA_COINCIDE_MIN = 60
# Siempre por debajo de cualquier coincidencia exacta: sin mención literal hay menos certeza.
SEMANTICA_COINCIDE_MAX = BASE_MENCION - 1
