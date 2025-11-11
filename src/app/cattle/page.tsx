'use client';

import { useState, useEffect } from 'react';
import { getCattle } from '@/services/cattleService';
import { Cattle } from '@prisma/client';
import CattlePurchaseForm from '@/components\CattlePurchaseForm';
import ExportButton from '@/components\ExportButton';
import { Search, Plus, Cattle as CattleIcon, Scale, Syringe, DollarSign } from 'lucide-react';
import Link from 'next/link';

export default function CattlePage() {
  const [cattleList, setCattleList] = useState<Cattle[]>([]);
  const [loading, setLoading] = useState(true);
  const [showPurchaseForm, setShowPurchaseForm] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    const fetchCattle = async () => {
      try {
        const cattle = await getCattle();
        setCattleList(cattle);
      } catch (error) {
        console.error('Error fetching cattle:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchCattle();
  }, []);

  const handlePurchaseComplete = () => {
    // Refresh cattle list after purchase
    const fetchCattle = async () => {
      try {
        const cattle = await getCattle();
        setCattleList(cattle);
      } catch (error) {
        console.error('Error fetching cattle:', error);
      }
    };

    fetchCattle();
    setShowPurchaseForm(false);
  };

  // Filter cattle based on search term
  const filteredCattle = cattleList.filter(cattle => 
    cattle.cattleId.toLowerCase().includes(searchTerm.toLowerCase()) ||
    cattle.breed.toLowerCase().includes(searchTerm.toLowerCase()) ||
    cattle.status.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center">
            <CattleIcon className="w-8 h-8 mr-3 text-primary-600" />
            Cattle Management
          </h1>
          <p className="text-gray-600 mt-2">Manage your cattle inventory and track their progress</p>
        </div>
        <div className="mt-4 md:mt-0 flex space-x-3">
          <ExportButton reportType="cattle" />
          <button
            onClick={() => setShowPurchaseForm(true)}
            className="flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
          >
            <Plus className="w-5 h-5 mr-2" />
            Add Cattle
          </button>
        </div>
      </div>

      {showPurchaseForm && (
        <div className="mb-8">
          <CattlePurchaseForm onPurchaseComplete={handlePurchaseComplete} />
        </div>
      )}

      {/* Search Bar */}
      <div className="mb-6">
        <div className="relative">
          <input
            type="text"
            placeholder="Search cattle by ID, breed, or status..."
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
          <p className="mt-4 text-gray-600">Loading cattle records...</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredCattle.map((cattle) => (
            <div key={cattle.id} className="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
              <div className="p-6">
                <div className="flex justify-between items-start">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">{cattle.cattleId}</h3>
                    <p className="text-gray-600">{cattle.breed} • {cattle.gender}</p>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                    cattle.status === 'ACTIVE' ? 'bg-green-100 text-green-800' :
                    cattle.status === 'SOLD' ? 'bg-blue-100 text-blue-800' :
                    cattle.status === 'SICK' ? 'bg-red-100 text-red-800' :
                    cattle.status === 'QUARANTINE' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-gray-100 text-gray-800'
                  }`}>
                    {cattle.status}
                  </span>
                </div>
                
                <div className="mt-4 space-y-2">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Initial Weight:</span>
                    <span className="font-medium">{cattle.initialWeight?.toString()} kg</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Current Weight:</span>
                    <span className="font-medium">{cattle.currentWeight?.toString()} kg</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Location:</span>
                    <span className="font-medium">{cattle.location || 'N/A'}</span>
                  </div>
                </div>
                
                <div className="mt-6 flex space-x-2">
                  <Link 
                    href={`/cattle/${cattle.id}/reweight`}
                    className="flex-1 flex items-center justify-center px-3 py-2 bg-primary-100 text-primary-700 rounded-lg hover:bg-primary-200 transition duration-200 text-sm"
                  >
                    <Scale className="w-4 h-4 mr-1" />
                    Weight
                  </Link>
                  <Link 
                    href={`/cattle/${cattle.id}/health`}
                    className="flex-1 flex items-center justify-center px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition duration-200 text-sm"
                  >
                    <Syringe className="w-4 h-4 mr-1" />
                    Health
                  </Link>
                  <Link 
                    href={`/cattle/${cattle.id}/details`}
                    className="flex-1 flex items-center justify-center px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-200 text-sm"
                  >
                    <DollarSign className="w-4 h-4 mr-1" />
                    Details
                  </Link>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {filteredCattle.length === 0 && !loading && (
        <div className="text-center py-10">
          <CattleIcon className="w-16 h-16 mx-auto text-gray-400" />
          <h3 className="mt-4 text-lg font-medium text-gray-900">No cattle found</h3>
          <p className="mt-2 text-gray-600">
            {searchTerm ? 'No cattle match your search criteria.' : 'Get started by adding your first cattle.'}
          </p>
          {!searchTerm && (
            <button
              onClick={() => setShowPurchaseForm(true)}
              className="mt-4 inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
            >
              <Plus className="w-5 h-5 mr-2" />
              Add Cattle
            </button>
          )}
        </div>
      )}
    </div>
  );
}