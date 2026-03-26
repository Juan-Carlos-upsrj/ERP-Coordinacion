import React from 'react';

/**
 * SugerenciasDesarrollo
 * Widget de buzón de feedback institucional.
 */
const SugerenciasDesarrollo: React.FC = () => {
  return (
    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col items-center justify-center text-center h-[200px] w-full">
      
      {/* Icono */}
      <div className="bg-blue-50 p-3 rounded-full mb-4">
        <svg 
          className="w-6 h-6 text-blue-400" 
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path 
            strokeLinecap="round" 
            strokeLinejoin="round" 
            strokeWidth={2} 
            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" 
          />
        </svg>
      </div>

      {/* Título */}
      <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-tight">
        Sugerencias de Desarrollo
      </h3>

      {/* Subtítulo */}
      <p className="text-xs font-medium text-slate-400 uppercase tracking-widest mt-1 mb-4">
        Buzón de Feedback Institucional
      </p>

      {/* Enlace de acción */}
      <a
        href="#"
        className="text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1 uppercase tracking-tight"
      >
        Ver Sugerencias
        <span>&rarr;</span>
      </a>
    </div>
  );
};

export default SugerenciasDesarrollo;
