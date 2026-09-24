<?php

/*
 * Iconos: la app los pinta con el helper svg() desde <x-ui.icon>, nunca con
 * los componentes <x-phosphor-*>. Registrarlos obliga a recorrer en cada
 * petición los ~9 000 SVG de Phosphor (más de 1 s por página en Windows).
 */
return [

    'components' => [
        'disabled' => true,
        'default' => 'icon',
    ],

];
