"""
Compone los SVG de la marca a partir de los trazos vectorizados.

Variantes (cada una con su propio fondo previsto):
  talentcloud-ai.svg              colores originales, para fondo claro (pie)
  talentcloud-ai-oscuro.svg       para la barra azul petróleo (#004961)
  talentcloud-ai-lema-oscuro.svg  la anterior con el lema (cabecera de la página de inicio)
  talentcloud-ai-lema.svg         colores originales con lema (maestro de la marca)
  simbolo.svg                     solo el símbolo (favicon, iconos)
"""
import json
from pathlib import Path
import os
import re

AQUI = Path(__file__).resolve().parent
t = json.load(open(AQUI / 'trazos.json'))
SALIDA = str(AQUI.parents[2] / 'public' / 'marca')  # talentcloud/public/marca
os.makedirs(SALIDA, exist_ok=True)


def enteros(d):
    # A la escala del dibujo (≈2000 unidades) un decimal no se aprecia: coordenadas enteras.
    return re.sub(r'-?\d+\.\d+', lambda m: str(round(float(m.group()))), d)


simbolo = [enteros(p['d']) for p in t['simbolo']]  # izquierda, cabeza, derecha, estrella
talent, cloud, ai = (enteros(t['nombre'][k]) for k in ('talent', 'cloud', 'ai'))
lema = enteros(t['lema'])

# Colores: originales (medidos en el PNG) y versión para fondo oscuro.
PALETAS = {
    'claro': {
        'izq': ('#14d6c6', '#0a97bb', '#077d92'), 'cabeza': ('#62e2d0', '#2f9cc1'),
        'der': ('#25afe0', '#0a8fe6', '#0a63dc'), 'estrella': ('#38b6f3', '#5a72ef'),
        'talent': '#0a2144', 'cloud': ('#0a7fa6', '#12c4d2'), 'ai': '#6e7f90', 'lema': '#5b7086',
    },
    'oscuro': {
        # Más luminosos: sobre #004961 el turquesa oscuro del original se perdería.
        'izq': ('#5ff0de', '#2fd0d6', '#20b4c9'), 'cabeza': ('#8ff3e2', '#4fd0e6'),
        'der': ('#6fd0ff', '#45b4ff', '#3d95ff'), 'estrella': ('#8fd8ff', '#a6b4ff'),
        'talent': '#ffffff', 'cloud': ('#6fe6ec', '#a5f3f6'), 'ai': '#c3d0dc', 'lema': '#cbeeff',
    },
}

CAJA = (180, 150, 1735, 406)  # x, y, ancho, alto del contenido (con margen)
CAJA_SIMBOLO = (180, 150, 405, 405)


def degradados(c):
    return f"""<defs>
<linearGradient id="izq" x1="195" y1="264" x2="374" y2="549" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="{c['izq'][0]}"/><stop offset=".55" stop-color="{c['izq'][1]}"/><stop offset="1" stop-color="{c['izq'][2]}"/></linearGradient>
<linearGradient id="cabeza" x1="355" y1="200" x2="440" y2="290" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="{c['cabeza'][0]}"/><stop offset="1" stop-color="{c['cabeza'][1]}"/></linearGradient>
<linearGradient id="der" x1="520" y1="268" x2="420" y2="533" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="{c['der'][0]}"/><stop offset=".5" stop-color="{c['der'][1]}"/><stop offset="1" stop-color="{c['der'][2]}"/></linearGradient>
<linearGradient id="estrella" x1="482" y1="162" x2="572" y2="255" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="{c['estrella'][0]}"/><stop offset="1" stop-color="{c['estrella'][1]}"/></linearGradient>
<linearGradient id="cloud" x1="1150" y1="0" x2="1650" y2="0" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="{c['cloud'][0]}"/><stop offset="1" stop-color="{c['cloud'][1]}"/></linearGradient>
</defs>"""


def figura(c):
    return (f'<g fill-rule="evenodd"><path fill="url(#izq)" d="{simbolo[0]}"/><path fill="url(#cabeza)" d="{simbolo[1]}"/>'
            f'<path fill="url(#der)" d="{simbolo[2]}"/><path fill="url(#estrella)" d="{simbolo[3]}"/></g>')


def nombre(c):
    return (f'<g fill-rule="evenodd"><path fill="{c["talent"]}" d="{talent}"/><path fill="url(#cloud)" d="{cloud}"/>'
            f'<path fill="{c["ai"]}" d="{ai}"/></g>')


def svg(caja, cuerpo, titulo):
    x, y, w, h = caja
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{x} {y} {w} {h}" role="img" aria-label="{titulo}">'
            f'<title>{titulo}</title>{cuerpo}</svg>\n')


variantes = {
    'talentcloud-ai.svg': ('claro', False),
    'talentcloud-ai-lema.svg': ('claro', True),
    'talentcloud-ai-oscuro.svg': ('oscuro', False),
    'talentcloud-ai-lema-oscuro.svg': ('oscuro', True),
}
for archivo, (paleta, con_lema) in variantes.items():
    c = PALETAS[paleta]
    cuerpo = degradados(c) + figura(c) + nombre(c)
    if con_lema:
        cuerpo += f'<path fill="{c["lema"]}" fill-rule="evenodd" d="{lema}"/>'
    open(os.path.join(SALIDA, archivo), 'w', encoding='utf-8', newline='\n').write(svg(CAJA, cuerpo, 'TalentCloud AI'))

c = PALETAS['claro']
open(os.path.join(SALIDA, 'simbolo.svg'), 'w', encoding='utf-8', newline='\n').write(
    svg(CAJA_SIMBOLO, degradados(c) + figura(c), 'TalentCloud AI'))

for f in sorted(os.listdir(SALIDA)):
    print(f, os.path.getsize(os.path.join(SALIDA, f)))
