const mysql = require('mysql2/promise');
const fs = require('fs');

const dbConfig = {
  host: 'localhost',
  user: 'swca_swca2019',
  password: '5Corners!',
  database: 'swca_swca2019'
};

async function importManualTransactions() {
  let connection;

  try {
    console.log('Connecting to database...');
    connection = await mysql.createConnection(dbConfig);
    console.log('✓ Connected\n');

    const tableName = 'swca_swca_financial_transactions';

    // Read the manual transactions
    const manualTransactions = JSON.parse(
      fs.readFileSync('/home/swca/scripts/501c3PO/manual_june_aug.json', 'utf8')
    );

    console.log('='.repeat(60));
    console.log('Importing Manual Transactions (June-July)');
    console.log('='.repeat(60));
    console.log(`Found ${manualTransactions.length} transactions to import\n`);

    let imported = 0;
    let skipped = 0;

    for (const transaction of manualTransactions) {
      const [month, day] = transaction.date.split('/');
      const transactionDate = `${transaction.year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;

      // Check if transaction already exists
      const [existing] = await connection.query(
        `SELECT * FROM ${tableName}
         WHERE transaction_date = ?
         AND amount = ?
         AND description = ?`,
        [transactionDate, transaction.amount, transaction.description]
      );

      if (existing.length > 0) {
        skipped++;
        continue;
      }

      // Determine payment method
      let paymentMethod = 'Bank Transfer';
      if (transaction.category === 'Check Payment') {
        paymentMethod = 'Check';
      } else if (transaction.category === 'Stripe Deposit') {
        paymentMethod = 'Stripe';
      } else if (transaction.category === 'Interest') {
        paymentMethod = 'Bank Interest';
      } else if (transaction.category === 'Deposit') {
        paymentMethod = 'Deposit';
      } else if (transaction.category === 'ACH Credit') {
        paymentMethod = 'ACH';
      }

      // Insert transaction
      await connection.query(
        `INSERT INTO ${tableName}
         (transaction_date, type, category, description, amount, payment_method, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [
          transactionDate,
          transaction.type,
          transaction.category,
          transaction.description,
          transaction.amount,
          paymentMethod,
          `Source: ${transaction.source}, Period: ${transaction.period}`
        ]
      );

      imported++;
      console.log(`✓ ${transaction.date}: ${transaction.category} $${transaction.amount}`);
    }

    console.log('\n' + '='.repeat(60));
    console.log('Import Complete!');
    console.log('='.repeat(60));
    console.log(`Imported: ${imported} new transactions`);
    console.log(`Skipped: ${skipped} duplicate transactions`);

    // Show updated summary
    const [summary] = await connection.query(`
      SELECT
        type,
        category,
        COUNT(*) as count,
        SUM(amount) as total
      FROM ${tableName}
      GROUP BY type, category
      ORDER BY type, category
    `);

    console.log('\n' + '='.repeat(60));
    console.log('Updated Financial Summary by Category');
    console.log('='.repeat(60));

    let totalIncome = 0;
    let totalExpense = 0;

    summary.forEach(row => {
      console.log(`${row.type.toUpperCase()}: ${row.category}`);
      console.log(`  Count: ${row.count}, Total: $${parseFloat(row.total).toFixed(2)}`);

      if (row.type === 'income') {
        totalIncome += parseFloat(row.total);
      } else {
        totalExpense += parseFloat(row.total);
      }
    });

    console.log('\n' + '='.repeat(60));
    console.log(`Total Income: $${totalIncome.toFixed(2)}`);
    console.log(`Total Expenses: $${totalExpense.toFixed(2)}`);
    console.log(`Net: $${(totalIncome - totalExpense).toFixed(2)}`);
    console.log('='.repeat(60));

  } catch (error) {
    console.error('ERROR:', error.message);
    process.exit(1);
  } finally {
    if (connection) {
      await connection.end();
    }
  }
}

importManualTransactions();
