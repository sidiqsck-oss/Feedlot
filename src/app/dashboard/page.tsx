'use client';

import { useState, useEffect } from 'react';
import DashboardMetrics from '@/components\DashboardMetrics';
import RecentActivities from '@/components\RecentActivities';
import ChartComponent from '@/components\ChartComponent';
import ExportButton from '@/components\ExportButton';
import { 
  Cattle, 
  Package, 
  Stethoscope, 
  DollarSign, 
  TrendingUp, 
  AlertTriangle, 
  CheckCircle, 
  User,
  Calendar,
  Download
} from 'lucide-react';
import Link from 'next/link';

export default function DashboardPage() {
  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center">
            <TrendingUp className="w-8 h-8 mr-3 text-primary-600" />
            Feedlot Dashboard
          </h1>
          <p className="text-gray-600 mt-2">Overview of your feedlot operations</p>
        </div>
        <div className="mt-4 md:mt-0">
          <ExportButton reportType="cattle" />
        </div>
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <Link href="/cattle" className="bg-white rounded-xl shadow-md p-6 border border-gray-200 hover:shadow-lg transition-shadow duration-200">
          <div className="flex items-center">
            <div className="p-3 rounded-lg bg-blue-100 text-blue-600">
              <Cattle className="w-6 h-6" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Active Cattle</p>
              <p className="text-2xl font-semibold text-gray-900">142</p>
            </div>
          </div>
        </Link>

        <Link href="/feed" className="bg-white rounded-xl shadow-md p-6 border border-gray-200 hover:shadow-lg transition-shadow duration-200">
          <div className="flex items-center">
            <div className="p-3 rounded-lg bg-green-100 text-green-600">
              <Package className="w-6 h-6" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Feed Stock</p>
              <p className="text-2xl font-semibold text-gray-900">5.2t</p>
            </div>
          </div>
        </Link>

        <Link href="/health" className="bg-white rounded-xl shadow-md p-6 border border-gray-200 hover:shadow-lg transition-shadow duration-200">
          <div className="flex items-center">
            <div className="p-3 rounded-lg bg-red-100 text-red-600">
              <Stethoscope className="w-6 h-6" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Health Issues</p>
              <p className="text-2xl font-semibold text-gray-900">3</p>
            </div>
          </div>
        </Link>

        <Link href="/sales" className="bg-white rounded-xl shadow-md p-6 border border-gray-200 hover:shadow-lg transition-shadow duration-200">
          <div className="flex items-center">
            <div className="p-3 rounded-lg bg-yellow-100 text-yellow-600">
              <DollarSign className="w-6 h-6" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Monthly Sales</p>
              <p className="text-2xl font-semibold text-gray-900">$42.8K</p>
            </div>
          </div>
        </Link>
      </div>

      {/* Main Dashboard Content */}
      <DashboardMetrics />

      {/* Charts and Activities */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
        <div className="lg:col-span-2 space-y-6">
          <div className="flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-800">Weight Growth Trend</h2>
            <ExportButton reportType="cattle" variant="icon" />
          </div>
          <ChartComponent type="weight" />
          
          <div className="flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-800">Monthly Sales</h2>
            <ExportButton reportType="sales" variant="icon" />
          </div>
          <ChartComponent type="sales" />
        </div>
        
        <div className="space-y-6">
          <div className="flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-800">Recent Activities</h2>
            <ExportButton reportType="cattle" variant="icon" />
          </div>
          <RecentActivities />
          
          <div className="flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-800">Daily Feed Usage</h2>
            <ExportButton reportType="feed" variant="icon" />
          </div>
          <ChartComponent type="feed" />
        </div>
      </div>

      {/* Alerts Section */}
      <div className="mt-8 bg-white rounded-xl shadow-md p-6 border border-gray-200">
        <div className="flex items-center mb-4">
          <AlertTriangle className="w-6 h-6 mr-2 text-yellow-600" />
          <h2 className="text-lg font-semibold text-gray-800">System Alerts</h2>
          <div className="ml-auto">
            <ExportButton reportType="health" variant="icon" />
          </div>
        </div>
        <div className="space-y-3">
          <div className="flex items-start p-3 bg-yellow-50 rounded-lg border border-yellow-200">
            <AlertTriangle className="w-5 h-5 text-yellow-600 mt-0.5 mr-3" />
            <div>
              <p className="font-medium text-yellow-800">Low Stock Alert</p>
              <p className="text-sm text-yellow-700">Corn feed stock is below minimum threshold (150kg remaining)</p>
            </div>
          </div>
          <div className="flex items-start p-3 bg-red-50 rounded-lg border border-red-200">
            <AlertTriangle className="w-5 h-5 text-red-600 mt-0.5 mr-3" />
            <div>
              <p className="font-medium text-red-800">Health Alert</p>
              <p className="text-sm text-red-700">3 cattle showing signs of respiratory issues</p>
            </div>
          </div>
          <div className="flex items-start p-3 bg-blue-50 rounded-lg border border-blue-200">
            <CheckCircle className="w-5 h-5 text-blue-600 mt-0.5 mr-3" />
            <div>
              <p className="font-medium text-blue-800">Growth Target Met</p>
              <p className="text-sm text-blue-700">Group 3 has exceeded target ADG of 1.2kg/day</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}