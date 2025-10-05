const fs = require('fs');
const pdf = require('pdf-parse');

async function debugAugust() {
  const dataBuffer = fs.readFileSync('/home/swca/scripts/501c3PO/treasurer-docs/2025 SWCA Bank Statements/MountainOne Bank Statement_08_Aug 2025.pdf');
  const data = await pdf(dataBuffer);
  const text = data.text;

  console.log('=== August PDF Full Text ===\n');
  console.log(text);
}

debugAugust();
