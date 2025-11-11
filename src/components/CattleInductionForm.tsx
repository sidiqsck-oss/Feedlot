'use client';

import { useState } from 'react';
import { createInduction } from '@/services/cattleService';
import { PlusCircle, Calendar, Syringe } from 'lucide-react';

interface CattleInductionFormProps {
  cattleId: string;
  onInductionComplete?: () => void;
}

export default function CattleInductionForm({ cattleId, onInductionComplete }: CattleInductionFormProps) {
  const [formData, setFormData] = useState({
    inductionDate: new Date().toISOString().split('T')[0],
    quarantineDays: '',
    treatment: '',
    status: 'quarantine',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      await createInduction({
        cattleId,
        inductionDate: new Date(formData.inductionDate),
        quarantineDays: formData.quarantineDays ? parseInt(formData.quarantineDays) : undefined,
        treatment: formData.treatment,
        status: formData.status,
        notes: formData.notes,
      });

      // Reset form
      setFormData({
        inductionDate: new Date().toISOString().split('T')[0],
        quarantineDays: '',
        treatment: '',
        status: 'quarantine',
        notes: '',
      });

      if (onInductionComplete) onInductionComplete();
    } catch (err) {
      console.error('Error creating induction:', err);
      setError('Failed to create cattle induction. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-xl font-bold text-gray-800 mb-4">Cattle Induction Record</h2>
      
      {error && (
        <div className="bg-red-50 text-red-700 p-3 rounded-md mb-4">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label htmlFor="inductionDate" className="block text-sm font-medium text-gray-700 mb-1">
              Induction Date
            </label>
            <div className="relative">
              <input
                id="inductionDate"
                name="inductionDate"
                type="date"
                value={formData.inductionDate}
                onChange={handleChange}
                required
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              />
              <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="quarantineDays" className="block text-sm font-medium text-gray-700 mb-1">
              Quarantine Days
            </label>
            <input
              id="quarantineDays"
              name="quarantineDays"
              type="number"
              value={formData.quarantineDays}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Number of days in quarantine"
            />
          </div>

          <div>
            <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-1">
              Status
            </label>
            <select
              id="status"
              name="status"
              value={formData.status}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="quarantine">Quarantine</option>
              <option value="healthy">Healthy</option>
              <option value="treatment">Treatment</option>
              <option value="recovered">Recovered</option>
            </select>
          </div>

          <div>
            <label htmlFor="treatment" className="block text-sm font-medium text-gray-700 mb-1">
              Treatment
            </label>
            <div className="relative">
              <input
                id="treatment"
                name="treatment"
                type="text"
                value={formData.treatment}
                onChange={handleChange}
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                placeholder="Vaccination, medication, etc."
              />
              <Syringe className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>
        </div>

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
            placeholder="Additional information about the induction process"
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
              <span>Processing...</span>
            ) : (
              <>
                <PlusCircle className="w-5 h-5 mr-2" />
                Record Induction
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}