'use client';

import { useState } from 'react';
import { createCattle, createPurchase } from '@/services/cattleService';
import { PlusCircle, Upload } from 'lucide-react';

interface CattlePurchaseFormProps {
  onPurchaseComplete?: () => void;
}

export default function CattlePurchaseForm({ onPurchaseComplete }: CattlePurchaseFormProps) {
  const [formData, setFormData] = useState({
    cattleId: '',
    breed: '',
    gender: 'MALE',
    initialWeight: '',
    purchasePrice: '',
    supplierId: '',
    purchaseDate: new Date().toISOString().split('T')[0],
    notes: '',
    photoFile: null as File | null,
    documentFile: null as File | null,
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, type: 'photo' | 'document') => {
    if (e.target.files && e.target.files[0]) {
      if (type === 'photo') {
        setFormData(prev => ({ ...prev, photoFile: e.target.files![0] }));
      } else {
        setFormData(prev => ({ ...prev, documentFile: e.target.files![0] }));
      }
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      // Create cattle record
      const cattle = await createCattle({
        cattleId: formData.cattleId,
        breed: formData.breed,
        gender: formData.gender,
        initialWeight: parseFloat(formData.initialWeight),
        notes: formData.notes,
        // photoUrl and documentUrl would be handled by file upload service in real app
      });

      // Create purchase record
      await createPurchase({
        cattleId: cattle.id,
        supplierId: formData.supplierId,
        purchaseDate: new Date(formData.purchaseDate),
        initialWeight: parseFloat(formData.initialWeight),
        purchasePrice: parseFloat(formData.purchasePrice),
        notes: formData.notes,
      });

      // Reset form
      setFormData({
        cattleId: '',
        breed: '',
        gender: 'MALE',
        initialWeight: '',
        purchasePrice: '',
        supplierId: '',
        purchaseDate: new Date().toISOString().split('T')[0],
        notes: '',
        photoFile: null,
        documentFile: null,
      });

      if (onPurchaseComplete) onPurchaseComplete();
    } catch (err) {
      console.error('Error creating purchase:', err);
      setError('Failed to create cattle purchase. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-xl font-bold text-gray-800 mb-4">Add New Cattle Purchase</h2>
      
      {error && (
        <div className="bg-red-50 text-red-700 p-3 rounded-md mb-4">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label htmlFor="cattleId" className="block text-sm font-medium text-gray-700 mb-1">
              Cattle ID
            </label>
            <input
              id="cattleId"
              name="cattleId"
              type="text"
              value={formData.cattleId}
              onChange={handleChange}
              required
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="e.g., C-2023-001"
            />
          </div>

          <div>
            <label htmlFor="breed" className="block text-sm font-medium text-gray-700 mb-1">
              Breed
            </label>
            <input
              id="breed"
              name="breed"
              type="text"
              value={formData.breed}
              onChange={handleChange}
              required
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="e.g., Angus, Hereford"
            />
          </div>

          <div>
            <label htmlFor="gender" className="block text-sm font-medium text-gray-700 mb-1">
              Gender
            </label>
            <select
              id="gender"
              name="gender"
              value={formData.gender}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="MALE">Male</option>
              <option value="FEMALE">Female</option>
            </select>
          </div>

          <div>
            <label htmlFor="initialWeight" className="block text-sm font-medium text-gray-700 mb-1">
              Initial Weight (kg)
            </label>
            <input
              id="initialWeight"
              name="initialWeight"
              type="number"
              value={formData.initialWeight}
              onChange={handleChange}
              required
              step="0.01"
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Weight in kg"
            />
          </div>

          <div>
            <label htmlFor="purchasePrice" className="block text-sm font-medium text-gray-700 mb-1">
              Purchase Price
            </label>
            <input
              id="purchasePrice"
              name="purchasePrice"
              type="number"
              value={formData.purchasePrice}
              onChange={handleChange}
              required
              step="0.01"
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Price in currency"
            />
          </div>

          <div>
            <label htmlFor="supplierId" className="block text-sm font-medium text-gray-700 mb-1">
              Supplier ID
            </label>
            <input
              id="supplierId"
              name="supplierId"
              type="text"
              value={formData.supplierId}
              onChange={handleChange}
              required
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Supplier ID"
            />
          </div>

          <div>
            <label htmlFor="purchaseDate" className="block text-sm font-medium text-gray-700 mb-1">
              Purchase Date
            </label>
            <input
              id="purchaseDate"
              name="purchaseDate"
              type="date"
              value={formData.purchaseDate}
              onChange={handleChange}
              required
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            />
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
            placeholder="Additional information about the cattle"
          />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Photo Upload
            </label>
            <div className="flex items-center">
              <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                <div className="flex flex-col items-center justify-center pt-5 pb-6">
                  <Upload className="w-8 h-8 text-gray-400" />
                  <p className="text-sm text-gray-500 mt-2">Click to upload photo</p>
                </div>
                <input 
                  id="photoFile" 
                  name="photoFile" 
                  type="file" 
                  className="hidden" 
                  accept="image/*"
                  onChange={(e) => handleFileChange(e, 'photo')}
                />
              </label>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Document Upload
            </label>
            <div className="flex items-center">
              <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                <div className="flex flex-col items-center justify-center pt-5 pb-6">
                  <Upload className="w-8 h-8 text-gray-400" />
                  <p className="text-sm text-gray-500 mt-2">Click to upload document</p>
                </div>
                <input 
                  id="documentFile" 
                  name="documentFile" 
                  type="file" 
                  className="hidden" 
                  accept=".pdf,.doc,.docx"
                  onChange={(e) => handleFileChange(e, 'document')}
                />
              </label>
            </div>
          </div>
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
                Add Cattle Purchase
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}