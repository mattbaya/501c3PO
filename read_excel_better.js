const XLSX = require('xlsx');

// Read the membership list
const membershipFile = '/home/swca/scripts/501c3PO/treasurer-docs/2024-2025 SWCA Membership List for Matt.xlsx';
const workbook = XLSX.readFile(membershipFile);
const sheetName = workbook.SheetNames[0];
const worksheet = workbook.Sheets[sheetName];

// Get the raw data with header row detection
const data = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });

console.log('=== 2024-2025 SWCA Membership List (Raw) ===');
console.log('Total rows:', data.length);
console.log('\nFirst 10 rows to identify header:');
data.slice(0, 10).forEach((row, idx) => {
  console.log(`Row ${idx}:`, row.slice(0, 10)); // First 10 columns
});

// Find the actual header row (look for "Last Name", "First Name", etc.)
let headerRowIndex = -1;
for (let i = 0; i < Math.min(10, data.length); i++) {
  const row = data[i];
  if (row.some(cell => typeof cell === 'string' && (cell.includes('Last Name') || cell.includes('First Name')))) {
    headerRowIndex = i;
    break;
  }
}

if (headerRowIndex >= 0) {
  console.log('\n\nHeader found at row', headerRowIndex);
  console.log('Headers:', data[headerRowIndex]);

  // Get data starting from next row
  const headers = data[headerRowIndex];
  const memberData = data.slice(headerRowIndex + 1).map(row => {
    const obj = {};
    headers.forEach((header, idx) => {
      if (header) {
        obj[header] = row[idx] || '';
      }
    });
    return obj;
  }).filter(obj => Object.keys(obj).length > 0);

  console.log('\n\nParsed member data (first 3):');
  console.log(JSON.stringify(memberData.slice(0, 3), null, 2));
  console.log('\n\nTotal members:', memberData.length);
}
