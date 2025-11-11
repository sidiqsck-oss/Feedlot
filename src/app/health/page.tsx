'use client';

import { useState, useEffect } from 'react';
import { getHealthRecords } from '@/services\healthService';
import { HealthRecord } from '@prisma/client';
import HealthRecordForm from '@/components\HealthRecordForm';
import ExportButton from '@/components\ExportButton';
import { Stethoscope, Calendar, Syringe, AlertTriangle, Search, PlusCircle } from 'lucide-react';
import { useSession } from 'next-auth/react';
import { format } from 'date-fns';

export default function HealthPage() {
  const [healthRecords, setHealthRecords] = useState<HealthRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [showRecordForm, setShowRecordForm] = useState(false);
  const [selectedCattleId, setSelectedCattleId] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const { data: session } = useSession();
  const userId = session?.user?.id || '';

  useEffect(() => {
    const fetchHealthRecords = async () => {
      try {
        const records = await getHealthRecords();
        setHealthRecords(records);
      } catch (error) {
        console.error('Error fetching health records:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchHealthRecords();
  }, []);

  const handleRecordCreated = () => {
    // Refresh health records list after creation
    const fetchHealthRecords = async () => {
      try {
        const records = await getHealthRecords();
        setHealthRecords(records);
      } catch (error) {
        console.error('Error fetching health records:', error);
      }
    };

    fetchHealthRecords();
    setShowRecordForm(false);
  };

  // Filter health records based on search term
  const filteredRecords = healthRecords.filter(record => 
    record.cattleId.toLowerCase().includes(searchTerm.toLowerCase()) ||
    record.diagnosis?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    record.treatment?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    record.status?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center">
            <Stethoscope className="w-8 h-8 mr-3 text-primary-600" />
            Health Management
          </h1>
          <p className="text-gray-600 mt-2">Track and manage cattle health records</p>
        </div>
        <div className="mt-4 md:mt-0 flex space-x-3">
          <ExportButton reportType="health" />
          <button
            onClick={() => setShowRecordForm(true)}
            className="flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
          >
            <PlusCircle className="w-5 h-5 mr-2" />
            Add Health Record
          </button>
        </div>
      </div>

      {showRecordForm && userId && (
        <div className="mb-8">
          <HealthRecordForm 
            cattleId={selectedCattleId || 'temp'} 
            userId={userId} 
            onRecordCreated={handleRecordCreated} 
          />
        </div>
      )}

      {/* Search Bar */}
      <div className="mb-6">
        <div className="relative">
          <input
            type="text"
            placeholder="Search health records by cattle ID, diagnosis, treatment, or status..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
          />
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
        </div>
      </div>

      {loading ? (
        <div className="text-center py-10">
          <div className="inline-block animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-primary-600"></div>
          <p className="mt-4 text-gray-600">Loading health records...</p>
        </div>
      ) : (
        <div className="space-y-6">
          {filteredRecords.map((record) => {
            const isUrgent = record.status === 'active';
            return (
              <div 
                key={record.id} 
                className={`bg-white rounded-xl shadow-md overflow-hidden border ${
                  isUrgent ? 'border-red-300' : 'border-gray-200'
                }`}
              >
                <div className="p-6">
                  <div className="flex justify-between items-start">
                    <div>
                      <div className="flex items-center">
                        <h3 className="text-lg font-semibold text-gray-900">Cattle ID: {record.cattleId}</h3>
                        {isUrgent && (
                          <span className="flex items-center ml-3 px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                            <AlertTriangle className="w-3 h-3 mr-1" />
                            URGENT
                          </span>
                        )}
                      </div>
                      <p className="text-gray-600 text-sm">
                        {format(new Date(record.startDate), 'PPP')} 
                        {record.endDate && ` - ${format(new Date(record.endDate), 'PPP')}`}
                      </p>
                    </div>
                    <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                      record.status === 'active' ? 'bg-red-100 text-red-800' :
                      record.status === 'recovered' ? 'bg-green-100 text-green-800' :
                      record.status === 'chronic' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {record.status}
                    </span>
                  </div>
                  
                  <div className="mt-4 space-y-3">
                    {record.symptoms.length > 0 && (
                      <div>
                        <p className="text-gray-600 text-sm">Symptoms</p>
                        <div className="flex flex-wrap gap-2 mt-1">
                          {record.symptoms.map((symptom, index) => (
                            <span key={index} className="px-2 py-1 bg-red-50 text-red-700 rounded-full text-xs">
                              {symptom}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                    
                    {record.diagnosis && (
                      <div>
                        <p className="text-gray-600 text-sm">Diagnosis</p>
                        <p className="font-medium">{record.diagnosis}</p>
                      </div>
                    )}
                    
                    {record.treatment && (
                      <div>
                        <p className="text-gray-600 text-sm">Treatment</p>
                        <div className="flex items-center">
                          <Syringe className="w-4 h-4 mr-2 text-primary-600" />
                          <p className="font-medium">{record.treatment}</p>
                        </div>
                      </div>
                    )}
                    
                    {record.medication && (
                      <div>
                        <p className="text-gray-600 text-sm">Medication</p>
                        <p className="font-medium">{record.medication}</p>
                      </div>
                    )}
                    
                    {record.notes && (
                      <div>
                        <p className="text-gray-600 text-sm">Notes</p>
                        <p className="font-medium">{record.notes}</p>
                      </div>
                    )}
                  </div>
                  
                  <div className="mt-4 pt-4 border-t border-gray-200 flex justify-end">
                    <button className="text-xs px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-200">
                      View Details
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {filteredRecords.length === 0 && !loading && (
        <div className="text-center py-10">
          <Stethoscope className="w-16 h-16 mx-auto text-gray-400" />
          <h3 className="mt-4 text-lg font-medium text-gray-900">No health records found</h3>
          <p className="mt-2 text-gray-600">
            {searchTerm ? 'No records match your search criteria.' : 'Get started by recording your first health issue.'}
          </p>
          {!searchTerm && (
            <button
              onClick={() => setShowRecordForm(true)}
              className="mt-4 inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
            >
              <PlusCircle className="w-5 h-5 mr-2" />
              Add Health Record
            </button>
          )}
        </div>
      )}
    </div>
  );
}