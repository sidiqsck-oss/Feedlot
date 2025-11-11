'use client';

import { useState } from 'react';
import { createSale } from '@/services\salesService';
import { DollarSign, Scale, Calendar, User, Cattle } from 'lucide-react';

interface SaleFormProps {
  cattleId: string;
  userId: string;
  onSaleCreated?: () => void;
}

export default function SaleForm({ cattleId, userId, onSaleCreated }: SaleFormProps) {
  const [formData, setFormData] = useState({
    finalWeight: '',
    salePrice: '',
    buyerName: '',
    buyerContact: '',
    saleDate: new Date().toISOString().split('T')[0],
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      await createSale({
        cattleId,
        userId,
        finalWeight: parseFloat(formData.finalWeight),
        salePrice: parseFloat(formData.salePrice),
        buyerName: formData.buyerName,
        buyerContact: formData.buyerContact,
        saleDate: new Date(formData.saleDate),
        notes: formData.notes,
      });

      // Reset form
      setFormData({
        finalWeight: '',
        salePrice: '',
        buyerName: '',
        buyerContact: '',
        saleDate: new Date().toISOString().split('T')[0],
        notes: '',
      });

      if (onSaleCreated) onSaleCreated();
    } catch (err) {
      console.error('Error creating sale:', err);
      setError('Failed to create sale. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-xl font-bold text-gray-800 mb-4">Record Cattle Sale</h2>
      
      {error && (
        <div className="bg-red-50 text-red-700 p-3 rounded-md mb-4">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label htmlFor="finalWeight" className="block text-sm font-medium text-gray-700 mb-1">
              Final Weight (kg)
            </label>
            <div className="relative">
              <input
                id="finalWeight"
                name="finalWeight"
                type="number"
                value={formData.finalWeight}
                onChange={handleChange}
                required
                step="0.01"
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                placeholder="Final weight at sale"
              />
              <Scale className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="salePrice" className="block text-sm font-medium text-gray-700 mb-1">
              Sale Price
            </label>
            <div className="relative">
              <input
                id="salePrice"
                name="salePrice"
                type="number"
                value={formData.salePrice}
                onChange={handleChange}
                required
                step="0.01"
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                placeholder="Total sale price"
              />
              <DollarSign className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="buyerName" className="block text-sm font-medium text-gray-700 mb-1">
              Buyer Name
            </label>
            <div className="relative">
              <input
                id="buyerName"
                name="buyerName"
                type="text"
                value={formData.buyerName}
                onChange={handleChange}
                required
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                placeholder="Buyer's name"
              />
              <User className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="buyerContact" className="block text-sm font-medium text-gray-700 mb-1">
              Buyer Contact
            </label>
            <input
              id="buyerContact"
              name="buyerContact"
              type="text"
              value={formData.buyerContact}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Phone, email, etc."
            />
          </div>

          <div>
            <label htmlFor="saleDate" className="block text-sm font-medium text-gray-700 mb-1">
              Sale Date
            </label>
            <div className="relative">
              <input
                id="saleDate"
                name="saleDate"
                type="date"
                value={formData.saleDate}
                onChange={handleChange}
                required
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              />
              <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
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
            placeholder="Additional information about the sale"
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
                <DollarSign className="w-5 h-5 mr-2" />
                Record Sale
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}