{{--
    Icono Phosphor en peso duotone (norma del design system).
    name: nombre del icono sin sufijo (bell, lock, check-circle…). Toma el color de currentColor.
--}}
@props(['name', 'size' => 16])

{{ svg('phosphor-'.$name.'-duotone', trim('shrink-0 '.$attributes->get('class')), $attributes->except('class')->merge([
    'width' => $size,
    'height' => $size,
    'aria-hidden' => 'true',
    'focusable' => 'false',
])->getAttributes()) }}
