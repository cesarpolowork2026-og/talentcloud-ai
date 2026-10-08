"""
Vectoriza el logo (PNG) por capas de color: cada capa es una máscara binaria,
ampliada y suavizada, trazada con potrace. Devuelve trazados SVG limpios en las
coordenadas del PNG original; los colores y degradados se ponen aparte.
"""
import json
from pathlib import Path

import numpy as np
import potrace
from PIL import Image, ImageFilter
from scipy import ndimage

AQUI = Path(__file__).resolve().parent
ORIGEN = AQUI.parent / 'TalentCloud-original.png'
ESCALA = 4  # se traza a 4x para que las curvas salgan suaves

im = Image.open(ORIGEN).convert('RGB')
a = np.asarray(im).astype(float)
fondo = np.array([254.0, 254.0, 254.0])

# "Tinta" continua 0..1 (cuánto se aleja del blanco): respeta el antialias.
distancia = np.sqrt(((a - fondo) ** 2).sum(axis=2))
tinta = np.clip(distancia / 120.0, 0, 1)

r, g, b = a[..., 0], a[..., 1], a[..., 2]
maximo = a.max(axis=2)
minimo = a.min(axis=2)
saturacion = maximo - minimo

H, W = tinta.shape
yy, xx = np.mgrid[0:H, 0:W]

zona_simbolo = xx < 590
zona_nombre = (xx >= 590) & (yy >= 250) & (yy <= 440)
zona_lema = (xx >= 590) & (yy >= 455) & (yy <= 530)

# Color de cada píxel del nombre: azul marino (oscuro), gris (poco saturado) o turquesa.
oscuro = maximo < 120
gris = (saturacion < 60) & ~oscuro
turquesa = ~oscuro & ~gris


def mascara(peso):
    """Máscara ampliada y suavizada a partir de un peso 0..1."""
    img = Image.fromarray((np.clip(peso, 0, 1) * 255).astype(np.uint8))
    img = img.resize((W * ESCALA, H * ESCALA), Image.LANCZOS).filter(ImageFilter.GaussianBlur(ESCALA * 0.6))
    return np.asarray(img) > 127


def trazar(m, turd=60):
    # potracer toma como figura los valores falsos: se invierte la máscara.
    bm = potrace.Bitmap(~m)
    curvas = bm.trace(turdsize=turd, alphamax=1.0, opticurve=True, opttolerance=0.2)
    partes = []
    f = lambda p: f'{p.x / ESCALA:.1f} {p.y / ESCALA:.1f}'
    for c in curvas:
        d = [f'M{f(c.start_point)}']
        for s in c.segments:
            if s.is_corner:
                d.append(f'L{f(s.c)}L{f(s.end_point)}')
            else:
                d.append(f'C{f(s.c1)} {f(s.c2)} {f(s.end_point)}')
        d.append('Z')
        partes.append(''.join(d))
    return ''.join(partes)


def componentes(peso, minimo_px=200):
    """Piezas separadas (para dar a cada una su degradado), ordenadas por x."""
    etiquetas, n = ndimage.label(peso > 0.35)
    piezas = []
    for i in range(1, n + 1):
        sel = etiquetas == i
        if sel.sum() < minimo_px:
            continue
        ys, xs = np.where(sel)
        caja = (xs.min(), ys.min(), xs.max(), ys.max())
        # Dilata un poco la selección para conservar el antialias del borde.
        cerca = ndimage.binary_dilation(sel, iterations=3)
        piezas.append((caja, np.where(cerca, peso, 0)))
    return sorted(piezas, key=lambda p: (p[0][0], p[0][1]))


resultado = {'ancho': W, 'alto': H, 'simbolo': [], 'nombre': {}, 'lema': ''}

for caja, peso in componentes(np.where(zona_simbolo, tinta, 0)):
    resultado['simbolo'].append({'caja': [int(v) for v in caja], 'd': trazar(mascara(peso))})

for nombre, sel in [('talent', oscuro), ('cloud', turquesa), ('ai', gris)]:
    peso = np.where(zona_nombre & sel, tinta, 0)
    # Separa "Cloud" de la "AI" por posición: el gris solo vale a la derecha de x=1640.
    if nombre == 'ai':
        peso = np.where(xx > 1640, peso, 0)
    if nombre == 'cloud':
        peso = np.where(xx > 1150, peso, 0)
    if nombre == 'talent':
        peso = np.where(xx < 1300, peso, 0)
    resultado['nombre'][nombre] = trazar(mascara(peso), turd=40)

resultado['lema'] = trazar(mascara(np.where(zona_lema, tinta, 0)), turd=20)

json.dump(resultado, open(AQUI / 'trazos.json', 'w'), indent=1)
for i, p in enumerate(resultado['simbolo']):
    print('pieza', i, 'caja', p['caja'], 'longitud', len(p['d']))
for k, v in resultado['nombre'].items():
    print(k, len(v))
print('lema', len(resultado['lema']))
