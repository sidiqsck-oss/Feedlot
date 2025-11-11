'use client';

import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, LineChart, Line, Legend } from 'recharts';

// Mock data for charts - in a real app, this would come from the backend
const mockWeightGrowthData = [
  { date: 'Jan 1', weight: 300 },
  { date: 'Feb 1', weight: 320 },
  { date: 'Mar 1', weight: 340 },
  { date: 'Apr 1', weight: 365 },
  { date: 'May 1', weight: 390 },
  { date: 'Jun 1', weight: 420 },
];

const mockFeedUsageData = [
  { day: 'Mon', feed: 400 },
  { day: 'Tue', feed: 300 },
  { day: 'Wed', feed: 500 },
  { day: 'Thu', feed: 280 },
  { day: 'Fri', feed: 390 },
  { day: 'Sat', feed: 450 },
  { day: 'Sun', feed: 380 },
];

const mockSalesData = [
  { month: 'Jan', sales: 12000 },
  { month: 'Feb', sales: 19000 },
  { month: 'Mar', sales: 15000 },
  { month: 'Apr', sales: 18000 },
  { month: 'May', sales: 22000 },
  { month: 'Jun', sales: 25000 },
];

interface ChartProps {
  type: 'weight' | 'feed' | 'sales';
}

export default function ChartComponent({ type }: ChartProps) {
  let data = [];
  let chartTitle = '';
  let dataKey = '';
  let color = '';

  switch (type) {
    case 'weight':
      data = mockWeightGrowthData;
      chartTitle = 'Weight Growth Trend';
      dataKey = 'weight';
      color = '#3b82f6';
      break;
    case 'feed':
      data = mockFeedUsageData;
      chartTitle = 'Daily Feed Usage';
      dataKey = 'feed';
      color = '#10b981';
      break;
    case 'sales':
      data = mockSalesData;
      chartTitle = 'Monthly Sales';
      dataKey = 'sales';
      color = '#8b5cf6';
      break;
    default:
      data = [];
  }

  if (data.length === 0) {
    return <div>No data available</div>;
  }

  return (
    <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
      <h3 className="text-lg font-semibold text-gray-800 mb-4">{chartTitle}</h3>
      <div className="h-72">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart
            data={data}
            margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
          >
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="date" />
            <YAxis />
            <Tooltip />
            <Legend />
            <Line 
              type="monotone" 
              dataKey={dataKey} 
              stroke={color} 
              activeDot={{ r: 8 }} 
              strokeWidth={2}
              name={chartTitle}
            />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}