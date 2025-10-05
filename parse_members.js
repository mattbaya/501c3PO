const XLSX = require('xlsx');
const fs = require('fs');

// Read the membership list
const membershipFile = '/home/swca/scripts/501c3PO/treasurer-docs/2024-2025 SWCA Membership List for Matt.xlsx';
const workbook = XLSX.readFile(membershipFile);
const sheetName = workbook.SheetNames[0];
const worksheet = workbook.Sheets[sheetName];

// Get the raw data with header row detection
const data = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });

console.log('=== Parsing 2024-2025 SWCA Membership List ===');
console.log('Total rows:', data.length);

// Find the actual header row (look for "Last Name", "First Name", etc.)
let headerRowIndex = -1;
for (let i = 0; i < Math.min(10, data.length); i++) {
  const row = data[i];
  if (row.some(cell => typeof cell === 'string' && (cell.includes('Last Name') || cell.includes('First Name')))) {
    headerRowIndex = i;
    break;
  }
}

if (headerRowIndex < 0) {
  console.error('ERROR: Could not find header row!');
  process.exit(1);
}

console.log('Header found at row', headerRowIndex);
const headers = data[headerRowIndex];
console.log('Headers:', headers.slice(0, 15).join(', '), '...\n');

// Get data starting from next row
const memberData = data.slice(headerRowIndex + 1).map(row => {
  const obj = {};
  headers.forEach((header, idx) => {
    if (header) {
      obj[header] = row[idx] || '';
    }
  });
  return obj;
}).filter(obj => {
  // Keep rows that have at least a last name or first name
  return obj['Last Name'] || obj['First Name'];
});

console.log('Parsed members:', memberData.length);
console.log('\nSample member data:');
console.log(JSON.stringify(memberData[0], null, 2));

// Write to JSON file
const outputFile = '/home/swca/scripts/501c3PO/members_data.json';
fs.writeFileSync(outputFile, JSON.stringify(memberData, null, 2));
console.log('\n✓ Saved to:', outputFile);
console.log('\nNow run: php /home/swca/scripts/501c3PO/import_members.php');
