const XLSX = require('xlsx');

// Read the membership list
const membershipFile = '/home/swca/scripts/501c3PO/treasurer-docs/2024-2025 SWCA Membership List for Matt.xlsx';
const workbook = XLSX.readFile(membershipFile);
const sheetName = workbook.SheetNames[0];
const worksheet = workbook.Sheets[sheetName];
const data = XLSX.utils.sheet_to_json(worksheet);

console.log('=== 2024-2025 SWCA Membership List ===');
console.log('Total rows:', data.length);
console.log('\nFirst 3 rows:');
console.log(JSON.stringify(data.slice(0, 3), null, 2));
console.log('\nColumns:', Object.keys(data[0] || {}));

// Read the calculations file
const calculationsFile = '/home/swca/scripts/501c3PO/treasurer-docs/SWCA Membership_Contribution Calculations.xlsx';
const calcWorkbook = XLSX.readFile(calculationsFile);
console.log('\n\n=== SWCA Membership/Contribution Calculations ===');
console.log('Sheets:', calcWorkbook.SheetNames);

calcWorkbook.SheetNames.forEach(sheetName => {
  const sheet = calcWorkbook.Sheets[sheetName];
  const sheetData = XLSX.utils.sheet_to_json(sheet);
  console.log(`\n--- Sheet: ${sheetName} ---`);
  console.log('Rows:', sheetData.length);
  if (sheetData.length > 0) {
    console.log('Columns:', Object.keys(sheetData[0]));
    console.log('Sample data:');
    console.log(JSON.stringify(sheetData.slice(0, 2), null, 2));
  }
});
