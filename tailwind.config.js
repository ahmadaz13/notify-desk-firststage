import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                notify: {
                    primary: '#0C86ED',
                    light: '#5AB2FF',
                    dark: '#050B0D',
                    charcoal: '#32383A',
                    bg: '#F6F9FC',
                    surface: '#FFFFFF',
                },
            },
            fontFamily: {
                sans: ['Cairo', 'Inter', ...defaultTheme.fontFamily.sans],
                arabic: ['Cairo', 'sans-serif'],
                english: ['Inter', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
