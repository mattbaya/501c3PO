const fs = require('fs');
const pdf = require('pdf-parse');

async function debugJune() {
  const dataBuffer = fs.readFileSync('/home/swca/scripts/501c3PO/treasurer-docs/2025 SWCA Bank Statements/MountainOne Bank Statement_06_Jun 2025.pdf');
  const data = await pdf(dataBuffer);
  const text = data.text;

  console.log('=== June PDF Text (first 2000 chars) ===');
  console.log(text.substring(0, 2000));
  console.log('\n=== Transaction Section ===');

  const transMatch = text.match(/Transaction Information([\s\S]{0,1500})/);
  if (transMatch) {
    console.log(transMatch[1]);
  } else {
    console.log('NOT FOUND');
  }
}

debugJune();
