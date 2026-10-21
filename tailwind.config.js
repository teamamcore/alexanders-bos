import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/spatie/laravel-passkeys/resources/views/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Batsons BOS — deep navy brand + copper accent (plumbing trade).
                brand: {
                    50: '#f1f6fb',
                    100: '#dfeaf5',
                    200: '#c3d7ea',
                    300: '#9abbd9',
                    400: '#6b98c4',
                    500: '#4a7bae',
                    600: '#386292',
                    700: '#2f5077',
                    800: '#2a4463',
                    900: '#1b2f47',
                    950: '#0f1d2e',
                },
                accent: {
                    50: '#fdf5ee',
                    100: '#fae7d6',
                    200: '#f4cbab',
                    300: '#eda876',
                    400: '#e57d3f',
                    500: '#df611f',
                    600: '#cf4a15',
                    700: '#ab3714',
                    800: '#892d18',
                    900: '#6f2716',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 29 46 / 0.04), 0 1px 3px 0 rgb(15 29 46 / 0.06)',
            },
        },
    },

    plugins: [forms],
};
