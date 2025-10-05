const fs = require('fs');
const pdf = require('pdf-parse');

async function parseOCRStatement(filePath, fileName) {
  const dataBuffer = fs.readFileSync(filePath);
  const data = await pdf(dataBuffer);
  const text = data.text;

  console.log(`\nProcessing: ${fileName}`);

  // Extract period
  const periodMatch = text.match(/Period:\s+(\w+\s+\d+,\s+\d+)\s+to\s+(\w+\s+\d+,\s+\d+)/);
  const period = periodMatch ? `${periodMatch[1]} to ${periodMatch[2]}` : 'Unknown';

  // Extract year
  let year = '2025';
  if (periodMatch) {
    const yearMatch = periodMatch[2].match(/\d{4}/);
    if (yearMatch) year = yearMatch[0];
  }

  console.log(`  Period: ${period}`);

  const transactions = [];

  // Extract transaction section
  const transMatch = text.match(/Transaction Information([\s\S]*?)(?:Daily Balance|Check Information)/);
  if (!transMatch) {
    console.log('  ✗ No transaction section found');
    return transactions;
  }

  const transText = transMatch[1];

  // Split into lines
  const lines = transText.split('\n').map(l => l.trim()).filter(l => l);

  // Find where amounts start (look for "Debit" and "Credit" headers)
  // Try multiple patterns for amount section
  let amountSectionMatch = text.match(/Debit\s+Credit\s+Amount\s+Amount([\s\S]*?)(?:Daily Balance|Member FDIC)/);

  if (!amountSectionMatch) {
    // Try alternate pattern for June format
    amountSectionMatch = text.match(/Amount\s+Amount([\s\S]*?)(?:Daily Balance|Member FDIC)/);
  }

  if (!amountSectionMatch) {
    // Try August format with separate Debit/Credit columns
    const creditMatch = text.match(/Credit\s+Amount([\s\S]*?)(?:Amount|Balance|Member)/);
    if (creditMatch) {
      amountSectionMatch = [null, creditMatch[1]];
    }
  }

  let amounts = [];
  if (amountSectionMatch) {
    const amountText = amountSectionMatch[1];
    console.log(`  Amount section found, raw text:\n${amountText.substring(0, 300)}`);

    const amountLines = amountText.split('\n').map(l => l.trim()).filter(l => l);

    // Extract numbers (amounts)
    for (const line of amountLines) {
      // Match currency amounts like "34. 71" or "34.71" or "193.00" or "34 . 71" or "127. 37"
      const cleaned = line.replace(/\s+/g, ''); // Remove all whitespace
      if (/^\d+\.?\d*$/.test(cleaned)) {
        const amount = parseFloat(cleaned);
        if (!isNaN(amount) && amount > 0) {
          amounts.push(amount);
        }
      }
    }
  } else {
    console.log('  ⚠ Amount section not found in text');
  }

  console.log(`  Found ${amounts.length} amounts:`, amounts.map(a => `$${a}`).join(', '));

  // Parse transaction descriptions
  let currentIndex = 0;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];

    // Skip header lines
    if (line.includes('Description') || line.includes('Check#') || line.includes('Date')) {
      continue;
    }

    // Pattern: Date + Description
    // Example: "06/04 ACH Credit TRANSFER"
    const dateMatch = line.match(/^(\d{2}\/\d{2})\s+(.+)$/);
    if (dateMatch) {
      const date = dateMatch[1];
      let description = dateMatch[2];

      // Check next lines for continuation (ID numbers, STRIPE keyword, etc.)
      let j = i + 1;
      while (j < lines.length) {
        const nextLine = lines[j];

        // If next line starts with a date, stop
        if (nextLine.match(/^\d{2}\/\d{2}/)) {
          break;
        }

        // If it's an ID line
        if (nextLine.match(/^ID\d+$/)) {
          description += ` ${nextLine}`;
          i = j; // Skip this line in main loop
          j++;
          continue;
        }

        // If it's a continuation (STRIPE, etc.)
        if (nextLine.match(/^(STRIPE|Williamstown)/) || nextLine.includes('ID')) {
          description += ` ${nextLine}`;
          i = j;
          j++;
          continue;
        }

        break;
      }

      // Determine transaction type and category
      let type = 'income';
      let category = 'Other';

      if (description.includes('Interest Credit')) {
        category = 'Interest';
      } else if (description.includes('STRIPE') || description.includes('TRANSFER STRIPE')) {
        category = 'Stripe Deposit';
      } else if (description.includes('Deposit') || description.includes('Cash')) {
        category = 'Deposit';
      } else if (description.includes('ACH Credit')) {
        category = 'ACH Credit';
      }

      // Get corresponding amount
      const amount = amounts[currentIndex];
      if (amount === undefined) {
        console.log(`  ⚠ No amount for transaction: ${date} ${description}`);
        continue;
      }

      currentIndex++;

      const transaction = {
        date,
        type,
        category,
        description: description.trim(),
        amount,
        source: fileName,
        period,
        year
      };

      transactions.push(transaction);
      console.log(`    ✓ ${date}: ${category} $${amount}`);
    }
  }

  // Parse checks from Check Information section
  const checkMatch = text.match(/Check\s+Information([\s\S]*?)(?:Daily Balance|$)/);
  if (checkMatch) {
    const checkText = checkMatch[1];
    const checkPattern = /(\d{2}\/\d{2})\s+(\d+)\s+(\d+\.\d+)/g;
    let match;

    while ((match = checkPattern.exec(checkText)) !== null) {
      const transaction = {
        date: match[1],
        type: 'expense',
        category: 'Check Payment',
        description: `Check #${match[2]}`,
        amount: parseFloat(match[3]),
        source: fileName,
        period,
        year
      };
      transactions.push(transaction);
      console.log(`    ✓ ${match[1]}: Check #${match[2]} $${match[3]}`);
    }
  }

  console.log(`  Total transactions: ${transactions.length}`);
  return transactions;
}

async function main() {
  console.log('=== Parsing OCR\'d Bank Statements ===');

  const allTransactions = [];

  // Parse June
  const juneTransactions = await parseOCRStatement(
    '/home/swca/scripts/501c3PO/treasurer-docs/2025 SWCA Bank Statements/MountainOne Bank Statement_06_Jun 2025.pdf',
    'MountainOne Bank Statement_06_Jun 2025.pdf'
  );
  allTransactions.push(...juneTransactions);

  // Parse August
  const augTransactions = await parseOCRStatement(
    '/home/swca/scripts/501c3PO/treasurer-docs/2025 SWCA Bank Statements/MountainOne Bank Statement_08_Aug 2025.pdf',
    'MountainOne Bank Statement_08_Aug 2025.pdf'
  );
  allTransactions.push(...augTransactions);

  console.log('\n' + '='.repeat(60));
  console.log(`Total transactions extracted: ${allTransactions.length}`);

  const totalIncome = allTransactions.filter(t => t.type === 'income').reduce((sum, t) => sum + t.amount, 0);
  const totalExpense = allTransactions.filter(t => t.type === 'expense').reduce((sum, t) => sum + t.amount, 0);

  console.log(`Total income: $${totalIncome.toFixed(2)}`);
  console.log(`Total expenses: $${totalExpense.toFixed(2)}`);

  // Save to JSON
  fs.writeFileSync(
    '/home/swca/scripts/501c3PO/ocr_transactions.json',
    JSON.stringify(allTransactions, null, 2)
  );
  console.log('\n✓ Saved to: ocr_transactions.json');
}

main();
