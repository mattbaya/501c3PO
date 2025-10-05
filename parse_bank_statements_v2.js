const fs = require('fs');
const path = require('path');
const pdf = require('pdf-parse');

const statementsDir = '/home/swca/scripts/501c3PO/treasurer-docs/2025 SWCA Bank Statements';

async function parseBankStatements() {
  const files = fs.readdirSync(statementsDir)
    .filter(f => f.endsWith('.pdf'))
    .sort();

  console.log('=== Parsing Bank Statements (v2 - Multi-format) ===');
  console.log(`Found ${files.length} PDF files\n`);

  const allTransactions = [];
  const monthlySummaries = [];

  for (const file of files) {
    const filePath = path.join(statementsDir, file);
    console.log(`\nProcessing: ${file}`);

    try {
      const dataBuffer = fs.readFileSync(filePath);
      const data = await pdf(dataBuffer);
      const text = data.text;

      // Extract period dates
      const periodMatch = text.match(/Period:\s+(\w+\s+\d+,\s+\d+)\s+to\s+(\w+\s+\d+,\s+\d+)/);
      const period = periodMatch ? `${periodMatch[1]} to ${periodMatch[2]}` : 'Unknown';

      // Extract year from period
      let year = '2025'; // default
      if (periodMatch) {
        const yearMatch = periodMatch[2].match(/\d{4}/);
        if (yearMatch) year = yearMatch[0];
      }

      // Extract beginning and ending balance
      const beginMatch = text.match(/Beginning Balance[\s\S]*?(\d+,?\d*\.\d+)/);
      const endMatch = text.match(/Ending Balance[\s\S]*?(\d+,?\d*\.\d+)/);
      const beginBalance = beginMatch ? parseFloat(beginMatch[1].replace(',', '')) : 0;
      const endBalance = endMatch ? parseFloat(endMatch[1].replace(',', '')) : 0;

      console.log(`  Period: ${period}`);
      console.log(`  Beginning Balance: $${beginBalance.toFixed(2)}`);
      console.log(`  Ending Balance: $${endBalance.toFixed(2)}`);

      // Extract transaction section
      const transactionSection = text.match(/Transaction Information([\s\S]*?)(?:Check Information|Daily Balance)/);

      if (transactionSection) {
        const transText = transactionSection[1];

        // Split by lines and process
        const lines = transText.split('\n').map(l => l.trim()).filter(l => l);

        for (let i = 0; i < lines.length; i++) {
          const line = lines[i];

          // Pattern 1: Single line format (Jan-May)
          // 01/02    ACH Credit TRANSFER STRIPE ID4270465600    33.68
          const singleLineMatch = line.match(/^(\d{2}\/\d{2})\s+ACH Credit TRANSFER STRIPE\s+ID(\d+)\s+(\d+\.\d+)$/);
          if (singleLineMatch) {
            const transaction = {
              date: singleLineMatch[1],
              type: 'income',
              category: 'Stripe Deposit',
              description: `ACH Credit TRANSFER STRIPE ID${singleLineMatch[2]}`,
              amount: parseFloat(singleLineMatch[3]),
              source: file,
              period: period,
              year: year
            };
            allTransactions.push(transaction);
            console.log(`    ✓ Stripe deposit: $${transaction.amount} on ${transaction.date}`);
            continue;
          }

          // Pattern 2: Multi-line format (June+)
          // 06/04    ACH Credit TRANSFER STRIPE
          // Next line: ID1800948598    34.71
          const multiLineMatch = line.match(/^(\d{2}\/\d{2})\s+ACH Credit TRANSFER STRIPE$/);
          if (multiLineMatch && i + 1 < lines.length) {
            const nextLine = lines[i + 1];
            const idMatch = nextLine.match(/ID(\d+)\s+(\d+\.\d+)$/);
            if (idMatch) {
              const transaction = {
                date: multiLineMatch[1],
                type: 'income',
                category: 'Stripe Deposit',
                description: `ACH Credit TRANSFER STRIPE ID${idMatch[1]}`,
                amount: parseFloat(idMatch[2]),
                source: file,
                period: period,
                year: year
              };
              allTransactions.push(transaction);
              console.log(`    ✓ Stripe deposit: $${transaction.amount} on ${transaction.date}`);
              i++; // Skip next line since we processed it
              continue;
            }
          }

          // Pattern 3: ACH Credit with ID on same line (July format)
          // 07/01    ACH Credit TRANSFER STRIPE ID
          const achIdMatch = line.match(/^(\d{2}\/\d{2})\s+ACH Credit TRANSFER STRIPE ID$/);
          if (achIdMatch && i + 1 < lines.length) {
            const nextLine = lines[i + 1];
            const amountMatch = nextLine.match(/^(\d+)\s+(\d+\.\d+)$/);
            if (amountMatch) {
              const transaction = {
                date: achIdMatch[1],
                type: 'income',
                category: 'Stripe Deposit',
                description: `ACH Credit TRANSFER STRIPE ID${amountMatch[1]}`,
                amount: parseFloat(amountMatch[2]),
                source: file,
                period: period,
                year: year
              };
              allTransactions.push(transaction);
              console.log(`    ✓ Stripe deposit: $${transaction.amount} on ${transaction.date}`);
              i++; // Skip next line
              continue;
            }
          }

          // Pattern 4: ACH Credit BILL PMT (June format)
          const billPmtMatch = line.match(/^(\d{2}\/\d{2})\s+ACH Credit BILL PMT$/);
          if (billPmtMatch && i + 1 < lines.length) {
            const nextLine = lines[i + 1];
            const descMatch = nextLine.match(/^(.+?)\s+ID(\d+)\s+(\d+\.\d+)$/);
            if (descMatch) {
              const transaction = {
                date: billPmtMatch[1],
                type: 'income',
                category: 'ACH Credit',
                description: `ACH Credit BILL PMT ${descMatch[1]} ID${descMatch[2]}`,
                amount: parseFloat(descMatch[3]),
                source: file,
                period: period,
                year: year
              };
              allTransactions.push(transaction);
              console.log(`    ✓ ACH Credit: $${transaction.amount} on ${transaction.date}`);
              i++; // Skip next line
              continue;
            }
          }

          // Pattern 5: Deposit (simple)
          const depositMatch = line.match(/^(\d{2}\/\d{2})\s+Deposit\s+(\d+\.\d+)$/);
          if (depositMatch) {
            const transaction = {
              date: depositMatch[1],
              type: 'income',
              category: 'Deposit',
              description: 'Deposit',
              amount: parseFloat(depositMatch[2]),
              source: file,
              period: period,
              year: year
            };
            allTransactions.push(transaction);
            console.log(`    ✓ Deposit: $${transaction.amount} on ${transaction.date}`);
            continue;
          }

          // Pattern 6: Interest Credit
          const interestMatch = line.match(/^(\d{2}\/\d{2})\s+Interest Credit\s+(\d+\.\d+)$/);
          if (interestMatch) {
            const transaction = {
              date: interestMatch[1],
              type: 'income',
              category: 'Interest',
              description: 'Interest Credit',
              amount: parseFloat(interestMatch[2]),
              source: file,
              period: period,
              year: year
            };
            allTransactions.push(transaction);
            console.log(`    ✓ Interest: $${transaction.amount} on ${transaction.date}`);
            continue;
          }
        }
      }

      // Extract checks from Check Information section
      const checkSection = text.match(/Check Information([\s\S]*?)(?:Daily Balance|$)/);
      if (checkSection) {
        const checksText = checkSection[1];
        // Pattern: Date Check# Amount
        const checkPattern = /(\d{2}\/\d{2})\s+(\d+)\s+(\d+\.\d+)/g;
        let match;
        while ((match = checkPattern.exec(checksText)) !== null) {
          const transaction = {
            date: match[1],
            type: 'expense',
            category: 'Check Payment',
            description: `Check #${match[2]}`,
            amount: parseFloat(match[3]),
            source: file,
            period: period,
            year: year
          };
          allTransactions.push(transaction);
          console.log(`    ✓ Check #${match[2]}: $${transaction.amount} on ${transaction.date}`);
        }
      }

      // Store monthly summary
      monthlySummaries.push({
        file: file,
        period: period,
        beginBalance: beginBalance,
        endBalance: endBalance,
        transactionCount: allTransactions.filter(t => t.source === file).length
      });

    } catch (error) {
      console.error(`  ✗ Error parsing ${file}: ${error.message}`);
    }
  }

  console.log('\n' + '='.repeat(60));
  console.log('Parsing Complete');
  console.log('='.repeat(60));
  console.log(`Total transactions extracted: ${allTransactions.length}`);

  const totalIncome = allTransactions.filter(t => t.type === 'income').reduce((sum, t) => sum + t.amount, 0);
  const totalExpense = allTransactions.filter(t => t.type === 'expense').reduce((sum, t) => sum + t.amount, 0);

  console.log(`Total income: $${totalIncome.toFixed(2)}`);
  console.log(`Total expenses: $${totalExpense.toFixed(2)}`);
  console.log(`Net: $${(totalIncome - totalExpense).toFixed(2)}`);

  // Save to JSON
  fs.writeFileSync(
    '/home/swca/scripts/501c3PO/bank_transactions_v2.json',
    JSON.stringify(allTransactions, null, 2)
  );
  console.log('\n✓ Saved transactions to: bank_transactions_v2.json');

  fs.writeFileSync(
    '/home/swca/scripts/501c3PO/monthly_summaries_v2.json',
    JSON.stringify(monthlySummaries, null, 2)
  );
  console.log('✓ Saved summaries to: monthly_summaries_v2.json');
}

parseBankStatements();
