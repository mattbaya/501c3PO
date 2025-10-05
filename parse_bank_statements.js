const fs = require('fs');
const path = require('path');
const pdf = require('pdf-parse');

const statementsDir = '/home/swca/scripts/501c3PO/treasurer-docs/2025 SWCA Bank Statements';

async function parseBankStatements() {
  const files = fs.readdirSync(statementsDir)
    .filter(f => f.endsWith('.pdf'))
    .sort();

  console.log('=== Parsing Bank Statements ===');
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

      // Extract beginning and ending balance
      const beginMatch = text.match(/Beginning Balance[\s\S]*?(\d+,?\d*\.\d+)/);
      const endMatch = text.match(/Ending Balance[\s\S]*?(\d+,?\d*\.\d+)/);
      const beginBalance = beginMatch ? parseFloat(beginMatch[1].replace(',', '')) : 0;
      const endBalance = endMatch ? parseFloat(endMatch[1].replace(',', '')) : 0;

      console.log(`  Period: ${period}`);
      console.log(`  Beginning Balance: $${beginBalance.toFixed(2)}`);
      console.log(`  Ending Balance: $${endBalance.toFixed(2)}`);

      // Extract transactions
      // Look for ACH Credits (Stripe deposits)
      const achPattern = /(\d{2}\/\d{2})\s+ACH Credit TRANSFER STRIPE\s+ID(\d+)\s+(\d+\.\d+)/g;
      let match;

      while ((match = achPattern.exec(text)) !== null) {
        const transaction = {
          date: match[1],
          type: 'income',
          category: 'Stripe Deposit',
          description: `ACH Credit TRANSFER STRIPE ID${match[2]}`,
          amount: parseFloat(match[3]),
          source: file,
          period: period
        };
        allTransactions.push(transaction);
        console.log(`    ✓ Stripe deposit: $${transaction.amount} on ${transaction.date}`);
      }

      // Look for Interest Credits
      const interestPattern = /(\d{2}\/\d{2})\s+Interest Credit\s+(\d+\.\d+)/g;
      while ((match = interestPattern.exec(text)) !== null) {
        const transaction = {
          date: match[1],
          type: 'income',
          category: 'Interest',
          description: 'Interest Credit',
          amount: parseFloat(match[2]),
          source: file,
          period: period
        };
        allTransactions.push(transaction);
        console.log(`    ✓ Interest: $${transaction.amount} on ${transaction.date}`);
      }

      // Look for Checks
      const checkPattern = /(\d{2}\/\d{2})\s+(\d+)\s+(\d+\.\d+)/g;
      const checkSection = text.match(/Check Information([\s\S]*?)Daily Balance/);
      if (checkSection) {
        const checksText = checkSection[1];
        while ((match = checkPattern.exec(checksText)) !== null) {
          const transaction = {
            date: match[1],
            type: 'expense',
            category: 'Check Payment',
            description: `Check #${match[2]}`,
            amount: parseFloat(match[3]),
            source: file,
            period: period
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
    '/home/swca/scripts/501c3PO/bank_transactions.json',
    JSON.stringify(allTransactions, null, 2)
  );
  console.log('\n✓ Saved transactions to: bank_transactions.json');

  fs.writeFileSync(
    '/home/swca/scripts/501c3PO/monthly_summaries.json',
    JSON.stringify(monthlySummaries, null, 2)
  );
  console.log('✓ Saved summaries to: monthly_summaries.json');
}

parseBankStatements();
