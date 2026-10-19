import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import colors from 'tailwindcss/colors';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            // Accento dell'app: blu sobrio. Le classi "indigo-*" usate nelle pagine puntano al blu,
            // così il colore si cambia qui in un punto solo.
            colors: {
                indigo: colors.blue,
            },
            // Font comuni a tutti i prodotti Fusionsoft.
            fontFamily: {
                sans: ['Geist', ...defaultTheme.fontFamily.sans],
                mono: ['"Geist Mono"', ...defaultTheme.fontFamily.mono],
            },
        },
    },

    plugins: [forms],
};
