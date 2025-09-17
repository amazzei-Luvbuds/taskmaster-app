/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Department-specific colors from architecture
        'sales-primary': '#10B981',
        'sales-accent': '#ECFDF5',
        'accounting-primary': '#3B82F6',
        'accounting-accent': '#EFF6FF',
        'tech-primary': '#8B5CF6',
        'tech-accent': '#F3E8FF',
        'marketing-primary': '#F59E0B',
        'marketing-accent': '#FFFBEB',
        'hr-primary': '#EF4444',
        'hr-accent': '#FEF2F2',
      },
      animation: {
        'fade-in': 'fadeIn 0.2s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
      },
    },
  },
  plugins: [],
}