import prisma from '@/lib/db';
import { Sale, Cattle } from '@prisma/client';

// Create a new sale record
export async function createSale(data: {
  cattleId: string;
  userId: string;
  finalWeight: number;
  salePrice: number;
  buyerName: string;
  buyerContact?: string;
  saleDate: Date;
  notes?: string;
}): Promise<Sale> {
  // First, update the cattle status to SOLD
  await prisma.cattle.update({
    where: { id: data.cattleId },
    data: { status: 'SOLD' },
  });

  return await prisma.sale.create({
    data: {
      cattleId: data.cattleId,
      userId: data.userId,
      finalWeight: new prisma.Decimal(data.finalWeight),
      salePrice: new prisma.Decimal(data.salePrice),
      buyerName: data.buyerName,
      buyerContact: data.buyerContact,
      saleDate: data.saleDate,
      notes: data.notes,
    },
  });
}

// Get all sales with optional filters
export async function getSales(filters?: {
  cattleId?: string;
  startDate?: Date;
  endDate?: Date;
  buyerName?: string;
}): Promise<Sale[]> {
  return await prisma.sale.findMany({
    where: {
      ...(filters?.cattleId && { cattleId: filters.cattleId }),
      ...(filters?.buyerName && { buyerName: { contains: filters.buyerName, mode: 'insensitive' } }),
      ...(filters?.startDate && { saleDate: { gte: filters.startDate } }),
      ...(filters?.endDate && { saleDate: { lte: filters.endDate } }),
    },
    include: {
      cattle: true,
      user: true,
    },
    orderBy: { saleDate: 'desc' },
  });
}

// Get sale by ID
export async function getSaleById(id: string): Promise<Sale | null> {
  return await prisma.sale.findUnique({
    where: { id },
    include: {
      cattle: true,
      user: true,
    },
  });
}

// Get sales summary (for analytics)
export async function getSalesSummary(): Promise<{
  totalSales: number;
  totalRevenue: number;
  averageSalePrice: number;
  averageWeight: number;
}> {
  const sales = await prisma.sale.findMany();

  if (sales.length === 0) {
    return {
      totalSales: 0,
      totalRevenue: 0,
      averageSalePrice: 0,
      averageWeight: 0,
    };
  }

  const totalRevenue = sales.reduce((sum, sale) => sum.add(sale.salePrice), new prisma.Decimal(0));
  const totalWeight = sales.reduce((sum, sale) => sum.add(sale.finalWeight), new prisma.Decimal(0));

  return {
    totalSales: sales.length,
    totalRevenue: Number(totalRevenue),
    averageSalePrice: Number(totalRevenue.div(new prisma.Decimal(sales.length))),
    averageWeight: Number(totalWeight.div(new prisma.Decimal(sales.length))),
  };
}

// Calculate profit for a specific sale
export async function calculateSaleProfit(saleId: string): Promise<number | null> {
  const sale = await prisma.sale.findUnique({
    where: { id: saleId },
    include: {
      cattle: {
        include: {
          purchase: true
        }
      }
    }
  });

  if (!sale || !sale.cattle.purchase) {
    return null;
  }

  const purchasePrice = sale.cattle.purchase.purchasePrice;
  const salePrice = sale.salePrice;
  const profit = salePrice.sub(purchasePrice);

  return Number(profit);
}