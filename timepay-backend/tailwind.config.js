import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                timepay: {
                    primary: '#059669',
                    primaryDark: '#047857',
                    mint: '#A7F3D0',
                    teal: '#14B8A6',
                    surface: '#F8FAFC',
                    ink: '#0F172A',
                },
            },
            boxShadow: {
                'timepay-sm': '0 8px 24px rgba(15, 23, 42, 0.06)',
                'timepay-glow': '0 12px 30px rgba(5, 150, 105, 0.18)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
