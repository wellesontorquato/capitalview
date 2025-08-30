import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        brand: {
          50:  '#f2f7ff',
          100: '#e6effe',
          200: '#c7dafc',
          300: '#9bbcf8',
          400: '#6f99f1',
          500: '#4a7deb', // primária
          600: '#2f62d6',
          700: '#244db1',
          800: '#1f418c',
          900: '#1e3b73',
        },
        ink: {
          900: '#0b1220',
          800: '#121a2b',
          700: '#172236',
          600: '#1d2a42',
        },
      },
      boxShadow: {
        card: '0 10px 30px rgba(16, 42, 112, .08)',
      },
      borderRadius: {
        xl: '14px',
        '2xl': '18px',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
