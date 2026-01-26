/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./site/templates/**/*.php",
    "./site/snippets/**/*.php",
    "./assets/js/**/*.js",
  ],
  theme: {
    extend: {
      colors: {
        // Add custom colors inspired by cycling/outdoor themes
        'ride-primary': '#2563eb', // Blue for tracks/links
        'ride-secondary': '#16a34a', // Green for elevation
        'ride-accent': '#ea580c', // Orange for highlights
      },
      fontFamily: {
        'sans': ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
}
