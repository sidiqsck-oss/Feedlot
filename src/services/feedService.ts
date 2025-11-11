import prisma from '@/lib/db';
import { RawMaterial, RawMaterialPurchase, Ration, FeedUsage } from '@prisma/client';

// Create a new raw material
export async function createRawMaterial(data: {
  name: string;
  category: string;
  unit: string;
  currentStock: number;
  minStock: number;
  pricePerUnit: number;
  supplierId?: string;
  notes?: string;
}): Promise<RawMaterial> {
  return await prisma.rawMaterial.create({
    data: {
      name: data.name,
      category: data.category,
      unit: data.unit,
      currentStock: new prisma.Decimal(data.currentStock),
      minStock: new prisma.Decimal(data.minStock),
      pricePerUnit: new prisma.Decimal(data.pricePerUnit),
      supplierId: data.supplierId,
      notes: data.notes,
    },
  });
}

// Get all raw materials with filters
export async function getRawMaterials(filters?: {
  category?: string;
  lowStock?: boolean;
}): Promise<RawMaterial[]> {
  return await prisma.rawMaterial.findMany({
    where: {
      ...(filters?.category && { category: filters.category }),
      ...(filters?.lowStock && { 
        currentStock: { lt: prisma.RawMaterial.fields.minStock } 
      }),
    },
    include: {
      supplier: true
    },
    orderBy: {
      name: 'asc',
    },
  });
}

// Get raw material by ID
export async function getRawMaterialById(id: string): Promise<RawMaterial | null> {
  return await prisma.rawMaterial.findUnique({
    where: { id },
    include: {
      supplier: true
    },
  });
}

// Update raw material stock
export async function updateRawMaterialStock(
  id: string,
  quantity: number,
  operation: 'add' | 'subtract'
): Promise<RawMaterial> {
  const material = await prisma.rawMaterial.findUnique({
    where: { id },
  });

  if (!material) {
    throw new Error('Raw material not found');
  }

  const updatedStock = operation === 'add'
    ? material.currentStock.add(new prisma.Decimal(quantity))
    : material.currentStock.sub(new prisma.Decimal(quantity));

  if (updatedStock.isNegative()) {
    throw new Error('Insufficient stock');
  }

  return await prisma.rawMaterial.update({
    where: { id },
    data: { currentStock: updatedStock },
  });
}

// Record raw material purchase
export async function createRawMaterialPurchase(data: {
  rawMaterialId: string;
  supplierId: string;
  purchaseDate: Date;
  quantity: number;
  unitPrice: number;
  notes?: string;
}): Promise<RawMaterialPurchase> {
  // Calculate total cost
  const totalCost = new prisma.Decimal(data.quantity).mul(new prisma.Decimal(data.unitPrice));

  // Create the purchase record
  const purchase = await prisma.rawMaterialPurchase.create({
    data: {
      rawMaterialId: data.rawMaterialId,
      supplierId: data.supplierId,
      purchaseDate: data.purchaseDate,
      quantity: new prisma.Decimal(data.quantity),
      unitPrice: new prisma.Decimal(data.unitPrice),
      totalCost,
      notes: data.notes,
    },
  });

  // Update the raw material stock
  await updateRawMaterialStock(data.rawMaterialId, data.quantity, 'add');

  return purchase;
}

// Create a new ration formula
export async function createRation(data: {
  name: string;
  description?: string;
  ingredients: { rawMaterialId: string; quantity: number }[];
  nutritionalValue?: any;
  targetGroup?: string;
  isActive: boolean;
}): Promise<Ration> {
  // Convert ingredients to Prisma Json format
  const ingredientsJson = data.ingredients.map(ing => ({
    rawMaterialId: ing.rawMaterialId,
    quantity: Number(ing.quantity)
  }));

  return await prisma.ration.create({
    data: {
      name: data.name,
      description: data.description,
      ingredients: ingredientsJson,
      nutritionalValue: data.nutritionalValue,
      targetGroup: data.targetGroup,
      isActive: data.isActive,
    },
  });
}

// Get all rations
export async function getRations(filters?: {
  isActive?: boolean;
  targetGroup?: string;
}): Promise<Ration[]> {
  return await prisma.ration.findMany({
    where: {
      ...(filters?.isActive !== undefined && { isActive: filters.isActive }),
      ...(filters?.targetGroup && { targetGroup: filters.targetGroup }),
    },
    orderBy: {
      name: 'asc',
    },
  });
}

// Get ration by ID
export async function getRationById(id: string): Promise<Ration | null> {
  return await prisma.ration.findUnique({
    where: { id },
  });
}

// Record feed usage
export async function recordFeedUsage(data: {
  rawMaterialId: string;
  rationId?: string;
  cattleGroupId?: string;
  userId: string;
  usageDate: Date;
  quantity: number;
  notes?: string;
}): Promise<FeedUsage> {
  // Create the feed usage record
  const feedUsage = await prisma.feedUsage.create({
    data: {
      rawMaterialId: data.rawMaterialId,
      rationId: data.rationId,
      cattleGroupId: data.cattleGroupId,
      userId: data.userId,
      usageDate: data.usageDate,
      quantity: new prisma.Decimal(data.quantity),
      notes: data.notes,
    },
  });

  // Update the raw material stock
  await updateRawMaterialStock(data.rawMaterialId, data.quantity, 'subtract');

  return feedUsage;
}

// Calculate feed conversion ratio (FCR) for a cattle group
export async function calculateFCR(cattleGroupId: string): Promise<number | null> {
  // This would be calculated based on feed usage and weight gain
  // For this implementation, we'll return a placeholder
  return 6.5; // Example FCR value
}