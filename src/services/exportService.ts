import { Cattle, Sale, HealthRecord, RawMaterial, FeedUsage } from '@prisma/client';
import { getCattle } from './cattleService';
import { getSales } from './salesService';
import { getHealthRecords } from './healthService';
import { getRawMaterials } from './feedService';

// Generate cattle report as CSV
export async function generateCattleReport(format: 'csv' | 'json' | 'pdf' = 'csv') {
  const cattle = await getCattle();
  
  if (format === 'csv') {
    // Create CSV content
    const headers = ['ID', 'Breed', 'Gender', 'Initial Weight', 'Current Weight', 'Status', 'Location', 'Created At'];
    const rows = cattle.map(c => [
      c.cattleId,
      c.breed,
      c.gender,
      c.initialWeight?.toString(),
      c.currentWeight?.toString(),
      c.status,
      c.location || '',
      c.createdAt.toISOString().split('T')[0]
    ]);
    
    const csvContent = [
      headers.join(','),
      ...rows.map(row => row.join(','))
    ].join('\n');
    
    return csvContent;
  } else if (format === 'json') {
    // Return JSON data
    return JSON.stringify(cattle, null, 2);
  } else {
    // For PDF, we'll return formatted data that can be used by a PDF library
    return {
      title: 'Cattle Report',
      data: cattle,
      columns: ['ID', 'Breed', 'Gender', 'Initial Weight', 'Current Weight', 'Status', 'Location', 'Created At'],
      rows: cattle.map(c => [
        c.cattleId,
        c.breed,
        c.gender,
        c.initialWeight?.toString() || '',
        c.currentWeight?.toString() || '',
        c.status,
        c.location || '',
        c.createdAt.toISOString().split('T')[0]
      ])
    };
  }
}

// Generate sales report
export async function generateSalesReport(format: 'csv' | 'json' | 'pdf' = 'csv') {
  const sales = await getSales();
  
  if (format === 'csv') {
    const headers = ['Cattle ID', 'Breed', 'Final Weight', 'Sale Price', 'Buyer', 'Sale Date'];
    const rows = sales.map(s => [
      s.cattle.cattleId,
      s.cattle.breed,
      s.finalWeight.toString(),
      s.salePrice.toString(),
      s.buyerName,
      s.saleDate.toISOString().split('T')[0]
    ]);
    
    const csvContent = [
      headers.join(','),
      ...rows.map(row => row.join(','))
    ].join('\n');
    
    return csvContent;
  } else if (format === 'json') {
    return JSON.stringify(sales, null, 2);
  } else {
    return {
      title: 'Sales Report',
      data: sales,
      columns: ['Cattle ID', 'Breed', 'Final Weight', 'Sale Price', 'Buyer', 'Sale Date'],
      rows: sales.map(s => [
        s.cattle.cattleId,
        s.cattle.breed,
        s.finalWeight.toString(),
        s.salePrice.toString(),
        s.buyerName,
        s.saleDate.toISOString().split('T')[0]
      ])
    };
  }
}

// Generate health report
export async function generateHealthReport(format: 'csv' | 'json' | 'pdf' = 'csv') {
  const healthRecords = await getHealthRecords();
  
  if (format === 'csv') {
    const headers = ['Cattle ID', 'Symptoms', 'Diagnosis', 'Treatment', 'Status', 'Start Date', 'End Date'];
    const rows = healthRecords.map(h => [
      h.cattleId,
      h.symptoms.join('; '),
      h.diagnosis || '',
      h.treatment || '',
      h.status || '',
      h.startDate.toISOString().split('T')[0],
      h.endDate ? h.endDate.toISOString().split('T')[0] : ''
    ]);
    
    const csvContent = [
      headers.join(','),
      ...rows.map(row => `"${row.join('","')}"`
    ].join('\n');
    
    return csvContent;
  } else if (format === 'json') {
    return JSON.stringify(healthRecords, null, 2);
  } else {
    return {
      title: 'Health Report',
      data: healthRecords,
      columns: ['Cattle ID', 'Symptoms', 'Diagnosis', 'Treatment', 'Status', 'Start Date', 'End Date'],
      rows: healthRecords.map(h => [
        h.cattleId,
        h.symptoms.join('; '),
        h.diagnosis || '',
        h.treatment || '',
        h.status || '',
        h.startDate.toISOString().split('T')[0],
        h.endDate ? h.endDate.toISOString().split('T')[0] : ''
      ])
    };
  }
}

// Generate feed report
export async function generateFeedReport(format: 'csv' | 'json' | 'pdf' = 'csv') {
  const rawMaterials = await getRawMaterials();
  
  if (format === 'csv') {
    const headers = ['Name', 'Category', 'Unit', 'Current Stock', 'Min Stock', 'Price per Unit', 'Supplier'];
    const rows = rawMaterials.map(rm => [
      rm.name,
      rm.category,
      rm.unit,
      rm.currentStock.toString(),
      rm.minStock.toString(),
      rm.pricePerUnit.toString(),
      rm.supplier?.name || ''
    ]);
    
    const csvContent = [
      headers.join(','),
      ...rows.map(row => row.join(','))
    ].join('\n');
    
    return csvContent;
  } else if (format === 'json') {
    return JSON.stringify(rawMaterials, null, 2);
  } else {
    return {
      title: 'Feed Report',
      data: rawMaterials,
      columns: ['Name', 'Category', 'Unit', 'Current Stock', 'Min Stock', 'Price per Unit', 'Supplier'],
      rows: rawMaterials.map(rm => [
        rm.name,
        rm.category,
        rm.unit,
        rm.currentStock.toString(),
        rm.minStock.toString(),
        rm.pricePerUnit.toString(),
        rm.supplier?.name || ''
      ])
    };
  }
}