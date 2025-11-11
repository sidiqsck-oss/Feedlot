'use client';

import { useState } from 'react';
import { createHealthRecord } from '@/services\healthService';
import { Stethoscope, Calendar, Syringe, Pill, AlertTriangle, User } from 'lucide-react';

interface HealthRecordFormProps {
  cattleId: string;
  userId: string;
  onRecordCreated?: () => void;
}

export default function HealthRecordForm({ cattleId, userId, onRecordCreated }: HealthRecordFormProps) {
  const [formData, setFormData] = useState({
    symptoms: [] as string[],
    currentSymptom: '',
    diagnosis: '',
    treatment: '',
    medication: '',
    startDate: new Date().toISOString().split('T')[0],
    endDate: '',
    status: 'active',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleAddSymptom = () => {
    if (formData.currentSymptom.trim() !== '' && !formData.symptoms.includes(formData.currentSymptom.trim())) {
      setFormData(prev => ({
        ...prev,
        symptoms: [...prev.symptoms, prev.currentSymptom.trim()],
        currentSymptom: ''
      }));
    }
  };

  const handleRemoveSymptom = (index: number) => {
    setFormData(prev => ({
      ...prev,
      symptoms: prev.symptoms.filter((_, i) => i !== index)
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      await createHealthRecord({
        cattleId,
        userId,
        symptoms: formData.symptoms,
        diagnosis: formData.diagnosis,
        treatment: formData.treatment,
        medication: formData.medication,
        startDate: new Date(formData.startDate),
        endDate: formData.endDate ? new Date(formData.endDate) : undefined,
        status: formData.status,
        notes: formData.notes,
      });

      // Reset form
      setFormData({
        symptoms: [],
        currentSymptom: '',
        diagnosis: '',
        treatment: '',
        medication: '',
        startDate: new Date().toISOString().split('T')[0],
        endDate: '',
        status: 'active',
        notes: '',
      });

      if (onRecordCreated) onRecordCreated();
    } catch (err) {
      console.error('Error creating health record:', err);
      setError('Failed to create health record. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-xl font-bold text-gray-800 mb-4">Record Health Issue</h2>
      
      {error && (
        <div className="bg-red-50 text-red-700 p-3 rounded-md mb-4">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label htmlFor="startDate" className="block text-sm font-medium text-gray-700 mb-1">
              Start Date
            </label>
            <div className="relative">
              <input
                id="startDate"
                name="startDate"
                type="date"
                value={formData.startDate}
                onChange={handleChange}
                required
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              />
              <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="endDate" className="block text-sm font-medium text-gray-700 mb-1">
              End Date (Optional)
            </label>
            <div className="relative">
              <input
                id="endDate"
                name="endDate"
                type="date"
                value={formData.endDate}
                onChange={handleChange}
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              />
              <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
          </div>

          <div>
            <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-1">
              Status
            </label>
            <select
              id="status"
              name="status"
              value={formData.status}
              onChange={handleChange}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="active">Active</option>
              <option value="recovered">Recovered</option>
              <option value="chronic">Chronic</option>
              <option value="deceased">Deceased</option>
            </select>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Symptoms
          </label>
          <div className="flex mb-2">
            <input
              type="text"
              value={formData.currentSymptom}
              onChange={(e) => setFormData(prev => ({ ...prev, currentSymptom: e.target.value }))}
              className="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Add a symptom"
            />
            <button
              type="button"
              onClick={handleAddSymptom}
              className="px-4 py-2 bg-primary-600 text-white rounded-r-lg hover:bg-primary-700 transition duration-200"
            >
              Add
            </button>
          </div>
          <div className="flex flex-wrap gap-2 mt-2">
            {formData.symptoms.map((symptom, index) => (
              <span 
                key={index} 
                className="flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm"
              >
                {symptom}
                <button
                  type="button"
                  onClick={() => handleRemoveSymptom(index)}
                  className="ml-2 text-red-600 hover:text-red-800"
                >
                  ×
                </button>
              </span>
            ))}
          </div>
        </div>

        <div>
          <label htmlFor="diagnosis" className="block text-sm font-medium text-gray-700 mb-1">
            Diagnosis
          </label>
          <textarea
            id="diagnosis"
            name="diagnosis"
            value={formData.diagnosis}
            onChange={handleChange}
            rows={2}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
            placeholder="Diagnosis details"
          />
        </div>

        <div>
          <label htmlFor="treatment" className="block text-sm font-medium text-gray-700 mb-1">
            Treatment
          </label>
          <div className="relative">
            <input
              id="treatment"
              name="treatment"
              type="text"
              value={formData.treatment}
              onChange={handleChange}
              className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Treatment administered"
            />
            <Syringe className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
          </div>
        </div>

        <div>
          <label htmlFor="medication" className="block text-sm font-medium text-gray-700 mb-1">
            Medication
          </label>
          <div className="relative">
            <input
              id="medication"
              name="medication"
              type="text"
              value={formData.medication}
              onChange={handleChange}
              className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
              placeholder="Medication given"
            />
            <Pill className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
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
            placeholder="Additional observations or notes"
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
                <Stethoscope className="w-5 h-5 mr-2" />
                Record Health Issue
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}