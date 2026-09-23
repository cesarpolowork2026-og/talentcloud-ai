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
BASE_ANIOS_INSUFICIENTES = 40
RANGO_ANIOS_INSUFICIENTES = 34  # 40 + 34 × (años / mínimo) < 74
ANIOS_NO_INDICADOS = 55

# Solo mencionada como interés o nivel básico.
SOLO_INTERES = 20
