'use client';

import { useState, useEffect } from 'react';
import { createWeightRecord, calculateADG } from '@/services/cattleService';
import { Scale, TrendingUp, Calendar } from 'lucide-react';

interface CattleReweightFormProps {
  cattleId: string;
  currentWeight?: number;
  onReweightComplete?: () => void;
}

export default function CattleReweightForm({ cattleId, currentWeight, onReweightComplete }: CattleReweightFormProps) {
  const [formData, setFormData] = useState({
    weight: '',
    recordDate: new Date().toISOString().split('T')[0],
    notes: '',
  });
  const [adg, setAdg] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Calculate ADG when weight or date changes
  useEffect(() => {
    const calculate = async () => {
      if (formData.weight && formData.recordDate) {
        const weight = parseFloat(formData.weight);
        const recordDate = new Date(formData.recordDate);
        
        try {
          const calculatedADG = await calculateADG(cattleId);
          if (calculatedADG !== null) {
            setAdg(calculatedADG);
          }
        } catch (err) {
          console.error('Error calculating ADG:', err);
          setAdg(null);
        }
      }
    };

    calculate();
  }, [formData.weight, formData.recordDate, cattleId]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      await createWeightRecord({
        cattleId,
        weight: parseFloat(formData.weight),
        recordDate: new Date(formData.recordDate),
        notes: formData.notes,
      });

      // Reset form
      setFormData({
        weight: '',
        recordDate: new Date().toISOString().split('T')[0],
        notes: '',
      });
      setAdg(null);

      if (onReweightComplete) onReweightComplete();
    } catch (err) {
      console.error('Error recording weight:', err);
      setError('Failed to record weight. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  // Calculate weight gain from initial
  const weightGain = currentWeight && formData.weight 
    ? (parseFloat(formData.weight) - currentWeight).toFixed(2)
    : null;

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-xl font-bold text-gray-800 mb-4">Cattle Reweight Record</h2>
      
      {error && (
        <div className="bg-red-50 text-red-700 p-3 rounded-md mb-4">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label htmlFor="weight" className="block text-sm font-medium text-gray-700 mb-1">
              Current Weight (kg)
            </label>
            <div className="relative">
              <input
                id="weight"
                name="weight"
                type="number"
                value={formData.weight}
                onChange={handleChange}
                required
                step="0.01"
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                placeholder="Weight in kg"
              />
              <Scale className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="recordDate" className="block text-sm font-medium text-gray-700 mb-1">
              Record Date
            </label>
            <div className="relative">
              <input
                id="recordDate"
                name="recordDate"
                type="date"
                value={formData.recordDate}
                onChange={handleChange}
                required
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              />
              <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>
        </div>

        {weightGain !== null && (
          <div className="bg-blue-50 p-3 rounded-md">
            <p className="text-sm text-blue-800">
              Weight gain: <span className="font-semibold">{weightGain} kg</span> from initial weight
            </p>
          </div>
        )}

        {adg !== null && (
          <div className="bg-green-50 p-3 rounded-md">
            <p className="text-sm text-green-800 flex items-center">
              <TrendingUp className="w-4 h-4 mr-1" />
              Average Daily Gain: <span className="font-semibold">{adg.toFixed(2)} kg/day</span>
            </p>
          </div>
        )}

        <div>
          <label htmlFor="notes" className="block text-sm font-medium text-gray-700 mb-1">
            Notes
          </label>
          <textarea
            id="notes"
            name="notes"
            value={formData.notes}
            onChange={handleChange}
            rows={3}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            placeholder="Additional observations about the cattle"
          />
        </div>

        <div className="flex justify-end">
          <button
            type="submit"
            disabled={loading}
            className={`flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200 ${
              loading ? 'opacity-70 cursor-not-allowed' : ''
            }`}
          >
            {loading ? (
              <span>Recording...</span>
            ) : (
              <>
                <Scale className="w-5 h-5 mr-2" />
                Record Weight
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}