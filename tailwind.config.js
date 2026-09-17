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
                    primary: '#0055CC',
                    hover: '#0044AA',
                    pressed: '#003388',
                    canvas: '#F7F6F3',
                    surface: '#FFFFFF',
                    text: '#0A1128',
                    muted: '#475569',
                    border: '#E2E8F0',
                    success: '#16A34A',
                    warning: '#F59E0B',
                    danger: '#B42318',
                    info: '#0284C7',
                },
            },
            fontFamily: {
                sans: ['Inter', 'Cairo', ...defaultTheme.fontFamily.sans],
                arabic: ['Cairo', 'sans-serif'],
                english: ['Inter', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
