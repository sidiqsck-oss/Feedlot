'use client';

import { useState, useEffect } from 'react';
import { getRecentActivities } from '@/services\analyticsService';
import { 
  Cattle, 
  DollarSign, 
  Stethoscope, 
  Scale, 
  Calendar,
  Activity
} from 'lucide-react';
import { format } from 'date-fns';

interface ActivityItem {
  type: string;
  action: string;
  date: Date;
  cattleId: string;
}

export default function RecentActivities() {
  const [activities, setActivities] = useState<ActivityItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchActivities = async () => {
      try {
        const data = await getRecentActivities(10);
        setActivities(data);
      } catch (error) {
        console.error('Error fetching activities:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchActivities();
  }, []);

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'cattle':
        return <Cattle className="w-5 h-5 text-blue-600" />;
      case 'sale':
        return <DollarSign className="w-5 h-5 text-green-600" />;
      case 'health':
        return <Stethoscope className="w-5 h-5 text-red-600" />;
      case 'weight':
        return <Scale className="w-5 h-5 text-gray-600" />;
      default:
        return <Activity className="w-5 h-5 text-gray-600" />;
    }
  };

  if (loading) {
    return (
      <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold text-gray-800">Recent Activities</h2>
        </div>
        <div className="space-y-3">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="flex items-start animate-pulse">
              <div className="w-2 h-2 bg-gray-300 rounded-full mt-2 mr-3"></div>
              <div className="flex-1">
                <div className="h-4 bg-gray-200 rounded w-3/4"></div>
                <div className="h-3 bg-gray-200 rounded w-1/2 mt-2"></div>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl shadow-md p-6 border border-gray-200">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-lg font-semibold text-gray-800">Recent Activities</h2>
        <Calendar className="w-5 h-5 text-gray-500" />
      </div>
      
      <div className="space-y-4">
        {activities.length > 0 ? (
          activities.map((activity, index) => (
            <div key={index} className="flex items-start">
              <div className="mt-1 mr-3">
                {getActivityIcon(activity.type)}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-gray-900 truncate">{activity.action}</p>
                <p className="text-xs text-gray-500">
                  Cattle: {activity.cattleId} • {format(new Date(activity.date), 'MMM d, yyyy HH:mm')}
                </p>
              </div>
            </div>
          ))
        ) : (
          <p className="text-gray-500 text-center py-4">No recent activities</p>
        )}
      </div>
    </div>
  );
}