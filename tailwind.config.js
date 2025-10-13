import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, typography],
    
    // Safelist important classes to ensure they're always included
    safelist: [
        // Background colors
        'bg-slate-50', 'bg-slate-100', 'bg-slate-800', 'bg-slate-900',
        'bg-blue-50', 'bg-blue-100', 'bg-blue-500', 'bg-blue-600', 'bg-blue-700', 'bg-blue-800', 'bg-blue-900',
        'bg-green-50', 'bg-green-100', 'bg-green-400', 'bg-green-500', 'bg-green-600', 'bg-green-800',
        'bg-purple-50', 'bg-purple-100', 'bg-purple-500', 'bg-purple-600', 'bg-purple-800',
        'bg-amber-50', 'bg-amber-100', 'bg-amber-300', 'bg-amber-400', 'bg-amber-500', 'bg-amber-600', 'bg-amber-800',
        'bg-red-50', 'bg-red-100', 'bg-red-400', 'bg-red-800',
        'bg-gray-50', 'bg-gray-100', 'bg-gray-800',
        
        // Text colors
        'text-slate-900', 'text-blue-200', 'text-blue-600', 'text-blue-800',
        'text-green-800', 'text-purple-800', 'text-amber-300', 'text-amber-800',
        'text-red-300', 'text-red-700', 'text-red-800', 'text-gray-400', 'text-gray-500', 'text-gray-600', 'text-gray-700', 'text-gray-800', 'text-gray-900',
        
        // Border colors
        'border-amber-400', 'border-red-400', 'border-gray-100', 'border-gray-200', 'border-gray-300',
        
        // Gradients
        'from-slate-50', 'via-blue-50', 'to-slate-100',
        'from-slate-900', 'via-blue-900', 'to-slate-900',
        'from-slate-800', 'via-blue-900', 'to-slate-800',
        'from-blue-500', 'to-blue-600', 'from-blue-600', 'to-blue-700', 'from-blue-700', 'to-blue-800',
        'from-green-500', 'to-green-600',
        'from-purple-500', 'to-purple-600',
        'from-amber-400', 'to-amber-600', 'from-amber-500', 'to-amber-600', 'from-amber-400', 'to-amber-500',
        
        // Hover states
        'hover:bg-blue-50', 'hover:bg-blue-700', 'hover:bg-blue-800', 'hover:bg-gray-50', 'hover:bg-gray-600',
        'hover:text-blue-600', 'hover:text-amber-300', 'hover:text-gray-700', 'hover:text-red-300',
        'hover:from-blue-700', 'hover:to-blue-800', 'hover:from-amber-400', 'hover:to-amber-500',
        
        // Focus states
        'focus:ring-blue-500', 'focus:border-blue-500', 'focus:ring-amber-400',
        
        // Rounded corners
        'rounded-full', 'rounded-lg', 'rounded-xl',
        
        // Shadows
        'shadow-lg', 'shadow-xl', 'shadow-2xl',
        
        // Transforms
        'hover:scale-105', 'transform', 'transition-all', 'transition-colors', 'transition-shadow',
        
        // Animation
        'animate-pulse'
    ],
};
