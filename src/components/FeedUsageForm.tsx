'use client';

import { useState } from 'react';
import { recordFeedUsage } from '@/services/feedService';
import { Scale, Calendar, User, Package } from 'lucide-react';

interface FeedUsageFormProps {
  userId: string;
  cattleGroupId?: string;
  onUsageRecorded?: () => void;
}

export default function FeedUsageForm({ userId, cattleGroupId, onUsageRecorded }: FeedUsageFormProps) {
  const [formData, setFormData] = useState({
    rawMaterialId: '',
    rationId: '',
    usageDate: new Date().toISOString().split('T')[0],
    quantity: '',
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
      await recordFeedUsage({
        rawMaterialId: formData.rawMaterialId,
        rationId: formData.rationId || undefined,
        cattleGroupId: cattleGroupId || undefined,
        userId,
        usageDate: new Date(formData.usageDate),
        quantity: parseFloat(formData.quantity),
        notes: formData.notes,
      });

      // Reset form
      setFormData({
        rawMaterialId: '',
        rationId: '',
        usageDate: new Date().toISOString().split('T')[0],
        quantity: '',
        notes: '',
      });

      if (onUsageRecorded) onUsageRecorded();
    } catch (err) {
      console.error('Error recording feed usage:', err);
      setError('Failed to record feed usage. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-xl font-bold text-gray-800 mb-4">Record Feed Usage</h2>
      
      {error && (
        <div className="bg-red-50 text-red-700 p-3 rounded-md mb-4">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label htmlFor="rawMaterialId" className="block text-sm font-medium text-gray-700 mb-1">
              Raw Material
            </label>
            <select
              id="rawMaterialId"
              name="rawMaterialId"
              value={formData.rawMaterialId}
              onChange={handleChange}
              required
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="">Select raw material</option>
              {/* In a real app, this would be populated with available raw materials */}
              <option value="1">Corn</option>
              <option value="2">Soybean Meal</option>
              <option value="3">Wheat Bran</option>
            </select>
          </div>

          <div>
            <label htmlFor="rationId" className="block text-sm font-medium text-gray-700 mb-1">
              Ration Formula (Optional)
            </label>
            <select
              id="rationId"
              name="rationId"
              value={formData.rationId}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="">Select ration formula</option>
              {/* In a real app, this would be populated with available rations */}
              <option value="1">Growing Phase Ration</option>
              <option value="2">Finishing Phase Ration</option>
            </select>
          </div>

          <div>
            <label htmlFor="usageDate" className="block text-sm font-medium text-gray-700 mb-1">
              Date
            </label>
            <div className="relative">
              <input
                id="usageDate"
                name="usageDate"
                type="date"
                value={formData.usageDate}
                onChange={handleChange}
                required
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              />
              <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="quantity" className="block text-sm font-medium text-gray-700 mb-1">
              Quantity (kg)
            </label>
            <div className="relative">
              <input
                id="quantity"
                name="quantity"
                type="number"
                value={formData.quantity}
                onChange={handleChange}
                required
                step="0.01"
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                placeholder="Amount used"
              />
              <Scale className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
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
            placeholder="Additional information about the feed usage"
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
                Record Usage
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}