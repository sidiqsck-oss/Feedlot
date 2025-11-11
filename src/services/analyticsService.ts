import prisma from '@/lib/db';
import { Cattle, Sale, HealthRecord, FeedUsage, WeightRecord } from '@prisma/client';

// Get cattle population summary
export async function getCattlePopulationSummary() {
  const total = await prisma.cattle.count();
  const active = await prisma.cattle.count({
    where: { status: 'ACTIVE' }
  });
  const sold = await prisma.cattle.count({
    where: { status: 'SOLD' }
  });
  const sick = await prisma.cattle.count({
    where: { status: 'SICK' }
  });
  const quarantine = await prisma.cattle.count({
    where: { status: 'QUARANTINE' }
  });

  return {
    total,
    active,
    sold,
    sick,
    quarantine
  };
}

// Get growth metrics
export async function getGrowthMetrics() {
  // Get average daily gain across all cattle
  const weightRecords = await prisma.weightRecord.findMany({
    orderBy: { recordDate: 'asc' },
    take: 100, // Limit for performance
  });

  // Calculate average ADG
  let totalADG = 0;
  let validRecords = 0;
  
  for (const record of weightRecords) {
    if (record.adg) {
      totalADG += Number(record.adg);
      validRecords++;
    }
  }

  const averageADG = validRecords > 0 ? totalADG / validRecords : 0;

  return {
    averageADG: Number(averageADG.toFixed(2)),
    totalWeightRecords: weightRecords.length
  };
}

// Get health metrics
export async function getHealthMetrics() {
  const totalRecords = await prisma.healthRecord.count();
  const activeRecords = await prisma.healthRecord.count({
    where: { status: 'active' }
  });
  const recoveredRecords = await prisma.healthRecord.count({
    where: { status: 'recovered' }
  });

  const mortalityRate = totalRecords > 0 
    ? (await prisma.healthRecord.count({
        where: { status: 'deceased' }
      })) / totalRecords * 100 
    : 0;

  return {
    totalRecords,
    activeRecords,
    recoveredRecords,
    mortalityRate: Number(mortalityRate.toFixed(2))
  };
}

// Get financial metrics
export async function getFinancialMetrics() {
  const sales = await prisma.sale.findMany();
  
  const totalRevenue = sales.reduce((sum, sale) => sum.add(sale.salePrice), new prisma.Decimal(0));
  const averageSalePrice = sales.length > 0 
    ? totalRevenue.div(new prisma.Decimal(sales.length)) 
    : new prisma.Decimal(0);

  // Placeholder for feed costs (in a real app, this would come from feed usage)
  const totalFeedCost = new prisma.Decimal(15000); // Placeholder value
  
  const profit = totalRevenue.sub(totalFeedCost);

  return {
    totalRevenue: Number(totalRevenue),
    averageSalePrice: Number(averageSalePrice),
    totalSales: sales.length,
    totalFeedCost: Number(totalFeedCost),
    profit: Number(profit),
    profitMargin: totalRevenue.gt(0) 
      ? Number(profit.div(totalRevenue).mul(100)) 
      : 0
  };
}

// Get feed efficiency metrics
export async function getFeedEfficiencyMetrics() {
  // Placeholder values - in a real app, this would be calculated from feed usage
  const fcr = 6.5; // Feed conversion ratio
  const totalFeedUsed = 50000; // kg

  return {
    fcr,
    totalFeedUsed
  };
}

// Get recent activities
export async function getRecentActivities(limit: number = 5) {
  const [
    recentCattle,
    recentSales,
    recentHealthRecords,
    recentWeightRecords
  ] = await Promise.all([
    prisma.cattle.findMany({
      orderBy: { createdAt: 'desc' },
      take: limit,
      select: {
        id: true,
        cattleId: true,
        breed: true,
        createdAt: true,
        status: true
      }
    }),
    prisma.sale.findMany({
      orderBy: { saleDate: 'desc' },
      take: limit,
      include: {
        cattle: {
          select: { cattleId: true, breed: true }
        }
      }
    }),
    prisma.healthRecord.findMany({
      orderBy: { startDate: 'desc' },
      take: limit,
      include: {
        cattle: {
          select: { cattleId: true, breed: true }
        }
      }
    }),
    prisma.weightRecord.findMany({
      orderBy: { recordDate: 'desc' },
      take: limit,
      include: {
        cattle: {
          select: { cattleId: true, breed: true }
        }
      }
    })
  ]);

  // Combine and sort all activities by date
  const allActivities = [
    ...recentCattle.map(item => ({
      type: 'cattle',
      action: `New cattle registered: ${item.cattleId}`,
      date: item.createdAt,
      cattleId: item.cattleId
    })),
    ...recentSales.map(item => ({
      type: 'sale',
      action: `Cattle ${item.cattle.cattleId} sold for ${item.salePrice.toString()}`,
      date: item.saleDate,
      cattleId: item.cattle.cattleId
    })),
    ...recentHealthRecords.map(item => ({
      type: 'health',
      action: `Health record for cattle ${item.cattle.cattleId}`,
      date: item.startDate,
      cattleId: item.cattle.cattleId
    })),
    ...recentWeightRecords.map(item => ({
      type: 'weight',
      action: `Weight recorded for cattle ${item.cattle.cattleId}: ${item.weight.toString()} kg`,
      date: item.recordDate,
      cattleId: item.cattle.cattleId
    }))
  ].sort((a, b) => b.date.getTime() - a.date.getTime());

  return allActivities.slice(0, limit);
}