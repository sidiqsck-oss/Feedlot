'use client';

import { useState, useEffect } from 'react';
import { 
  getCattlePopulationSummary, 
  getGrowthMetrics, 
  getHealthMetrics, 
  getFinancialMetrics, 
  getFeedEfficiencyMetrics 
} from '@/services\analyticsService';
import { 
  Cattle, 
  TrendingUp, 
  Stethoscope, 
  DollarSign, 
  Package, 
  Activity,
  AlertTriangle,
  CheckCircle,
  XCircle
} from 'lucide-react';

export default function DashboardMetrics() {
  const [population, setPopulation] = useState<any>(null);
  const [growthMetrics, setGrowthMetrics] = useState<any>(null);
  const [healthMetrics, setHealthMetrics] = useState<any>(null);
  const [financialMetrics, setFinancialMetrics] = useState<any>(null);
  const [feedMetrics, setFeedMetrics] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchMetrics = async () => {
      try {
        const [
          popData,
          growthData,
          healthData,
          financialData,
          feedData
        ] = await Promise.all([
          getCattlePopulationSummary(),
          getGrowthMetrics(),
          getHealthMetrics(),
          getFinancialMetrics(),
          getFeedEfficiencyMetrics()
        ]);

        setPopulation(popData);
        setGrowthMetrics(growthData);
        setHealthMetrics(healthData);
        setFinancialMetrics(financialData);
        setFeedMetrics(feedData);
      } catch (error) {
        console.error('Error fetching metrics:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchMetrics();
  }, []);

  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {[...Array(8)].map((_, i) => (
          <div key={i} className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
            <div className="animate-pulse">
              <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
              <div className="h-8 bg-gray-200 rounded w-3/4"></div>
              <div className="h-4 bg-gray-200 rounded w-1/2 mt-2"></div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {/* Total Cattle */}
      <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
        <div className="flex items-center">
          <div className="p-3 rounded-lg bg-primary-100 text-primary-600">
            <Cattle className="w-6 h-6" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-600">Total Cattle</p>
            <p className="text-2xl font-semibold text-gray-900">{population?.total || 0}</p>
          </div>
        </div>
        <div className="mt-4 pt-4 border-t border-gray-100">
          <p className="text-xs text-gray-500">
            <span className="text-green-600 flex items-center">
              <CheckCircle className="w-3 h-3 mr-1" /> 
              {population?.active || 0} Active
            </span>
            <span className="text-yellow-600 flex items-center mt-1">
              <AlertTriangle className="w-3 h-3 mr-1" /> 
              {population?.sick || 0} Sick
            </span>
            <span className="text-blue-600 flex items-center mt-1">
              <XCircle className="w-3 h-3 mr-1" /> 
              {population?.sold || 0} Sold
            </span>
          </p>
        </div>
      </div>

      {/* Average Daily Gain */}
      <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
        <div className="flex items-center">
          <div className="p-3 rounded-lg bg-green-100 text-green-600">
            <TrendingUp className="w-6 h-6" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-600">Avg Daily Gain</p>
            <p className="text-2xl font-semibold text-gray-900">
              {growthMetrics?.averageADG || 0} <span className="text-sm font-normal">kg/day</span>
            </p>
          </div>
        </div>
        <div className="mt-4 pt-4 border-t border-gray-100">
          <p className="text-xs text-gray-500">
            Based on {growthMetrics?.totalWeightRecords || 0} records
          </p>
        </div>
      </div>

      {/* Health Metrics */}
      <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
        <div className="flex items-center">
          <div className="p-3 rounded-lg bg-red-100 text-red-600">
            <Stethoscope className="w-6 h-6" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-600">Health Index</p>
            <p className="text-2xl font-semibold text-gray-900">
              {healthMetrics ? (100 - healthMetrics.mortalityRate).toFixed(1) : '0.0'}%
            </p>
          </div>
        </div>
        <div className="mt-4 pt-4 border-t border-gray-100">
          <p className="text-xs text-gray-500">
            <span className="text-green-600">✓ {healthMetrics?.recoveredRecords || 0} Recovered</span>
            <span className="text-red-600 block mt-1">✗ {healthMetrics?.activeRecords || 0} Active Issues</span>
          </p>
        </div>
      </div>

      {/* Financial Metrics */}
      <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
        <div className="flex items-center">
          <div className="p-3 rounded-lg bg-yellow-100 text-yellow-600">
            <DollarSign className="w-6 h-6" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-600">Net Profit</p>
            <p className="text-2xl font-semibold text-gray-900">
              ${(financialMetrics?.profit || 0).toLocaleString()}
            </p>
          </div>
        </div>
        <div className="mt-4 pt-4 border-t border-gray-100">
          <p className="text-xs text-gray-500">
            <span>Margin: {(financialMetrics?.profitMargin || 0).toFixed(1)}%</span>
          </p>
        </div>
      </div>

      {/* Feed Conversion Ratio */}
      <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200 md:col-span-2">
        <div className="flex items-center">
          <div className="p-3 rounded-lg bg-blue-100 text-blue-600">
            <Package className="w-6 h-6" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-600">Feed Conversion Ratio</p>
            <p className="text-2xl font-semibold text-gray-900">
              {feedMetrics?.fcr || 0.0} <span className="text-sm font-normal">(Feed:Gain)</span>
            </p>
          </div>
        </div>
        <div className="mt-4 pt-4 border-t border-gray-100">
          <p className="text-xs text-gray-500">
            Total feed used: {(feedMetrics?.totalFeedUsed || 0).toLocaleString()} kg
          </p>
        </div>
      </div>

      {/* Revenue & Sales */}
      <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200 md:col-span-2">
        <div className="flex items-center">
          <div className="p-3 rounded-lg bg-purple-100 text-purple-600">
            <Activity className="w-6 h-6" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-600">Financial Summary</p>
            <div className="grid grid-cols-2 gap-4 mt-2">
              <div>
                <p className="text-xs text-gray-500">Total Revenue</p>
                <p className="font-semibold">${(financialMetrics?.totalRevenue || 0).toLocaleString()}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Avg Sale Price</p>
                <p className="font-semibold">${(financialMetrics?.averageSalePrice || 0).toLocaleString()}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Total Sales</p>
                <p className="font-semibold">{financialMetrics?.totalSales || 0}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Feed Cost</p>
                <p className="font-semibold">${(financialMetrics?.totalFeedCost || 0).toLocaleString()}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}