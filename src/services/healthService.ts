import prisma from '@/lib/db';
import { HealthRecord } from '@prisma/client';

// Create a new health record
export async function createHealthRecord(data: {
  cattleId: string;
  userId: string;
  symptoms: string[];
  diagnosis?: string;
  treatment?: string;
  medication?: string;
  startDate: Date;
  endDate?: Date;
  status?: string;
  notes?: string;
}): Promise<HealthRecord> {
  return await prisma.healthRecord.create({
    data: {
      cattleId: data.cattleId,
      userId: data.userId,
      symptoms: data.symptoms,
      diagnosis: data.diagnosis,
      treatment: data.treatment,
      medication: data.medication,
      startDate: data.startDate,
      endDate: data.endDate,
      status: data.status,
      notes: data.notes,
    },
  });
}

// Get health records for a specific cattle
export async function getHealthRecordsByCattleId(cattleId: string): Promise<HealthRecord[]> {
  return await prisma.healthRecord.findMany({
    where: { cattleId },
    orderBy: { startDate: 'desc' },
  });
}

// Get health records by status
export async function getHealthRecordsByStatus(status: string): Promise<HealthRecord[]> {
  return await prisma.healthRecord.findMany({
    where: { status },
    orderBy: { startDate: 'desc' },
  });
}

// Get all health records with optional filters
export async function getHealthRecords(filters?: {
  cattleId?: string;
  startDate?: Date;
  endDate?: Date;
  status?: string;
}): Promise<HealthRecord[]> {
  return await prisma.healthRecord.findMany({
    where: {
      ...(filters?.cattleId && { cattleId: filters.cattleId }),
      ...(filters?.status && { status: filters.status }),
      ...(filters?.startDate && { startDate: { gte: filters.startDate } }),
      ...(filters?.endDate && { startDate: { lte: filters.endDate } }),
    },
    orderBy: { startDate: 'desc' },
  });
}

// Update a health record
export async function updateHealthRecord(
  id: string,
  data: Partial<HealthRecord>
): Promise<HealthRecord> {
  return await prisma.healthRecord.update({
    where: { id },
    data: {
      ...data,
      ...(data.symptoms && { symptoms: data.symptoms }),
    },
  });
}

// Calculate health metrics for a cattle
export async function calculateHealthMetrics(cattleId: string) {
  const records = await getHealthRecordsByCattleId(cattleId);
  
  const totalRecords = records.length;
  const activeRecords = records.filter(r => r.status === 'active').length;
  const recoveredRecords = records.filter(r => r.status === 'recovered').length;
  const chronicRecords = records.filter(r => r.status === 'chronic').length;
  
  return {
    totalRecords,
    activeRecords,
    recoveredRecords,
    chronicRecords,
    healthIndex: totalRecords > 0 ? (recoveredRecords / totalRecords) * 100 : 100
  };
}