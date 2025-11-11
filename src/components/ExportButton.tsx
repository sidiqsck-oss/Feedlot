'use client';

import { useState } from 'react';
import ExportModal from './ExportModal';
import { Download } from 'lucide-react';

interface ExportButtonProps {
  reportType: 'cattle' | 'sales' | 'health' | 'feed';
  variant?: 'default' | 'icon';
}

export default function ExportButton({ reportType, variant = 'default' }: ExportButtonProps) {
  const [modalOpen, setModalOpen] = useState(false);

  const getReportName = () => {
    switch (reportType) {
      case 'cattle': return 'Cattle';
      case 'sales': return 'Sales';
      case 'health': return 'Health';
      case 'feed': return 'Feed';
      default: return 'Report';
    }
  };

  return (
    <>
      {variant === 'default' ? (
        <button
          onClick={() => setModalOpen(true)}
          className="flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
        >
          <Download className="w-5 h-5 mr-2" />
          Export {getReportName()} Report
        </button>
      ) : (
        <button
          onClick={() => setModalOpen(true)}
          className="p-2 rounded-lg bg-primary-100 text-primary-600 hover:bg-primary-200 transition duration-200"
          title={`Export ${getReportName()} Report`}
        >
          <Download className="w-5 h-5" />
        </button>
      )}
      
      <ExportModal 
        isOpen={modalOpen} 
        onClose={() => setModalOpen(false)} 
        reportType={reportType} 
      />
    </>
  );
}