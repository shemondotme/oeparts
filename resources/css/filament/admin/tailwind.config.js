export default {
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './resources/views/components/admin/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Geist', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
        },
    },
}
