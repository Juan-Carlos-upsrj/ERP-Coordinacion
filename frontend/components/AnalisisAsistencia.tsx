import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

/**
 * Mock Data for Attendance Analysis
 */
const CUATRIMESTRAL_DATA = [
  { mes: 'Enero', asistencia: 88 },
  { mes: 'Feb', asistencia: 92 },
  { mes: 'Mar', asistencia: 84 },
  { mes: 'Abr', asistencia: 96 },
];

const SEMANAL_DATA = [
  { mes: 'Sem 1', asistencia: 85 },
  { mes: 'Sem 2', asistencia: 89 },
  { mes: 'Sem 3', asistencia: 94 },
  { mes: 'Sem 4', asistencia: 91 },
];

/**
 * Custom Tooltip Component
 */
const CustomTooltip = ({ active, payload, label }: any) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white p-3 border border-slate-100 shadow-lg rounded-xl">
        <p className="text-xs font-bold text-slate-500 uppercase mb-1">{label}</p>
        <p className="text-sm font-extrabold text-blue-600">
          Asistencia: {payload[0].value}%
        </p>
      </div>
    );
  }
  return null;
};

/**
 * AnalisisAsistencia Component
 * A premium administrative widget for academic ERPs visualizing attendance trends.
 */
const AnalisisAsistencia: React.FC = () => {
  const [view, setView] = useState<'Semanal' | 'Cuatrimestral'>('Cuatrimestral');

  const data = view === 'Cuatrimestral' ? CUATRIMESTRAL_DATA : SEMANAL_DATA;

  return (
    <motion.div 
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, ease: 'easeOut' }}
      className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col h-[400px] w-full"
    >
      
      {/* Header section */}
      <header className="flex justify-between items-center mb-8">
        <h3 className="text-sm font-extrabold text-slate-800 tracking-tight uppercase">
          ANÁLISIS DE ASISTENCIA
        </h3>
        
        {/* Pill Selector */}
        <div className="flex bg-slate-50 p-1 rounded-full border border-slate-100">
          <button
            onClick={() => setView('Semanal')}
            className={`px-4 py-1.5 text-xs font-bold transition-all duration-200 rounded-full ${
              view === 'Semanal'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-700'
            }`}
          >
            Semanal
          </button>
          <button
            onClick={() => setView('Cuatrimestral')}
            className={`px-4 py-1.5 text-xs font-bold transition-all duration-200 rounded-full ${
              view === 'Cuatrimestral'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-700'
            }`}
          >
            Cuatrimestral
          </button>
        </div>
      </header>

      {/* Chart Body */}
      <div className="flex-1 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart
            data={data}
            margin={{ top: 5, right: 10, left: -20, bottom: 0 }}
          >
            <CartesianGrid 
              vertical={false} 
              stroke="#f1f5f9" // slate-100
              strokeDasharray="3 3"
            />
            <XAxis 
              dataKey="mes" 
              axisLine={false}
              tickLine={false}
              tick={{ fill: '#94a3b8', fontSize: 12 }} // slate-400
              dy={10}
            />
            <YAxis 
              domain={[50, 100]}
              ticks={[50, 60, 70, 80, 90, 100]}
              axisLine={false}
              tickLine={false}
              tick={{ fill: '#94a3b8', fontSize: 12 }}
              dx={-5}
            />
            <Tooltip content={<CustomTooltip />} />
            <Line
              type="monotone"
              dataKey="asistencia"
              stroke="#2563eb" // blue-600
              strokeWidth={4}
              dot={{ 
                r: 5, 
                fill: '#fff', 
                stroke: '#1e40af', // blue-800
                strokeWidth: 2 
              }}
              activeDot={{ 
                r: 7, 
                fill: '#2563eb', 
                stroke: '#fff', 
                strokeWidth: 2 
              }}
              animationDuration={1000}
            />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </motion.div>
  );
};

export default AnalisisAsistencia;
