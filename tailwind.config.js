import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['"Baloo 2"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: 'var(--baskit-primary)',
                    hover: 'var(--baskit-primary-hover)',
                    light: 'var(--baskit-primary-light)',
                },
                brand: {
                    DEFAULT: 'var(--baskit-brand-dark)',
                },
                panel: 'var(--baskit-panel)',
                surface: 'var(--baskit-surface)',
                canvas: 'var(--baskit-background)',
                stroke: 'var(--baskit-border)',
                ink: {
                    DEFAULT: 'var(--baskit-text)',
                    muted: 'var(--baskit-text-muted)',
                },
            },
        },
    },

    plugins: [forms],
};
