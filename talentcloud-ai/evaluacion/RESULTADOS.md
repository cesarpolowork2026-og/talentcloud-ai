# Resultados de la evaluación del matching

Generado por `evaluacion/evaluar.py` con `paraphrase-multilingual-MiniLM-L12-v2`. Conjunto: 145 casos (`conjunto.json`, 29 requisitos × 5 CV) y `competencias_esperadas.json`, etiquetados por el equipo de desarrollo y pendientes de revisión por un reclutador. Es un conjunto pequeño: sirve para comparar motores y detectar regresiones, no como medida definitiva de calidad.

## Nivel por requisito

| Motor | Exactitud | Requisitos del catálogo | Sin término literal | Falsos «Coincide» | Precisión de «Coincide» |
| --- | --- | --- | --- | --- | --- |
| Servicio de IA (exacta + semántica) | 89,0 % | 96,0 % | 81,4 % | 0 | 100,0 % |
| Servicio, solo coincidencia exacta | 82,1 % | 96,0 % | 67,1 % | 0 | 100,0 % |
| Análisis básico (FakeMatchingEngine) | 80,7 % | 93,3 % | 67,1 % | 0 | 92,0 % |

Falso «Coincide»: dar por acreditada una competencia que el CV no tiene (el error más costoso).

## Extracción de competencias (/cv/analizar)

| Método | Precisión | Recall | F1 |
| --- | --- | --- | --- |
| Servicio (solo acreditadas) | 97,5 % | 100,0 % | 98,7 % |
| Toda mención literal | 92,9 % | 100,0 % | 96,3 % |

Diferencias del servicio con lo esperado:

- sofia: sobran Git; faltan —.
