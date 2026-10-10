# Marca de TalentCloud AI

`TalentCloud-original.png` es el logo original: solo existe como imagen. Los SVG de `public/marca/` se
reconstruyeron a partir de él vectorizando cada color por separado (figura, «Talent», «Cloud», «AI» y lema) y
aplicando degradados SVG con los colores medidos en el original. No se usa ninguna tipografía: el texto son
trazados.

## Variantes (`public/marca/`)

| Archivo | Uso |
|---|---|
| `talentcloud-ai-oscuro.svg` | Barra azul petróleo (`#004961`): app, cabecera pública y maquetas. Colores aclarados para que se lea sobre ella. |
| `talentcloud-ai.svg` | Fondos claros (pie). Colores originales. |
| `talentcloud-ai-lema.svg`, `talentcloud-ai-lema-oscuro.svg` | Maestros con «Inteligencia que conecta talento» (documentos, presentaciones). En la web el lema va como texto junto al logo: dentro de la imagen, a la altura de la barra, no se leería. |
| `simbolo.svg` | Solo el símbolo; también es `public/favicon.svg`. |
| `talentcloud-ai-correo.png` | Cabecera de los correos (los clientes de correo no muestran bien los SVG). |
| `vista-previa.png` | Imagen para compartir (Open Graph, 1200 × 630) de las páginas públicas. Se genera desde `herramientas/vista-previa.html`: abrirlo a 1200 × 630 y capturarlo (Playwright: `page.goto('file:///…/vista-previa.html')` + `page.screenshot`). |

Además: `public/favicon.ico` (16, 32 y 48 px) y `public/apple-touch-icon.png` (180 px), generados desde `simbolo.svg`.

En las vistas, siempre `<x-marca>` (props `fondo`, `tamano`): nunca un `<img>` suelto al archivo.

## Regenerar

Si llega el logo en vectorial (Figma, Illustrator o SVG), se sustituyen directamente los archivos de
`public/marca/`, conservando sus nombres. Para volver a reconstruirlo desde el PNG (`herramientas/`):

```bash
cd talentcloud/resources/brand/herramientas
uv run --no-project --with pillow --with numpy --with scipy --with potracer python vectorizar.py  # → trazos.json
python componer.py                                                                                  # → public/marca/*.svg
```

Los colores de cada variante están en `PALETAS` de `componer.py`. El favicon, el icono de iOS y el PNG del correo se
renderizan desde los SVG (por ejemplo, con Playwright) a los tamaños indicados.
