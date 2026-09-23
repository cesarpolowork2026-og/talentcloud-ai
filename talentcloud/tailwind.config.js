import forms from '@tailwindcss/forms';

/*
 * Design system Broadsheet (TalentCloud AI Platform/_ds/broadsheet-*).
 * Los valores viven como variables CSS en resources/css/broadsheet/tokens.css;
 * aquí solo se exponen como utilidades de Tailwind. Para retocar el aspecto,
 * editar los tokens, no este archivo.
 */

const ramp = (role) =>
    Object.fromEntries([100, 200, 300, 400, 500, 600, 700, 800, 900].map((step) => [step, `var(--color-${role}-${step})`]));

// Escala de espaciado con densidad 1.25× (paso de 5px). Los pasos que el
// sistema define como token (1, 2, 3, 4, 6, 8) apuntan a su variable.
const tokenSteps = [1, 2, 3, 4, 6, 8];
const spacing = { px: '1px', 0: '0px' };
for (const step of [0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 16, 20, 24, 28, 32, 36, 40, 44, 48, 52, 56, 60, 64, 72, 80, 96]) {
    spacing[step] = tokenSteps.includes(step) ? `var(--space-${step})` : `${step * 5}px`;
}

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    theme: {
        spacing,
        colors: {
            transparent: 'transparent',
            current: 'currentColor',
            white: '#ffffff',
            black: '#000000',
            bg: 'var(--color-bg)',
            surface: 'var(--color-surface)',
            ink: 'var(--color-text)',
            divider: 'var(--color-divider)',
            'process-yellow': 'var(--color-process-yellow)',
            neutral: ramp('neutral'),
            accent: { DEFAULT: 'var(--color-accent)', ...ramp('accent') },
            'accent-2': { DEFAULT: 'var(--color-accent-2)', ...ramp('accent-2') },
        },
        fontFamily: {
            sans: ['var(--font-body)'],
            serif: ['var(--font-body)'],
            heading: ['var(--font-heading)'],
        },
        borderRadius: {
            none: '0px',
            sm: 'var(--radius-sm)',
            DEFAULT: 'var(--radius-md)',
            md: 'var(--radius-md)',
            lg: 'var(--radius-lg)',
            full: '9999px',
        },
        boxShadow: {
            none: 'none',
            sm: 'var(--shadow-sm)',
            md: 'var(--shadow-md)',
            lg: 'var(--shadow-lg)',
        },
        extend: {
            // Tamaños en px que usa el prototipo (text-13, text-15, text-30…).
            fontSize: {
                10: ['10px', { lineHeight: '1.4' }],
                11: ['11px', { lineHeight: '1.45' }],
                12: ['12px', { lineHeight: '1.5' }],
                13: ['13px', { lineHeight: '1.5' }],
                14: ['14px', { lineHeight: '1.55' }],
                15: ['15px', { lineHeight: '1.55' }],
                16: ['16px', { lineHeight: '1.5' }],
                17: ['17px', { lineHeight: '1.2' }],
                18: ['18px', { lineHeight: '1.3' }],
                20: ['20px', { lineHeight: '1.25' }],
                22: ['22px', { lineHeight: '1.2' }],
                24: ['24px', { lineHeight: '1.15' }],
                28: ['28px', { lineHeight: '1.12' }],
                30: ['30px', { lineHeight: '1.12' }],
                32: ['32px', { lineHeight: '1.1' }],
                44: ['44px', { lineHeight: '1.08' }],
            },
            maxWidth: {
                page: '1240px',
            },
        },
    },

    // 'class': el plugin no toca los inputs globalmente; el aspecto lo da .input.
    plugins: [forms({ strategy: 'class' })],
};
