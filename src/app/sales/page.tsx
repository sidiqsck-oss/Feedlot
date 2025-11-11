'use client';

import { useState, useEffect } from 'react';
import { getSales } from '@/services\salesService';
import { Sale } from '@prisma/client';
import SaleForm from '@/components\SaleForm';
import ExportButton from '@/components\ExportButton';
import { DollarSign, Calendar, User, Cattle, Search, PlusCircle } from 'lucide-react';
import { useSession } from 'next-auth/react';
import { format } from 'date-fns';

export default function SalesPage() {
  const [sales, setSales] = useState<Sale[]>([]);
  const [loading, setLoading] = useState(true);
  const [showSaleForm, setShowSaleForm] = useState(false);
  const [selectedCattleId, setSelectedCattleId] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const { data: session } = useSession();
  const userId = session?.user?.id || '';

  useEffect(() => {
    const fetchSales = async () => {
      try {
        const salesData = await getSales();
        setSales(salesData);
      } catch (error) {
        console.error('Error fetching sales:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchSales();
  }, []);

  const handleSaleCreated = () => {
    // Refresh sales list after creation
    const fetchSales = async () => {
      try {
        const salesData = await getSales();
        setSales(salesData);
      } catch (error) {
        console.error('Error fetching sales:', error);
      }
    };

    fetchSales();
    setShowSaleForm(false);
  };

  // Filter sales based on search term
  const filteredSales = sales.filter(sale => 
    sale.cattle.cattleId.toLowerCase().includes(searchTerm.toLowerCase()) ||
    sale.buyerName.toLowerCase().includes(searchTerm.toLowerCase()) ||
    sale.cattle.breed.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center">
            <DollarSign className="w-8 h-8 mr-3 text-primary-600" />
            Sales Management
          </h1>
          <p className="text-gray-600 mt-2">Track and manage cattle sales</p>
        </div>
        <div className="mt-4 md:mt-0 flex space-x-3">
          <ExportButton reportType="sales" />
          <button
            onClick={() => setShowSaleForm(true)}
            className="flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
          >
            <PlusCircle className="w-5 h-5 mr-2" />
            Record Sale
          </button>
        </div>
      </div>

      {showSaleForm && userId && (
        <div className="mb-8">
          <SaleForm 
            cattleId={selectedCattleId || 'temp'} 
            userId={userId} 
            onSaleCreated={handleSaleCreated} 
          />
        </div>
      )}

      {/* Search Bar */}
      <div className="mb-6">
        <div className="relative">
          <input
            type="text"
            placeholder="Search sales by cattle ID, buyer name, or breed..."
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
          <p className="mt-4 text-gray-600">Loading sales records...</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full bg-white rounded-lg shadow-md overflow-hidden">
            <thead className="bg-gray-50">
              <tr>
                <th className="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cattle ID</th>
                <th className="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Breed</th>
                <th className="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                <th className="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                <th className="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buyer</th>
                <th className="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th className="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {filteredSales.map((sale) => (
                <tr key={sale.id} className="hover:bg-gray-50">
                  <td className="py-4 px-4 whitespace-nowrap">
                    <div className="flex items-center">
                      <Cattle className="w-5 h-5 mr-2 text-primary-600" />
                      <span className="font-medium">{sale.cattle.cattleId}</span>
                    </div>
                  </td>
                  <td className="py-4 px-4 whitespace-nowrap text-sm text-gray-900">{sale.cattle.breed}</td>
                  <td className="py-4 px-4 whitespace-nowrap text-sm text-gray-900">{sale.finalWeight.toString()} kg</td>
                  <td className="py-4 px-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <div className="flex items-center">
                      <DollarSign className="w-4 h-4 mr-1 text-green-600" />
                      {sale.salePrice.toString()}
                    </div>
                  </td>
                  <td className="py-4 px-4 whitespace-nowrap text-sm text-gray-900">
                    <div className="flex items-center">
                      <User className="w-4 h-4 mr-2 text-gray-500" />
                      {sale.buyerName}
                    </div>
                  </td>
                  <td className="py-4 px-4 whitespace-nowrap text-sm text-gray-500">
                    <div className="flex items-center">
                      <Calendar className="w-4 h-4 mr-2 text-gray-500" />
                      {format(new Date(sale.saleDate), 'PP')}
                    </div>
                  </td>
                  <td className="py-4 px-4 whitespace-nowrap text-sm">
                    <button className="text-primary-600 hover:text-primary-900">View</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {filteredSales.length === 0 && !loading && (
        <div className="text-center py-10">
          <DollarSign className="w-16 h-16 mx-auto text-gray-400" />
          <h3 className="mt-4 text-lg font-medium text-gray-900">No sales records found</h3>
          <p className="mt-2 text-gray-600">
            {searchTerm ? 'No sales match your search criteria.' : 'Get started by recording your first sale.'}
          </p>
          {!searchTerm && (
            <button
              onClick={() => setShowSaleForm(true)}
              className="mt-4 inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200"
            >
              <PlusCircle className="w-5 h-5 mr-2" />
              Record Sale
            </button>
          )}
        </div>
      )}
    </div>
  );
}