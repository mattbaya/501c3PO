const fs = require('fs');
const pdf = require('pdf-parse');

async function debugOCRPDFs() {
  // Check June
  console.log('=== JUNE 2025 OCR PDF ===\n');
  const juneBuffer = fs.readFileSync('/home/swca/scripts/501c3PO/treasurer-docs/2025 SWCA Bank Statements/MountainOne Bank Statement_06_Jun 2025.pdf');
  const juneData = await pdf(juneBuffer);

  console.log('Full text length:', juneData.text.length);
  console.log('\nSearching for "Transaction Information"...');

  const juneTransMatch = juneData.text.match(/Transaction Information([\s\S]{0,2000})/);
  if (juneTransMatch) {
    console.log('FOUND Transaction section:');
    console.log(juneTransMatch[1]);
  } else {
    console.log('NOT FOUND - showing first 3000 chars:');
    console.log(juneData.text.substring(0, 3000));
  }

  console.log('\n\n' + '='.repeat(60));
  console.log('=== AUGUST 2025 OCR PDF ===\n');

  const augBuffer = fs.readFileSync('/home/swca/scripts/501c3PO/treasurer-docs/2025 SWCA Bank Statements/MountainOne Bank Statement_08_Aug 2025.pdf');
  const augData = await pdf(augBuffer);

  console.log('Full text length:', augData.text.length);
  console.log('\nSearching for "Transaction Information"...');

  const augTransMatch = augData.text.match(/Transaction Information([\s\S]{0,2000})/);
  if (augTransMatch) {
    console.log('FOUND Transaction section:');
    console.log(augTransMatch[1]);
  } else {
    console.log('NOT FOUND - showing first 3000 chars:');
    console.log(augData.text.substring(0, 3000));
  }
}

debugOCRPDFs();
