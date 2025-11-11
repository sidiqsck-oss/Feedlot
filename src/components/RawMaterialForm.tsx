'use client';

import { useState } from 'react';
import { createRawMaterial } from '@/services/feedService';
import { PackagePlus, Package, Scale, AlertTriangle } from 'lucide-react';

interface RawMaterialFormProps {
  onMaterialCreated?: () => void;
}

export default function RawMaterialForm({ onMaterialCreated }: RawMaterialFormProps) {
  const [formData, setFormData] = useState({
    name: '',
    category: 'grain',
    unit: 'kg',
    currentStock: '0',
    minStock: '10',
    pricePerUnit: '0',
    supplierId: '',
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
      await createRawMaterial({
        name: formData.name,
        category: formData.category,
        unit: formData.unit,
        currentStock: parseFloat(formData.currentStock),
        minStock: parseFloat(formData.minStock),
        pricePerUnit: parseFloat(formData.pricePerUnit),
        supplierId: formData.supplierId || undefined,
        notes: formData.notes,
      });

      // Reset form
      setFormData({
        name: '',
        category: 'grain',
        unit: 'kg',
        currentStock: '0',
        minStock: '10',
        pricePerUnit: '0',
        supplierId: '',
        notes: '',
      });

      if (onMaterialCreated) onMaterialCreated();
    } catch (err) {
      console.error('Error creating raw material:', err);
      setError('Failed to create raw material. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-xl font-bold text-gray-800 mb-4">Add Raw Material</h2>
      
      {error && (
        <div className="bg-red-50 text-red-700 p-3 rounded-md mb-4">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
              Material Name
            </label>
            <input
              id="name"
              name="name"
              type="text"
              value={formData.name}
              onChange={handleChange}
              required
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="e.g., Corn, Soybean Meal"
            />
          </div>

          <div>
            <label htmlFor="category" className="block text-sm font-medium text-gray-700 mb-1">
              Category
            </label>
            <select
              id="category"
              name="category"
              value={formData.category}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="grain">Grain</option>
              <option value="hay">Hay/Forage</option>
              <option value="supplement">Supplement</option>
              <option value="mineral">Mineral/Vitamin</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div>
            <label htmlFor="unit" className="block text-sm font-medium text-gray-700 mb-1">
              Unit
            </label>
            <select
              id="unit"
              name="unit"
              value={formData.unit}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="kg">Kilogram (kg)</option>
              <option value="ton">Ton</option>
              <option value="bag">Bag</option>
              <option value="liter">Liter</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div>
            <label htmlFor="currentStock" className="block text-sm font-medium text-gray-700 mb-1">
              Current Stock
            </label>
            <div className="relative">
              <input
                id="currentStock"
                name="currentStock"
                type="number"
                value={formData.currentStock}
                onChange={handleChange}
                required
                step="0.01"
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                placeholder="0.00"
              />
              <Package className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="minStock" className="block text-sm font-medium text-gray-700 mb-1">
              Minimum Stock
            </label>
            <div className="relative">
              <input
                id="minStock"
                name="minStock"
                type="number"
                value={formData.minStock}
                onChange={handleChange}
                required
                step="0.01"
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                placeholder="0.00"
              />
              <AlertTriangle className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="pricePerUnit" className="block text-sm font-medium text-gray-700 mb-1">
              Price per Unit
            </label>
            <input
              id="pricePerUnit"
              name="pricePerUnit"
              type="number"
              value={formData.pricePerUnit}
              onChange={handleChange}
              required
              step="0.01"
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="0.00"
            />
          </div>

          <div>
            <label htmlFor="supplierId" className="block text-sm font-medium text-gray-700 mb-1">
              Supplier ID (Optional)
            </label>
            <input
              id="supplierId"
              name="supplierId"
              type="text"
              value={formData.supplierId}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Supplier ID"
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
            placeholder="Additional information about the raw material"
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
                <PackagePlus className="w-5 h-5 mr-2" />
                Add Material
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}