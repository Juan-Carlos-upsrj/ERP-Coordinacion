import React from 'react';
import { motion } from 'framer-motion';

/**
 * Interface for Group Data
 */
interface GroupPerformance {
  id: string;
  name: string;
  performance: number;
}

/**
 * Mock Data based on Career IAEV
 */
const MOCK_GROUPS: GroupPerformance[] = [
  { id: '1', name: 'IAEV-39', performance: 85 },
  { id: '2', name: 'IAEV-40', performance: 92 },
  { id: '3', name: 'IAEV-41', performance: 78 },
  { id: '4', name: 'IAEV-42', performance: 65 },
  { id: '5', name: 'IAEV-43', performance: 45 },
  { id: '6', name: 'IAEV-EXT-1', performance: 30 }, // Adding extra for scroll testing
];

/**
 * RendimientoGrupo Component
 * A premium administrative widget for academic ERPs.
 * 
 * @returns {JSX.Element}
 */
const RendimientoGrupo: React.FC = () => {
  return (
    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col h-[400px] w-full max-w-md overflow-hidden">
      
      {/* Header section */}
      <header className="flex justify-between items-center mb-6 shrink-0">
        <h3 className="text-sm font-extrabold text-slate-800 tracking-tight uppercase">
          Rendimiento por Grupo
        </h3>
        <svg 
          className="w-5 h-5 text-slate-400" 
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24" 
          xmlns="http://www.w3.org/2000/svg"
        >
          <path 
            strokeLinecap="round" 
            strokeLinejoin="round" 
            strokeWidth={2} 
            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" 
          />
        </svg>
      </header>

      {/* Body List with custom minimal scrollbar */}
      <div className="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-5">
        {MOCK_GROUPS.map((group, index) => (
          <motion.div 
            key={group.id}
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: index * 0.1 }}
            className="flex flex-col gap-2 group"
          >
            {/* Context Title & Pct */}
            <div className="flex justify-between items-end">
              <span className="text-sm font-bold text-slate-700 tracking-tight group-hover:text-blue-600 transition-colors">
                {group.name}
              </span>
              <span className="text-sm font-extrabold text-slate-900">
                {group.performance}%
              </span>
            </div>

            {/* Progress Track */}
            <div className="h-2.5 w-full bg-slate-50 rounded-full overflow-hidden relative">
              {/* Progress Fill */}
              <motion.div 
                initial={{ width: 0 }}
                animate={{ width: `${group.performance}%` }}
                transition={{ duration: 1, ease: 'easeOut', delay: index * 0.1 + 0.3 }}
                className={`h-full rounded-full ${
                  group.performance >= 85 ? 'bg-blue-500' : 
                  group.performance >= 70 ? 'bg-indigo-400' : 'bg-rose-400'
                }`}
              />
            </div>
          </motion.div>
        ))}
      </div>

      {/* Custom Styles for minimalist scrollbar */}
      <style jsx>{`
        .custom-scrollbar::-webkit-scrollbar {
          width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
          background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
          background: #f1f5f9;
          border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
          background: #e2e8f0;
        }
      `}</style>
    </div>
  );
};

export default RendimientoGrupo;
