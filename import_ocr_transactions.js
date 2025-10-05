const mysql = require('mysql2/promise');
const fs = require('fs');

const dbConfig = {
  host: 'localhost',
  user: 'swca_swca2019',
  password: '5Corners!',
  database: 'swca_swca2019'
};

async function importOCRTransactions() {
  let connection;

  try {
    console.log('Connecting to database...');
    connection = await mysql.createConnection(dbConfig);
    console.log('✓ Connected\n');

    const tableName = 'swca_swca_financial_transactions';

    // Read June OCR transactions
    const juneTransactions = JSON.parse(
      fs.readFileSync('/home/swca/scripts/501c3PO/ocr_transactions.json', 'utf8')
    );

    // Read August manual transactions
    const augustTransactions = JSON.parse(
      fs.readFileSync('/home/swca/scripts/501c3PO/august_transactions.json', 'utf8')
    );

    const allTransactions = [...juneTransactions, ...augustTransactions];

    console.log('='.repeat(60));
    console.log('Importing OCR\'d Transactions (June & August)');
    console.log('='.repeat(60));
    console.log(`June: ${juneTransactions.length} transactions`);
    console.log(`August: ${augustTransactions.length} transactions`);
    console.log(`Total: ${allTransactions.length} transactions to import\n`);

    let imported = 0;
    let skipped = 0;

    for (const transaction of allTransactions) {
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
        console.log(`  - Skipped duplicate: ${transaction.date} ${transaction.category} $${transaction.amount}`);
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
      console.log(`  ✓ ${transaction.date}: ${transaction.category} $${transaction.amount}`);
    }

    console.log('\n' + '='.repeat(60));
    console.log('Import Complete!');
    console.log('='.repeat(60));
    console.log(`Imported: ${imported} new transactions`);
    console.log(`Skipped: ${skipped} duplicate transactions`);

    // Show updated summary
    const [summary] = await connection.query(`
      SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        type,
        COUNT(*) as count,
        SUM(amount) as total
      FROM ${tableName}
      GROUP BY month, type
      ORDER BY month, type
    `);

    console.log('\n' + '='.repeat(60));
    console.log('Monthly Financial Summary');
    console.log('='.repeat(60));

    summary.forEach(row => {
      console.log(`${row.month} ${row.type.toUpperCase()}: ${row.count} transactions, $${parseFloat(row.total).toFixed(2)}`);
    });

    // Grand totals
    const [totals] = await connection.query(`
      SELECT
        type,
        SUM(amount) as total
      FROM ${tableName}
      GROUP BY type
    `);

    console.log('\n' + '='.repeat(60));
    totals.forEach(row => {
      console.log(`Total ${row.type.toUpperCase()}: $${parseFloat(row.total).toFixed(2)}`);
    });

    const totalIncome = totals.find(t => t.type === 'income')?.total || 0;
    const totalExpense = totals.find(t => t.type === 'expense')?.total || 0;
    console.log(`Net: $${(parseFloat(totalIncome) - parseFloat(totalExpense)).toFixed(2)}`);
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

importOCRTransactions();
