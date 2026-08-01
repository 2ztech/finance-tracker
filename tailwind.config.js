/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./templates/**/*.php', './public/**/*.php'],
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: { sans: ['Outfit', 'sans-serif'] },
    },
  },
  plugins: [],
}
