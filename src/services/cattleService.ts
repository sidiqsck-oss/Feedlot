import prisma from '@/lib/db';
import { Cattle, Purchase, Induction, WeightRecord } from '@prisma/client';

// Create a new cattle record
export async function createCattle(data: {
  cattleId: string;
  breed: string;
  gender: string;
  initialWeight: number;
  location?: string;
  notes?: string;
  photoUrl?: string;
  documentUrl?: string;
}): Promise<Cattle> {
  return await prisma.cattle.create({
    data: {
      cattleId: data.cattleId,
      breed: data.breed,
      gender: data.gender as any,
      initialWeight: new prisma.Decimal(data.initialWeight),
      currentWeight: new prisma.Decimal(data.initialWeight),
      location: data.location,
      notes: data.notes,
      photoUrl: data.photoUrl,
      documentUrl: data.documentUrl,
    },
  });
}

// Get all cattle with filters
export async function getCattle(filters?: {
  status?: string;
  location?: string;
  breed?: string;
}): Promise<Cattle[]> {
  return await prisma.cattle.findMany({
    where: {
      ...(filters?.status && { status: filters.status }),
      ...(filters?.location && { location: filters.location }),
      ...(filters?.breed && { breed: filters.breed }),
    },
    orderBy: {
      createdAt: 'desc',
    },
  });
}

// Get cattle by ID
export async function getCattleById(id: string): Promise<Cattle | null> {
  return await prisma.cattle.findUnique({
    where: { id },
  });
}

// Update cattle record
export async function updateCattle(
  id: string,
  data: Partial<Cattle>
): Promise<Cattle> {
  return await prisma.cattle.update({
    where: { id },
    data: {
      ...data,
      ...(data.currentWeight && { currentWeight: new prisma.Decimal(Number(data.currentWeight)) }),
    },
  });
}

// Record a cattle purchase
export async function createPurchase(data: {
  cattleId: string;
  supplierId: string;
  purchaseDate: Date;
  initialWeight: number;
  purchasePrice: number;
  notes?: string;
}): Promise<Purchase> {
  return await prisma.purchase.create({
    data: {
      cattleId: data.cattleId,
      supplierId: data.supplierId,
      purchaseDate: data.purchaseDate,
      initialWeight: new prisma.Decimal(data.initialWeight),
      purchasePrice: new prisma.Decimal(data.purchasePrice),
      notes: data.notes,
    },
  });
}

// Record cattle induction
export async function createInduction(data: {
  cattleId: string;
  inductionDate: Date;
  quarantineDays?: number;
  treatment?: string;
  status?: string;
  notes?: string;
}): Promise<Induction> {
  return await prisma.induction.create({
    data: {
      cattleId: data.cattleId,
      inductionDate: data.inductionDate,
      quarantineDays: data.quarantineDays,
      treatment: data.treatment,
      status: data.status,
      notes: data.notes,
    },
  });
}

// Record cattle weight
export async function createWeightRecord(data: {
  cattleId: string;
  weight: number;
  recordDate: Date;
  notes?: string;
}): Promise<WeightRecord> {
  // First, get the previous weight record to calculate ADG
  const previousRecord = await prisma.weightRecord.findFirst({
    where: { cattleId: data.cattleId },
    orderBy: { recordDate: 'desc' },
  });

  let adg: number | null = null;
  if (previousRecord) {
    const daysDiff = Math.floor(
      (data.recordDate.getTime() - previousRecord.recordDate.getTime()) / (1000 * 60 * 60 * 24)
    );
    
    if (daysDiff > 0) {
      adg = Number(
        (new prisma.Decimal(data.weight).sub(previousRecord.weight)).div(new prisma.Decimal(daysDiff))
      );
    }
  }

  // Update the cattle's current weight
  await prisma.cattle.update({
    where: { id: data.cattleId },
    data: { currentWeight: new prisma.Decimal(data.weight) },
  });

  return await prisma.weightRecord.create({
    data: {
      cattleId: data.cattleId,
      weight: new prisma.Decimal(data.weight),
      recordDate: data.recordDate,
      adg: adg !== null ? new prisma.Decimal(adg) : null,
      notes: data.notes,
    },
  });
}

// Calculate average daily gain for a cattle
export async function calculateADG(cattleId: string): Promise<number | null> {
  const records = await prisma.weightRecord.findMany({
    where: { cattleId },
    orderBy: { recordDate: 'asc' },
  });

  if (records.length < 2) return null;

  const firstRecord = records[0];
  const lastRecord = records[records.length - 1];

  const daysDiff = Math.floor(
    (lastRecord.recordDate.getTime() - firstRecord.recordDate.getTime()) / (1000 * 60 * 60 * 24)
  );

  if (daysDiff <= 0) return null;

  const weightDiff = Number(lastRecord.weight.sub(firstRecord.weight));
  return weightDiff / daysDiff;
}