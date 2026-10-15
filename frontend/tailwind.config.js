/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      colors: { brand: { 50: "#f0fdf4", 100: "#dcfce7", 500: "#22a559", 600: "#178a47", 700: "#146c3b", 900: "#0b3d22" } },
    },
  },
  plugins: [],
};
