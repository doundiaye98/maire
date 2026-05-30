/**
 * Configuration Tailwind — extraite du header inline pour build local.
 *
 * Pour générer le CSS :
 *   npm install
 *   npm run build         # production minifiée
 *   npm run dev           # mode watch pour le développement
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  // On scanne tous les fichiers PHP/HTML/JS qui peuvent contenir des classes Tailwind.
  content: [
    './*.php',
    './admin/**/*.php',
    './super-admin/**/*.php',
    './citoyen/**/*.php',
    './api/**/*.php',
    './includes/**/*.php',
    './src/**/*.{html,js,php}',
    './assets/js/**/*.js',
  ],
  // IMPORTANT : preflight: false → cohabite proprement avec assets/css/style.css legacy.
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {
      colors: {
        mairie: {
          50:  '#f0f9f5',
          100: '#dcf0e6',
          200: '#bbe1cd',
          300: '#8eccac',
          400: '#5db088',
          500: '#3d9670',
          600: '#2e7a5b',
          700: '#1e5f48',
          800: '#0c4a3e',
          900: '#0a3c34',
          950: '#03241e',
        },
        gold: {
          50:  '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
          950: '#451a03',
        },
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
        serif: ['Lora', 'Georgia', 'serif'],
      },
      boxShadow: {
        glow: '0 10px 40px -10px rgba(12, 74, 62, 0.45)',
        card: '0 4px 20px -2px rgba(15, 23, 42, 0.08)',
        'card-hover': '0 20px 40px -8px rgba(15, 23, 42, 0.18)',
        panel: '0 24px 60px -24px rgba(15, 23, 42, 0.28)',
        luxury: '0 30px 80px -36px rgba(2, 6, 23, 0.38)',
        insetline: 'inset 0 1px 0 rgba(255,255,255,0.45)',
      },
      backdropBlur: {
        xs: '2px',
      },
      backgroundImage: {
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
      },
      animation: {
        'fade-up': 'fadeUp 0.6s ease-out',
        'fade-in': 'fadeIn 0.5s ease-out',
        shimmer: 'shimmer 2s linear infinite',
        'float-soft': 'floatSoft 7s ease-in-out infinite',
        'pulse-soft': 'pulseSoft 3.4s ease-in-out infinite',
      },
      keyframes: {
        fadeUp: {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        shimmer: {
          '0%': { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
        floatSoft: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-8px)' },
        },
        pulseSoft: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '.72' },
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
  ],
};
