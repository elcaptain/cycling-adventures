/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./site/templates/**/*.php",
    "./site/snippets/**/*.php",
    "./assets/js/**/*.js",
  ],
  theme: {
    container: {
      center: true,
      padding: '1rem',
    },
    extend: {
      colors: {
        // Brand palette from Figma design (purple-violet system)
        accent: {
          50:  '#f6f3ff', // page background, marker base
          200: '#bea7ff', // footer text
          500: '#722fd2', // primary / logo
          700: '#4c0097', // strong purple (marker icons)
          800: '#33006b', // stat values, secondary text
          900: '#1f0046', // headings, body text, footer bg
        },
        'spring-green': {
          DEFAULT: '#16a34a', // links / accent green
        },
        // Legacy aliases (kept so existing classes don't break)
        'ride-primary':   '#722fd2',
        'ride-secondary': '#16a34a',
        'ride-accent':    '#4c0097',
      },
      fontFamily: {
        'sans':    ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
        'display': ['DynaPuff', 'Inter', 'system-ui', 'sans-serif'],
      },
      fontSize: {
        'stat-label': ['11px', { lineHeight: '20px', letterSpacing: '0.35px' }],
        'stat-value': ['30px', { lineHeight: '36px' }],
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
}
