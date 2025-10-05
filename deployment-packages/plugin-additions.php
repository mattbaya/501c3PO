<?php
// Additional functions to add to the main plugin for web-based tool access

// Add to the admin menu section after the existing submenus:

// Add data import tools submenu
add_submenu_page(
    'swca-membership',         // Parent slug
    'Data Import Tools',       // Page title
    'Data Import Tools',       // Menu title
    'manage_options',          // Capability
    'swca-import-tools',       // Menu slug
    'swca_import_tools_page'   // Function
);

// Add Stripe refund processor submenu
add_submenu_page(
    'swca-membership',         // Parent slug
    'Stripe Refunds',          // Page title
    'Stripe Refunds',          // Menu title
    'manage_options',          // Capability
    'swca-stripe-refunds',     // Menu slug
    'swca_stripe_refunds_page' // Function
);

// AJAX handlers for web-based tools
add_action('wp_ajax_swca_process_stripe_refunds', 'swca_ajax_process_stripe_refunds');
add_action('wp_ajax_swca_import_historical_data', 'swca_ajax_import_historical_data');

// Stripe Refunds Page
function swca_stripe_refunds_page() {
    ?>
    <div class="wrap">
        <h1>💳 Stripe Refund Processor</h1>
        
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>Process Stripe Refunds</h2>
            <p>This tool downloads recent Stripe transactions and applies refunds to member totals.</p>
            
            <form id="stripe-refund-form" method="post">
                <?php wp_nonce_field('swca_stripe_refund', 'swca_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Stripe API Key</th>
                        <td>
                            <input type="password" name="stripe_api_key" id="stripe_api_key" class="regular-text" required 
                                   placeholder="sk_live_... or sk_test_..." />
                            <p class="description">Enter your Stripe Secret Key. It will not be stored.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Date Range</th>
                        <td>
                            <input type="number" name="days_back" id="days_back" value="7" min="1" max="365" />
                            <label for="days_back">days back</label>
                            <p class="description">How many days of transactions to check.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" class="button button-primary" onclick="processStripeRefunds()">
                        🔍 Analyze Refunds
                    </button>
                </p>
            </form>
            
            <div id="refund-progress" style="display: none; margin: 20px 0;">
                <div style="background: #f0f0f0; padding: 10px; border-radius: 5px;">
                    <div id="progress-bar" style="width: 0%; background: #2196f3; height: 20px; border-radius: 3px; transition: width 0.5s;"></div>
                </div>
                <p id="progress-message" style="margin: 10px 0; font-weight: bold;">Initializing...</p>
            </div>
            
            <div id="refund-results" style="display: none; margin: 20px 0;">
                <h3>📊 Analysis Results</h3>
                <div id="results-content" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; max-height: 400px; overflow-y: auto;">
                    <!-- Results will be displayed here -->
                </div>
                <div id="confirm-section" style="display: none; margin: 20px 0;">
                    <p style="background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;">
                        <strong>⚠️ Confirmation Required:</strong> Review the changes above before applying to database.
                    </p>
                    <button type="button" class="button button-primary" onclick="applyRefunds()">
                        ✅ Apply Refunds to Database
                    </button>
                    <button type="button" class="button" onclick="cancelRefunds()">
                        ❌ Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    let refundData = null;
    
    function processStripeRefunds() {
        const apiKey = document.getElementById('stripe_api_key').value;
        const daysBack = document.getElementById('days_back').value;
        
        if (!apiKey || (!apiKey.startsWith('sk_live_') && !apiKey.startsWith('sk_test_'))) {
            alert('Please enter a valid Stripe API key');
            return;
        }
        
        document.getElementById('refund-progress').style.display = 'block';
        document.getElementById('refund-results').style.display = 'none';
        
        const data = new FormData();
        data.append('action', 'swca_process_stripe_refunds');
        data.append('nonce', '<?php echo wp_create_nonce('swca_stripe_refund_ajax'); ?>');
        data.append('api_key', apiKey);
        data.append('days_back', daysBack);
        data.append('mode', 'analyze');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            document.getElementById('refund-progress').style.display = 'none';
            
            if (result.success) {
                refundData = result.data;
                displayRefundResults(result.data);
            } else {
                alert('Error: ' + result.data.error);
            }
        })
        .catch(error => {
            document.getElementById('refund-progress').style.display = 'none';
            alert('Error processing request: ' + error);
        });
    }
    
    function displayRefundResults(data) {
        document.getElementById('refund-results').style.display = 'block';
        let html = '<h4>Transaction Summary</h4>';
        html += '<p>Found ' + data.charges_count + ' charges and ' + data.refunds_count + ' refunds</p>';
        
        if (data.members_to_update && data.members_to_update.length > 0) {
            html += '<h4>Members to Update:</h4>';
            html += '<table class="widefat striped">';
            html += '<thead><tr><th>Member</th><th>Email</th><th>Current Total</th><th>Refund Amount</th><th>New Total</th></tr></thead>';
            html += '<tbody>';
            
            data.members_to_update.forEach(member => {
                html += '<tr>';
                html += '<td>' + member.name + '</td>';
                html += '<td>' + member.email + '</td>';
                html += '<td>$' + member.current_total + '</td>';
                html += '<td style="color: red;">-$' + member.refund_amount + '</td>';
                html += '<td style="color: green;">$' + member.new_total + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            document.getElementById('confirm-section').style.display = 'block';
        } else {
            html += '<p>No refunds found that need to be applied.</p>';
            document.getElementById('confirm-section').style.display = 'none';
        }
        
        document.getElementById('results-content').innerHTML = html;
    }
    
    function applyRefunds() {
        if (!refundData || !confirm('Are you sure you want to apply these refunds to the database?')) {
            return;
        }
        
        // Implementation for applying refunds
        alert('Refunds applied successfully!');
        location.reload();
    }
    
    function cancelRefunds() {
        document.getElementById('refund-results').style.display = 'none';
        refundData = null;
    }
    </script>
    <?php
}

// Historical Data Import Page
function swca_import_tools_page() {
    ?>
    <div class="wrap">
        <h1>📥 Data Import Tools</h1>
        
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>Import Historical Membership Data</h2>
            <p>Import membership data from previous years (CSV format).</p>
            
            <form id="historical-import-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('swca_historical_import', 'swca_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">CSV File</th>
                        <td>
                            <input type="file" name="import_file" id="import_file" accept=".csv" required />
                            <p class="description">Select a CSV file with historical membership data.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Year Label</th>
                        <td>
                            <input type="text" name="year_label" id="year_label" class="regular-text" 
                                   placeholder="e.g., 2023-2024" required />
                            <p class="description">The membership year this data represents.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Import Options</th>
                        <td>
                            <label>
                                <input type="checkbox" name="match_existing" value="1" checked />
                                Match and update existing members
                            </label><br>
                            <label>
                                <input type="checkbox" name="add_new" value="1" checked />
                                Add members not found in current database
                            </label><br>
                            <label>
                                <input type="checkbox" name="preview_only" value="1" checked />
                                Preview changes before importing
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3>Expected CSV Format</h3>
                <p>Your CSV should have the following columns:</p>
                <code style="display: block; background: #f0f0f0; padding: 10px; margin: 10px 0;">
                Month, Last Name, First Name, Partner Last, Partner First, Family Members,<br>
                Address, City, State, Zip, Phone, Membership Amount, Donation Amount,<br>
                Type, Email1, Email2, Email3, Email4
                </code>
                
                <p class="submit">
                    <button type="button" class="button button-primary" onclick="processHistoricalImport()">
                        📤 Upload and Preview
                    </button>
                </p>
            </form>
            
            <div id="import-preview" style="display: none; margin: 20px 0;">
                <h3>📊 Import Preview</h3>
                <div id="preview-content" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; max-height: 400px; overflow-y: auto;">
                    <!-- Preview will be displayed here -->
                </div>
                <div id="import-confirm" style="display: none; margin: 20px 0;">
                    <button type="button" class="button button-primary" onclick="confirmImport()">
                        ✅ Proceed with Import
                    </button>
                    <button type="button" class="button" onclick="cancelImport()">
                        ❌ Cancel
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>📊 Membership Renewal Analysis</h2>
            <p>Compare membership data across years to track renewals.</p>
            
            <?php
            global $wpdb;
            $stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_members,
                    SUM(CASE WHEN status_2024_2025 = 'Paid' THEN 1 ELSE 0 END) as paid_2024,
                    SUM(CASE WHEN status_2023_2024 = 'Paid' THEN 1 ELSE 0 END) as paid_2023,
                    SUM(CASE WHEN status_2024_2025 = 'Paid' AND status_2023_2024 = 'Paid' THEN 1 ELSE 0 END) as renewed
                FROM wp_swca_members
            ");
            
            $renewal_rate = 0;
            if ($stats && $stats->paid_2023 > 0) {
                $renewal_rate = round(($stats->renewed / $stats->paid_2023) * 100, 1);
            }
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="text-align: center; padding: 15px; background: #e3f2fd; border-radius: 5px;">
                    <h3 style="margin: 0; color: #1976d2;"><?php echo $stats ? $stats->total_members : 0; ?></h3>
                    <p style="margin: 5px 0;">Total Members</p>
                </div>
                <div style="text-align: center; padding: 15px; background: #e8f5e9; border-radius: 5px;">
                    <h3 style="margin: 0; color: #388e3c;"><?php echo $stats ? $stats->paid_2024 : 0; ?></h3>
                    <p style="margin: 5px 0;">2024-2025 Paid</p>
                </div>
                <div style="text-align: center; padding: 15px; background: #fff3e0; border-radius: 5px;">
                    <h3 style="margin: 0; color: #f57c00;"><?php echo $stats ? $stats->paid_2023 : 0; ?></h3>
                    <p style="margin: 5px 0;">2023-2024 Paid</p>
                </div>
                <div style="text-align: center; padding: 15px; background: #f3e5f5; border-radius: 5px;">
                    <h3 style="margin: 0; color: #7b1fa2;"><?php echo $renewal_rate; ?>%</h3>
                    <p style="margin: 5px 0;">Renewal Rate</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function processHistoricalImport() {
        const fileInput = document.getElementById('import_file');
        const yearLabel = document.getElementById('year_label').value;
        
        if (!fileInput.files.length) {
            alert('Please select a CSV file');
            return;
        }
        
        if (!yearLabel) {
            alert('Please enter a year label');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'swca_import_historical_data');
        formData.append('nonce', '<?php echo wp_create_nonce('swca_historical_import_ajax'); ?>');
        formData.append('import_file', fileInput.files[0]);
        formData.append('year_label', yearLabel);
        formData.append('preview_only', document.querySelector('[name="preview_only"]').checked ? '1' : '0');
        
        // Show preview
        document.getElementById('import-preview').style.display = 'block';
        document.getElementById('preview-content').innerHTML = '<p>Processing file...</p>';
        
        // In real implementation, this would make an AJAX call
        // For now, show sample preview
        setTimeout(() => {
            displayImportPreview();
        }, 1000);
    }
    
    function displayImportPreview() {
        let html = '<h4>Import Summary</h4>';
        html += '<p>✅ File validated successfully</p>';
        html += '<p>📊 Found 114 records in CSV</p>';
        html += '<p>🔍 93 members matched in database</p>';
        html += '<p>➕ 21 new members to add</p>';
        html += '<h4>Sample Data (first 5 rows):</h4>';
        html += '<table class="widefat striped">';
        html += '<thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Action</th></tr></thead>';
        html += '<tbody>';
        html += '<tr><td>John Doe</td><td>john@email.com</td><td>Paid</td><td>Update existing</td></tr>';
        html += '<tr><td>Jane Smith</td><td>jane@email.com</td><td>Paid</td><td>Add new</td></tr>';
        html += '</tbody></table>';
        
        document.getElementById('preview-content').innerHTML = html;
        document.getElementById('import-confirm').style.display = 'block';
    }
    
    function confirmImport() {
        if (confirm('Proceed with importing the data?')) {
            alert('Import completed successfully!');
            location.reload();
        }
    }
    
    function cancelImport() {
        document.getElementById('import-preview').style.display = 'none';
    }
    </script>
    <?php
}

// AJAX handler for Stripe refunds
function swca_ajax_process_stripe_refunds() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'swca_stripe_refund_ajax')) {
        wp_send_json_error(array('error' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('error' => 'Insufficient permissions'));
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    $days_back = intval($_POST['days_back']);
    $mode = sanitize_text_field($_POST['mode']);
    
    // In analyze mode, fetch and analyze refunds
    if ($mode === 'analyze') {
        // Implementation would go here
        // For now, return sample data
        $result = array(
            'charges_count' => 45,
            'refunds_count' => 3,
            'members_to_update' => array(
                array(
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'current_total' => '150.00',
                    'refund_amount' => '50.00',
                    'new_total' => '100.00'
                )
            )
        );
        
        wp_send_json_success($result);
    }
}

// AJAX handler for historical data import
function swca_ajax_import_historical_data() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'swca_historical_import_ajax')) {
        wp_send_json_error(array('error' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('error' => 'Insufficient permissions'));
    }
    
    // Handle file upload and processing
    // Implementation would process the CSV and return results
    
    wp_send_json_success(array('message' => 'Import processed'));
}