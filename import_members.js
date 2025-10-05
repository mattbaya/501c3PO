const mysql = require('mysql2/promise');
const fs = require('fs');

// Database credentials from wp-config.php
const dbConfig = {
  host: 'localhost',
  user: 'swca_swca2019',
  password: '5Corners!',
  database: 'swca_swca2019'
};

async function importMembers() {
  let connection;

  try {
    // Connect to database
    console.log('Connecting to database...');
    connection = await mysql.createConnection(dbConfig);
    console.log('✓ Connected\n');

    // Check if table exists
    const tableName = 'swca_swca_members';
    const [tables] = await connection.query("SHOW TABLES LIKE ?", [tableName]);

    if (tables.length === 0) {
      console.error(`✗ Table ${tableName} does not exist!`);
      console.error('You may need to activate the SWCA plugin first.');
      process.exit(1);
    }

    console.log(`✓ Table ${tableName} exists\n`);

    // Get current count
    const [countResult] = await connection.query(`SELECT COUNT(*) as count FROM ${tableName}`);
    console.log(`Current member count: ${countResult[0].count}\n`);

    // Show table structure
    const [columns] = await connection.query(`SHOW COLUMNS FROM ${tableName}`);
    console.log('Table columns:');
    columns.forEach(col => {
      console.log(`  - ${col.Field} (${col.Type})`);
    });

    console.log('\n' + '='.repeat(60));
    console.log('Starting Member Import');
    console.log('='.repeat(60) + '\n');

    // Read the JSON data
    const membersData = JSON.parse(fs.readFileSync('/home/swca/scripts/501c3PO/members_data.json', 'utf8'));
    console.log(`Found ${membersData.length} members to import\n`);

    let imported = 0;
    let updated = 0;
    let skipped = 0;

    for (const member of membersData) {
      // Skip empty rows
      if (!member['Last Name'] && !member['First Name']) {
        skipped++;
        continue;
      }

      const lastName = (member['Last Name'] || '').toString().trim();
      const firstName = (member['First Name'] || '').toString().trim();

      // Check if member already exists
      const [existing] = await connection.query(
        `SELECT * FROM ${tableName} WHERE last_name = ? AND first_name = ?`,
        [lastName, firstName]
      );

      // Parse membership month if it's an Excel date number
      let membershipMonth = '';
      if (member['Membership Month'] && !isNaN(member['Membership Month'])) {
        const excelDate = parseInt(member['Membership Month']);
        // Excel dates start from 1900-01-01
        const unixTimestamp = (excelDate - 25569) * 86400 * 1000;
        const date = new Date(unixTimestamp);
        membershipMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
      } else if (member['Membership Month']) {
        membershipMonth = member['Membership Month'].toString();
      }

      // Prepare data
      const data = {
        first_name: firstName,
        last_name: lastName,
        partner_first_name: (member['First Name (Partner)'] || '').toString(),
        partner_last_name: (member['Last Name (Partner)'] || '').toString(),
        family_members: (member['Family Members'] || '').toString(),
        email_1: (member['email 1'] || '').toString().trim(),
        email_2: (member['email 2'] || '').toString().trim(),
        email_3: (member['email 3'] || '').toString().trim(),
        email_4: (member['email 4'] || '').toString().trim(),
        phone: (member['Phone #'] || '').toString(),
        address: (member['Address'] || '').toString(),
        city: (member['City'] || '').toString(),
        state: (member['State'] || '').toString(),
        zip_code: (member['ZIP'] || '').toString(),
        membership_type: (member['Individual or Family'] || '').toString(),
        status_2024_2025: (member['2024-2025 Status'] || '').toString(),
        membership_amount: parseFloat(member['Membership Amount'] || 0) || 0,
        donation_amount: parseFloat(member['Donation Amount'] || 0) || 0,
        total_amount: parseFloat(member['Total'] || 0) || 0,
        payment_type: (member['Type'] || '').toString(),
        on_swca_email_list: (member['On SWCA email list'] && member['On SWCA email list'] !== 'No') ? 1 : 0,
        notes: (member['On Bette\'s List'] || '').toString(),
        membership_month: membershipMonth
      };

      if (existing.length > 0) {
        // Update existing member
        const updateFields = Object.keys(data).map(key => `${key} = ?`).join(', ');
        const updateValues = [...Object.values(data), existing[0].id];
        await connection.query(
          `UPDATE ${tableName} SET ${updateFields} WHERE id = ?`,
          updateValues
        );
        updated++;
        if (updated % 50 === 0) {
          console.log(`Updated ${updated} members...`);
        }
      } else {
        // Insert new member
        const insertFields = Object.keys(data).join(', ');
        const placeholders = Object.keys(data).map(() => '?').join(', ');
        await connection.query(
          `INSERT INTO ${tableName} (${insertFields}) VALUES (${placeholders})`,
          Object.values(data)
        );
        imported++;
        if (imported % 50 === 0) {
          console.log(`Imported ${imported} new members...`);
        }
      }
    }

    console.log('\n' + '='.repeat(60));
    console.log('Import Complete!');
    console.log('='.repeat(60));
    console.log(`Imported: ${imported} new members`);
    console.log(`Updated: ${updated} existing members`);
    console.log(`Skipped: ${skipped} empty rows`);
    console.log(`Total processed: ${imported + updated + skipped}`);

  } catch (error) {
    console.error('ERROR:', error.message);
    if (error.code === 'ER_NO_SUCH_TABLE') {
      console.error('\nThe SWCA members table does not exist.');
      console.error('Please activate the SWCA plugin in WordPress first.');
    } else if (error.code === 'ER_ACCESS_DENIED_ERROR') {
      console.error('\nDatabase access denied. Please check the credentials.');
    }
    process.exit(1);
  } finally {
    if (connection) {
      await connection.end();
    }
  }
}

importMembers();
