const mysql = require('mysql2/promise');
const fs = require('fs');

// Database credentials
const dbConfig = {
  host: 'localhost',
  user: 'swca_swca2019',
  password: '5Corners!',
  database: 'swca_swca2019'
};

async function importTransactions() {
  let connection;

  try {
    console.log('Connecting to database...');
    connection = await mysql.createConnection(dbConfig);
    console.log('✓ Connected\n');

    // Check if financial transactions table exists
    const tableName = 'swca_swca_financial_transactions';
    const [tables] = await connection.query("SHOW TABLES LIKE ?", [tableName]);

    if (tables.length === 0) {
      console.error(`✗ Table ${tableName} does not exist!`);
      console.error('Creating the table now...\n');

      // Create the table
      await connection.query(`
        CREATE TABLE ${tableName} (
          id INT AUTO_INCREMENT PRIMARY KEY,
          transaction_date DATE NOT NULL,
          type ENUM('income', 'expense') NOT NULL,
          category VARCHAR(100) NOT NULL,
          description TEXT,
          amount DECIMAL(10,2) NOT NULL,
          payment_method VARCHAR(50),
          notes TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
      `);

      console.log(`✓ Created table ${tableName}\n`);
    } else {
      console.log(`✓ Table ${tableName} exists\n`);
    }

    // Get current count
    const [countResult] = await connection.query(`SELECT COUNT(*) as count FROM ${tableName}`);
    console.log(`Current transaction count: ${countResult[0].count}\n`);

    // Read the JSON data
    const transactionsData = JSON.parse(
      fs.readFileSync('/home/swca/scripts/501c3PO/bank_transactions.json', 'utf8')
    );

    console.log('='.repeat(60));
    console.log('Starting Transaction Import');
    console.log('='.repeat(60));
    console.log(`Found ${transactionsData.length} transactions to import\n`);

    let imported = 0;
    let skipped = 0;

    for (const transaction of transactionsData) {
      // Parse date (format: MM/DD, need to add year)
      const [month, day] = transaction.date.split('/');
      // Extract year from period if available
      let year = '2025'; // default
      if (transaction.period) {
        const yearMatch = transaction.period.match(/\d{4}/);
        if (yearMatch) {
          year = yearMatch[0];
        }
      }
      const transactionDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;

      // Check if transaction already exists (by date, amount, and description)
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
      if (imported % 10 === 0) {
        console.log(`Imported ${imported} transactions...`);
      }
    }

    console.log('\n' + '='.repeat(60));
    console.log('Import Complete!');
    console.log('='.repeat(60));
    console.log(`Imported: ${imported} new transactions`);
    console.log(`Skipped: ${skipped} duplicate transactions`);
    console.log(`Total processed: ${imported + skipped}`);

    // Show summary by category
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
    console.log('Financial Summary by Category');
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

importTransactions();
