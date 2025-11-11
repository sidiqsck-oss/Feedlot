'use client';

import { useState, useEffect } from 'react';
import { useParams } from 'next/navigation';
import { getCattleById } from '@/services/cattleService';
import { Cattle } from '@prisma/client';
import CattleReweightForm from '@/components\CattleReweightForm';
import { Scale, TrendingUp, Calendar, BarChart3 } from 'lucide-react';
import { format } from 'date-fns';

export default function CattleReweightPage() {
  const { id } = useParams();
  const [cattle, setCattle] = useState<Cattle | null>(null);
  const [loading, setLoading] = useState(true);

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
          <Scale className="w-16 h-16 mx-auto text-gray-400" />
          <h3 className="mt-4 text-lg font-medium text-gray-900">Cattle not found</h3>
          <p className="mt-2 text-gray-600">The requested cattle could not be found.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex items-center mb-6">
        <Scale className="w-8 h-8 mr-3 text-primary-600" />
        <h1 className="text-3xl font-bold text-gray-900">Reweight Cattle: {cattle.cattleId}</h1>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Cattle Information */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
            <h2 className="text-xl font-semibold text-gray-800 mb-4">Cattle Information</h2>
            <div className="space-y-3">
              <div>
                <p className="text-gray-600 text-sm">Cattle ID</p>
                <p className="font-medium">{cattle.cattleId}</p>
              </div>
              <div>
                <p className="text-gray-600 text-sm">Breed</p>
                <p className="font-medium">{cattle.breed}</p>
              </div>
              <div>
                <p className="text-gray-600 text-sm">Initial Weight</p>
                <p className="font-medium">{cattle.initialWeight?.toString()} kg</p>
              </div>
              <div>
                <p className="text-gray-600 text-sm">Current Weight</p>
                <p className="font-medium">{cattle.currentWeight?.toString()} kg</p>
              </div>
              <div>
                <p className="text-gray-600 text-sm">Status</p>
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
            </div>
          </div>

          {/* Growth Summary */}
          <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200 mt-6">
            <h2 className="text-xl font-semibold text-gray-800 mb-4">Growth Summary</h2>
            <div className="space-y-3">
              <div className="flex justify-between">
                <span className="text-gray-600">Total Weight Gain</span>
                <span className="font-medium">
                  {cattle.currentWeight && cattle.initialWeight 
                    ? (Number(cattle.currentWeight) - Number(cattle.initialWeight)).toFixed(2) 
                    : '0.00'} kg
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">ADG Est.</span>
                <span className="font-medium">0.8 kg/day</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">Days in Feedlot</span>
                <span className="font-medium">45</span>
              </div>
            </div>
          </div>
        </div>

        {/* Reweight Form */}
        <div className="lg:col-span-2">
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
            }}
          />

          {/* Weight History */}
          <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200 mt-6">
            <div className="flex items-center mb-4">
              <BarChart3 className="w-5 h-5 mr-2 text-primary-600" />
              <h2 className="text-xl font-semibold text-gray-800">Weight History</h2>
            </div>
            <div className="space-y-3">
              {/* In a real app, we would fetch and display weight records here */}
              <p className="text-gray-600 text-center py-4">No weight history yet</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}