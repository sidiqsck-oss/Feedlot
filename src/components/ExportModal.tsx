'use client';

import { useState } from 'react';
import { 
  generateCattleReport, 
  generateSalesReport, 
  generateHealthReport, 
  generateFeedReport 
} from '@/services\exportService';
import { downloadCSV, downloadJSON, exportToPDF } from '@/utils\exportUtils';
import { X, Download, FileText, FileSpreadsheet, FileJson, FilePdf } from 'lucide-react';

interface ExportModalProps {
  isOpen: boolean;
  onClose: () => void;
  reportType: 'cattle' | 'sales' | 'health' | 'feed' | null;
}

export default function ExportModal({ isOpen, onClose, reportType }: ExportModalProps) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  if (!isOpen || !reportType) return null;

  const handleExport = async (format: 'csv' | 'json' | 'pdf') => {
    setLoading(true);
    setError('');

    try {
      let content;
      let filename = '';

      switch (reportType) {
        case 'cattle':
          content = await generateCattleReport(format);
          filename = `cattle-report-${new Date().toISOString().split('T')[0]}.${format}`;
          break;
        case 'sales':
          content = await generateSalesReport(format);
          filename = `sales-report-${new Date().toISOString().split('T')[0]}.${format}`;
          break;
        case 'health':
          content = await generateHealthReport(format);
          filename = `health-report-${new Date().toISOString().split('T')[0]}.${format}`;
          break;
        case 'feed':
          content = await generateFeedReport(format);
          filename = `feed-report-${new Date().toISOString().split('T')[0]}.${format}`;
          break;
        default:
          throw new Error('Invalid report type');
      }

      if (format === 'csv' && typeof content === 'string') {
        downloadCSV(content, filename);
      } else if (format === 'json' && typeof content === 'string') {
        downloadJSON(content, filename);
      } else if (format === 'pdf' && typeof content === 'object') {
        exportToPDF(content, filename);
      }
    } catch (err) {
      console.error('Export error:', err);
      setError('Failed to generate report. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div className="flex justify-between items-center p-6 border-b border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900">Export Report</h3>
          <button 
            onClick={onClose}
            className="text-gray-400 hover:text-gray-500"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="p-6">
          {error && (
            <div className="bg-red-50 text-red-700 p-3 rounded-md mb-4">
              {error}
            </div>
          )}

          <p className="text-gray-600 mb-6">
            Select the format to export your {reportType} report:
          </p>

          <div className="space-y-3">
            <button
              onClick={() => handleExport('csv')}
              disabled={loading}
              className="w-full flex items-center justify-between px-4 py-3 bg-green-100 text-green-800 rounded-lg hover:bg-green-200 transition duration-200 disabled:opacity-50"
            >
              <span className="font-medium">CSV Format</span>
              <FileSpreadsheet className="h-5 w-5" />
            </button>

            <button
              onClick={() => handleExport('json')}
              disabled={loading}
              className="w-full flex items-center justify-between px-4 py-3 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition duration-200 disabled:opacity-50"
            >
              <span className="font-medium">JSON Format</span>
              <FileJson className="h-5 w-5" />
            </button>

            <button
              onClick={() => handleExport('pdf')}
              disabled={loading}
              className="w-full flex items-center justify-between px-4 py-3 bg-red-100 text-red-800 rounded-lg hover:bg-red-200 transition duration-200 disabled:opacity-50"
            >
              <span className="font-medium">PDF Format</span>
              <FilePdf className="h-5 w-5" />
            </button>
          </div>

          {loading && (
            <div className="mt-4 flex justify-center">
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div>
            </div>
          )}
        </div>

        <div className="p-4 bg-gray-50 rounded-b-xl">
          <button
            onClick={onClose}
            className="w-full flex justify-center items-center px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition duration-200"
          >
            <X className="h-5 w-5 mr-2" />
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
}