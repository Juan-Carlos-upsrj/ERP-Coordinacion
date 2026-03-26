import React from 'react';

/**
 * GestorHorarios
 * Widget de acceso al planificador de horarios académicos.
 */
const GestorHorarios: React.FC = () => {
  return (
    <div className="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl p-6 shadow-lg shadow-blue-500/30 flex flex-col items-center justify-center text-center text-white h-[200px] w-full">
      
      {/* Icono */}
      <div className="bg-white/20 p-3 rounded-full mb-4">
        <svg 
          className="w-6 h-6 text-white" 
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path 
            strokeLinecap="round" 
            strokeLinejoin="round" 
            strokeWidth={2} 
            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" 
          />
        </svg>
      </div>

      {/* Título */}
      <h3 className="font-extrabold text-base uppercase tracking-tight">
        Gestor de Horarios
      </h3>

      {/* Subtítulo */}
      <p className="text-xs font-medium text-white/80 uppercase tracking-widest mt-1 mb-5">
        Configuración Académica
      </p>

      {/* Botón de acción */}
      <a
        href="index.php?v=horarios"
        className="bg-white/20 hover:bg-white/30 transition-colors text-white font-bold text-xs uppercase tracking-wide px-5 py-2 rounded-full flex items-center gap-2"
      >
        ACCEDER
        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
        </svg>
      </a>
    </div>
  );
};

export default GestorHorarios;
