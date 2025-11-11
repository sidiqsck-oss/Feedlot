'use client';

import { useState, useEffect } from 'react';
import { getRawMaterials } from '@/services/feedService';
import { RawMaterial } from '@prisma/client';
import RawMaterialForm from '@/components\RawMaterialForm';
import FeedUsageForm from '@/components\FeedUsageForm';
import ExportButton from '@/components\ExportButton';
import { Package, PackagePlus, Scale, AlertTriangle, Search } from 'lucide-react';
import { useSession } from 'next-auth/react';

export default function FeedPage() {
  const [rawMaterials, setRawMaterials] = useState<RawMaterial[]>([]);
  const [loading, setLoading] = useState(true);
  const [showMaterialForm, setShowMaterialForm] = useState(false);
  const [showUsageForm, setShowUsageForm] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const { data: session } = useSession();
  const userId = session?.user?.id || '';

  useEffect(() => {
    const fetchRawMaterials = async () => {
      try {
        const materials = await getRawMaterials();
        setRawMaterials(materials);
      } catch (error) {
        console.error('Error fetching raw materials:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchRawMaterials();
  }, []);

  const handleMaterialCreated = () => {
    // Refresh raw materials list after creation
    const fetchRawMaterials = async () => {
      try {
        const materials = await getRawMaterials();
        setRawMaterials(materials);
      } catch (error) {
        console.error('Error fetching raw materials:', error);
      }
    };

    fetchRawMaterials();
    setShowMaterialForm(false);
  };

  const handleUsageRecorded = () => {
    // Refresh raw materials list after usage
    const fetchRawMaterials = async () => {
      try {
        const materials = await getRawMaterials();
        setRawMaterials(materials);
      } catch (error) {
        console.error('Error fetching raw materials:', error);
      }
    };

    fetchRawMaterials();
    setShowUsageForm(false);
  };

  // Filter raw materials based on search term
  const filteredMaterials = rawMaterials.filter(material => 
    material.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    material.category.toLowerCase().includes(searchTerm.toLowerCase()) ||
    material.unit.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center">
            <Package className="w-8 h-8 mr-3 text-primary-600" />
            Feed Management
          </h1>
          <p className="text-gray-600 mt-2">Manage feed inventory and track usage</p>
        </div>
        <div className="mt-4 md:mt-0 flex space-x-3">
          <ExportButton reportType="feed" />
          <button
            onClick={() => setShowUsageForm(true)}
            className="flex items-center px-4 py-2 bg-secondary-600 text-white rounded-lg hover:bg-secondary-700 transition duration-200"
          >
            <Scale className="w-5 h-5 mr-2" />
            Record Usage
          </button>
          <button
            onClick={() => setShowMaterialForm(true)}
            className="flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
          >
            <PackagePlus className="w-5 h-5 mr-2" />
            Add Material
          </button>
        </div>
      </div>

      {showMaterialForm && (
        <div className="mb-8">
          <RawMaterialForm onMaterialCreated={handleMaterialCreated} />
        </div>
      )}

      {showUsageForm && userId && (
        <div className="mb-8">
          <FeedUsageForm userId={userId} onUsageRecorded={handleUsageRecorded} />
        </div>
      )}

      {/* Search Bar */}
      <div className="mb-6">
        <div className="relative">
          <input
            type="text"
            placeholder="Search materials by name, category, or unit..."
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
          <p className="mt-4 text-gray-600">Loading raw materials...</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredMaterials.map((material) => {
            const isLowStock = material.currentStock.lt(material.minStock);
            return (
              <div 
                key={material.id} 
                className={`bg-white rounded-xl shadow-md overflow-hidden border ${
                  isLowStock ? 'border-red-300' : 'border-gray-200'
                }`}
              >
                <div className="p-6">
                  <div className="flex justify-between items-start">
                    <div>
                      <h3 className="text-lg font-semibold text-gray-900">{material.name}</h3>
                      <p className="text-gray-600">{material.category} • {material.unit}</p>
                    </div>
                    {isLowStock && (
                      <span className="flex items-center px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                        <AlertTriangle className="w-3 h-3 mr-1" />
                        Low Stock
                      </span>
                    )}
                  </div>
                  
                  <div className="mt-4 space-y-2">
                    <div className="flex justify-between">
                      <span className="text-gray-600">Current Stock:</span>
                      <span className={`font-medium ${isLowStock ? 'text-red-600' : 'text-gray-900'}`}>
                        {material.currentStock.toString()} {material.unit}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600">Min Stock:</span>
                      <span className="font-medium">{material.minStock.toString()} {material.unit}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600">Price/Unit:</span>
                      <span className="font-medium">{material.pricePerUnit.toString()}</span>
                    </div>
                    {material.supplier && (
                      <div className="flex justify-between">
                        <span className="text-gray-600">Supplier:</span>
                        <span className="font-medium text-sm truncate max-w-[120px]">{material.supplier.name}</span>
                      </div>
                    )}
                  </div>
                  
                  <div className="mt-4 pt-4 border-t border-gray-200 flex space-x-2">
                    <button className="flex-1 text-xs px-3 py-2 bg-primary-100 text-primary-700 rounded-lg hover:bg-primary-200 transition duration-200">
                      Update Stock
                    </button>
                    <button className="flex-1 text-xs px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-200">
                      View Details
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {filteredMaterials.length === 0 && !loading && (
        <div className="text-center py-10">
          <Package className="w-16 h-16 mx-auto text-gray-400" />
          <h3 className="mt-4 text-lg font-medium text-gray-900">No raw materials found</h3>
          <p className="mt-2 text-gray-600">
            {searchTerm ? 'No materials match your search criteria.' : 'Get started by adding your first raw material.'}
          </p>
          {!searchTerm && (
            <button
              onClick={() => setShowMaterialForm(true)}
              className="mt-4 inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
            >
              <PackagePlus className="w-5 h-5 mr-2" />
              Add Material
            </button>
          )}
        </div>
      )}
    </div>
  );
}