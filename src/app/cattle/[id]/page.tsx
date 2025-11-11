'use client';

import { useState, useEffect } from 'react';
import { useParams } from 'next/navigation';
import { getCattleById } from '@/services/cattleService';
import { Cattle } from '@prisma/client';
import CattleInductionForm from '@/components\CattleInductionForm';
import CattleReweightForm from '@/components\CattleReweightForm';
import { Cattle as CattleIcon, Calendar, Scale, Syringe, MapPin, DollarSign } from 'lucide-react';
import { format } from 'date-fns';

export default function CattleDetailsPage() {
  const { id } = useParams();
  const [cattle, setCattle] = useState<Cattle | null>(null);
  const [loading, setLoading] = useState(true);
  const [showInductionForm, setShowInductionForm] = useState(false);
  const [showReweightForm, setShowReweightForm] = useState(false);

  useEffect(() => {
    const fetchCattle = async () => {
      if (typeof id === 'string') {
        try {
          const cattleData = await getCattleById(id);
          setCattle(cattleData);
        } catch (error) {
          console.error('Error fetching cattle:', error);
        } finally {
          setLoading(false);
        }
      }
    };

    fetchCattle();
  }, [id]);

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center py-10">
          <div className="inline-block animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-primary-600"></div>
          <p className="mt-4 text-gray-600">Loading cattle details...</p>
        </div>
      </div>
    );
  }

  if (!cattle) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center py-10">
          <CattleIcon className="w-16 h-16 mx-auto text-gray-400" />
          <h3 className="mt-4 text-lg font-medium text-gray-900">Cattle not found</h3>
          <p className="mt-2 text-gray-600">The requested cattle could not be found.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex items-center mb-6">
        <CattleIcon className="w-8 h-8 mr-3 text-primary-600" />
        <h1 className="text-3xl font-bold text-gray-900">Cattle Details: {cattle.cattleId}</h1>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Cattle Information Card */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
            <h2 className="text-xl font-semibold text-gray-800 mb-4">Cattle Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p className="text-gray-600">Cattle ID</p>
                <p className="font-medium">{cattle.cattleId}</p>
              </div>
              <div>
                <p className="text-gray-600">Breed</p>
                <p className="font-medium">{cattle.breed}</p>
              </div>
              <div>
                <p className="text-gray-600">Gender</p>
                <p className="font-medium">{cattle.gender}</p>
              </div>
              <div>
                <p className="text-gray-600">Initial Weight</p>
                <p className="font-medium">{cattle.initialWeight?.toString()} kg</p>
              </div>
              <div>
                <p className="text-gray-600">Current Weight</p>
                <p className="font-medium">{cattle.currentWeight?.toString()} kg</p>
              </div>
              <div>
                <p className="text-gray-600">Location</p>
                <p className="font-medium">{cattle.location || 'N/A'}</p>
              </div>
              <div>
                <p className="text-gray-600">Status</p>
                <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                  cattle.status === 'ACTIVE' ? 'bg-green-100 text-green-800' :
                  cattle.status === 'SOLD' ? 'bg-blue-100 text-blue-800' :
                  cattle.status === 'SICK' ? 'bg-red-100 text-red-800' :
                  cattle.status === 'QUARANTINE' ? 'bg-yellow-100 text-yellow-800' :
                  'bg-gray-100 text-gray-800'
                }`}>
                  {cattle.status}
                </span>
              </div>
              <div>
                <p className="text-gray-600">Created</p>
                <p className="font-medium">{cattle.createdAt ? format(new Date(cattle.createdAt), 'PPP') : 'N/A'}</p>
              </div>
            </div>
            
            {cattle.notes && (
              <div className="mt-4">
                <p className="text-gray-600">Notes</p>
                <p className="mt-1 font-medium">{cattle.notes}</p>
              </div>
            )}
          </div>

          {/* Induction Records */}
          <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-semibold text-gray-800">Induction Records</h2>
              <button
                onClick={() => setShowInductionForm(!showInductionForm)}
                className="flex items-center px-3 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200 text-sm"
              >
                <Syringe className="w-4 h-4 mr-1" />
                {showInductionForm ? 'Cancel' : 'Add Induction'}
              </button>
            </div>
            
            {showInductionForm && (
              <div className="mb-6">
                <CattleInductionForm 
                  cattleId={cattle.id} 
                  onInductionComplete={() => {
                    // Refresh cattle data after induction
                    const fetchCattle = async () => {
                      if (typeof id === 'string') {
                        try {
                          const cattleData = await getCattleById(id);
                          setCattle(cattleData);
                        } catch (error) {
                          console.error('Error fetching cattle:', error);
                        }
                      }
                    };
                    fetchCattle();
                    setShowInductionForm(false);
                  }}
                />
              </div>
            )}
            
            <div className="space-y-3">
              {/* In a real app, we would fetch and display induction records here */}
              <p className="text-gray-600 text-center py-4">No induction records yet</p>
            </div>
          </div>

          {/* Weight Records */}
          <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-semibold text-gray-800">Weight Records</h2>
              <button
                onClick={() => setShowReweightForm(!showReweightForm)}
                className="flex items-center px-3 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200 text-sm"
              >
                <Scale className="w-4 h-4 mr-1" />
                {showReweightForm ? 'Cancel' : 'Add Weight'}
              </button>
            </div>
            
            {showReweightForm && (
              <div className="mb-6">
                <CattleReweightForm 
                  cattleId={cattle.id} 
                  currentWeight={Number(cattle.currentWeight)}
                  onReweightComplete={() => {
                    // Refresh cattle data after reweight
                    const fetchCattle = async () => {
                      if (typeof id === 'string') {
                        try {
                          const cattleData = await getCattleById(id);
                          setCattle(cattleData);
                        } catch (error) {
                          console.error('Error fetching cattle:', error);
                        }
                      }
                    };
                    fetchCattle();
                    setShowReweightForm(false);
                  }}
                />
              </div>
            )}
            
            <div className="space-y-3">
              {/* In a real app, we would fetch and display weight records here */}
              <p className="text-gray-600 text-center py-4">No weight records yet</p>
            </div>
          </div>
        </div>

        {/* Sidebar with quick actions */}
        <div className="space-y-6">
          <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
            <h2 className="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
            <div className="space-y-3">
              <button className="w-full flex items-center justify-center px-4 py-2 bg-primary-100 text-primary-700 rounded-lg hover:bg-primary-200 transition duration-200">
                <DollarSign className="w-5 h-5 mr-2" />
                Record Sale
              </button>
              <button className="w-full flex items-center justify-center px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition duration-200">
                <Syringe className="w-5 h-5 mr-2" />
                Add Health Note
              </button>
              <button className="w-full flex items-center justify-center px-4 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition duration-200">
                <MapPin className="w-5 h-5 mr-2" />
                Update Location
              </button>
            </div>
          </div>

          <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
            <h2 className="text-lg font-semibold text-gray-800 mb-4">History</h2>
            <div className="space-y-3">
              {cattle.createdAt && (
                <div className="flex items-center text-sm">
                  <Calendar className="w-5 h-5 mr-2 text-gray-500" />
                  <span>Added: {format(new Date(cattle.createdAt), 'PPP')}</span>
                </div>
              )}
              {cattle.updatedAt && (
                <div className="flex items-center text-sm">
                  <Calendar className="w-5 h-5 mr-2 text-gray-500" />
                  <span>Last updated: {format(new Date(cattle.updatedAt), 'PPP')}</span>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}