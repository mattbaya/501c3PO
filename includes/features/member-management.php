<?php
/**
 * Member Management Feature Module
 * Full CRUD for members from WordPress admin: list, add, edit, delete, search, filter
 */

defined('ABSPATH') or die('No script kiddies please!');

// Load WP_List_Table if not already available
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Register admin menu
 */
add_action('admin_menu', 'five01c3po_add_member_management_menu', 15);

function five01c3po_add_member_management_menu() {
    add_submenu_page(
        'membership-management',
        'Members',
        '👥 Members',
        'manage_options',
        '501c3PO-members',
        'five01c3po_member_management_page'
    );
}

/**
 * Get the members table name for production
 */
function five01c3po_get_members_table() {
    global $wpdb;
    return $wpdb->prefix . 'c3_members';
}

/**
 * Route to the correct view
 */
function five01c3po_member_management_page() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

    switch ($action) {
        case 'add':
        case 'edit':
            five01c3po_member_form_page();
            break;
        case 'delete':
            five01c3po_member_delete_handler();
            break;
        default:
            five01c3po_member_list_page();
            break;
    }
}

// ============================================================================
// LIST VIEW
// ============================================================================

class Five01c3PO_Members_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'member',
            'plural'   => 'members',
            'ajax'     => false
        ));
    }

    public function get_columns() {
        return array(
            'cb'              => '<input type="checkbox" />',
            'name'            => 'Name',
            'email_1'         => 'Email',
            'phone'           => 'Phone',
            'membership_type' => 'Type',
            'status'          => 'Status',
            'total_amount'    => 'Total Paid',
            'on_email_list'   => 'Email List',
            'updated_at'      => 'Updated',
        );
    }

    public function get_sortable_columns() {
        return array(
            'name'            => array('last_name', true),
            'email_1'         => array('email_1', false),
            'membership_type' => array('membership_type', false),
            'total_amount'    => array('total_amount', false),
            'updated_at'      => array('updated_at', false),
        );
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="member_ids[]" value="%d" />', $item->id);
    }

    public function column_name($item) {
        $edit_url = admin_url('admin.php?page=501c3PO-members&action=edit&id=' . $item->id);
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=501c3PO-members&action=delete&id=' . $item->id),
            'delete_member_' . $item->id
        );

        $name = esc_html($item->first_name . ' ' . $item->last_name);
        if (!empty($item->partner_first_name)) {
            $name .= '<br><small style="color:#666;">&amp; ' . esc_html($item->partner_first_name . ' ' . $item->partner_last_name) . '</small>';
        }

        $actions = array(
            'edit'   => sprintf('<a href="%s">Edit</a>', $edit_url),
            'delete' => sprintf('<a href="%s" style="color:#b32d2e;" onclick="return confirm(\'Delete this member?\')">Delete</a>', $delete_url),
        );

        return $name . $this->row_actions($actions);
    }

    public function column_email_1($item) {
        return !empty($item->email_1) ? '<a href="mailto:' . esc_attr($item->email_1) . '">' . esc_html($item->email_1) . '</a>' : '—';
    }

    public function column_phone($item) {
        return !empty($item->phone) ? esc_html($item->phone) : '—';
    }

    public function column_membership_type($item) {
        return !empty($item->membership_type) ? esc_html($item->membership_type) : '—';
    }

    public function column_status($item) {
        $status = !empty($item->status_2024_2025) ? $item->status_2024_2025 : 'Unknown';
        $color = (strtolower($status) === 'paid') ? '#00a32a' : '#d63638';
        return sprintf('<span style="color:%s;font-weight:600;">%s</span>', $color, esc_html(ucfirst($status)));
    }

    public function column_total_amount($item) {
        return '$' . number_format((float)$item->total_amount, 2);
    }

    public function column_on_email_list($item) {
        return $item->on_swca_email_list ? '✓' : '✗';
    }

    public function column_updated_at($item) {
        return !empty($item->updated_at) ? date('M j, Y', strtotime($item->updated_at)) : '—';
    }

    public function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
    }

    public function get_bulk_actions() {
        return array(
            'bulk_delete' => 'Delete',
        );
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') return;

        $current_type = isset($_GET['membership_type']) ? sanitize_text_field($_GET['membership_type']) : '';
        $current_status = isset($_GET['member_status']) ? sanitize_text_field($_GET['member_status']) : '';
        $current_email_list = isset($_GET['email_list']) ? sanitize_text_field($_GET['email_list']) : '';

        ?>
        <div class="alignleft actions">
            <select name="membership_type">
                <option value="">All Types</option>
                <option value="Individual" <?php selected($current_type, 'Individual'); ?>>Individual</option>
                <option value="Family" <?php selected($current_type, 'Family'); ?>>Family</option>
            </select>

            <select name="member_status">
                <option value="">All Statuses</option>
                <option value="Paid" <?php selected($current_status, 'Paid'); ?>>Paid</option>
                <option value="Unpaid" <?php selected($current_status, 'Unpaid'); ?>>Unpaid</option>
            </select>

            <select name="email_list">
                <option value="">Email List: All</option>
                <option value="1" <?php selected($current_email_list, '1'); ?>>On Email List</option>
                <option value="0" <?php selected($current_email_list, '0'); ?>>Not on Email List</option>
            </select>

            <?php submit_button('Filter', 'secondary', 'filter_action', false); ?>
        </div>
        <?php
    }

    public function prepare_items() {
        global $wpdb;
        $table = five01c3po_get_members_table();

        $per_page = 25;
        $current_page = $this->get_pagenum();

        // Build WHERE clauses
        $where = array('1=1');
        $values = array();

        // Search
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(first_name LIKE %s OR last_name LIKE %s OR email_1 LIKE %s OR phone LIKE %s OR CONCAT(first_name, " ", last_name) LIKE %s)';
            $values = array_merge($values, array($like, $like, $like, $like, $like));
        }

        // Filters
        if (!empty($_GET['membership_type'])) {
            $where[] = 'membership_type = %s';
            $values[] = sanitize_text_field($_GET['membership_type']);
        }
        if (!empty($_GET['member_status'])) {
            $where[] = 'status_2024_2025 = %s';
            $values[] = sanitize_text_field($_GET['member_status']);
        }
        if (isset($_GET['email_list']) && $_GET['email_list'] !== '') {
            $where[] = 'on_swca_email_list = %d';
            $values[] = intval($_GET['email_list']);
        }

        $where_sql = implode(' AND ', $where);

        // Count total items
        $count_query = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        if (!empty($values)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $values));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }

        // Sorting
        $allowed_orderby = array('last_name', 'email_1', 'membership_type', 'total_amount', 'updated_at');
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby) ? $_GET['orderby'] : 'last_name';
        $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), array('ASC', 'DESC')) ? strtoupper($_GET['order']) : 'ASC';

        // Query
        $offset = ($current_page - 1) * $per_page;
        $query = "SELECT * FROM $table WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $query_values = array_merge($values, array($per_page, $offset));

        $this->items = $wpdb->get_results($wpdb->prepare($query, $query_values));

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ));

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
    }
}

function five01c3po_member_list_page() {
    global $wpdb;
    $table = five01c3po_get_members_table();

    // Handle bulk delete
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && !empty($_POST['member_ids'])) {
        check_admin_referer('bulk-members');
        $ids = array_map('intval', $_POST['member_ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", $ids));
        echo '<div class="notice notice-success"><p>' . count($ids) . ' member(s) deleted.</p></div>';
    }

    // Summary stats
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $paid = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status_2024_2025 = 'Paid'");
    $unpaid = $total - $paid;
    $on_email = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE on_swca_email_list = 1");

    $list_table = new Five01c3PO_Members_List_Table();
    $list_table->prepare_items();

    $add_url = admin_url('admin.php?page=501c3PO-members&action=add');
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">👥 Members</h1>
        <a href="<?php echo esc_url($add_url); ?>" class="page-title-action">Add New Member</a>
        <hr class="wp-header-end">

        <div style="display:flex;gap:15px;margin:15px 0;">
            <div class="card" style="margin:0;padding:10px 15px;flex:1;">
                <strong style="font-size:24px;"><?php echo $total; ?></strong><br>Total Members
            </div>
            <div class="card" style="margin:0;padding:10px 15px;flex:1;">
                <strong style="font-size:24px;color:#00a32a;"><?php echo $paid; ?></strong><br>Paid
            </div>
            <div class="card" style="margin:0;padding:10px 15px;flex:1;">
                <strong style="font-size:24px;color:#d63638;"><?php echo $unpaid; ?></strong><br>Unpaid
            </div>
            <div class="card" style="margin:0;padding:10px 15px;flex:1;">
                <strong style="font-size:24px;color:#2271b1;"><?php echo $on_email; ?></strong><br>On Email List
            </div>
        </div>

        <form method="get">
            <input type="hidden" name="page" value="501c3PO-members" />
            <?php
            $list_table->search_box('Search Members', 'member-search');
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}

// ============================================================================
// ADD / EDIT FORM
// ============================================================================

function five01c3po_member_form_page() {
    global $wpdb;
    $table = five01c3po_get_members_table();

    $member = null;
    $is_edit = false;

    // Load existing member for edit
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$member) {
            wp_die('Member not found.');
        }
        $is_edit = true;
    }

    // Handle form submission
    if (isset($_POST['save_member'])) {
        check_admin_referer('five01c3po_save_member');
        $result = five01c3po_save_member($_POST, $is_edit ? $member->id : null);
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            // Save mailing list subscriptions if the feature is available
            $member_id = is_numeric($result) ? intval($result) : ($is_edit ? $member->id : $wpdb->insert_id);
            if (function_exists('five01c3po_save_member_lists')) {
                $selected_lists = isset($_POST['member_lists']) ? array_map('intval', $_POST['member_lists']) : array();
                five01c3po_save_member_lists($member_id, $selected_lists);
            }
            $redirect = admin_url('admin.php?page=501c3PO-members&msg=' . ($is_edit ? 'updated' : 'added'));
            wp_redirect($redirect);
            exit;
        }
    }

    // Show success messages from redirect
    if (isset($_GET['msg'])) {
        $msg = $_GET['msg'] === 'updated' ? 'Member updated successfully.' : 'Member added successfully.';
        echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
    }

    $back_url = admin_url('admin.php?page=501c3PO-members');
    ?>
    <div class="wrap">
        <h1><?php echo $is_edit ? '✏️ Edit Member' : '➕ Add New Member'; ?></h1>
        <p><a href="<?php echo esc_url($back_url); ?>">&larr; Back to Members</a></p>

        <form method="post" style="max-width:800px;">
            <?php wp_nonce_field('five01c3po_save_member'); ?>

            <h2>Personal Information</h2>
            <table class="form-table">
                <tr>
                    <th><label for="first_name">First Name *</label></th>
                    <td><input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($member->first_name ?? ''); ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="last_name">Last Name *</label></th>
                    <td><input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($member->last_name ?? ''); ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="partner_first_name">Partner First Name</label></th>
                    <td><input type="text" id="partner_first_name" name="partner_first_name" value="<?php echo esc_attr($member->partner_first_name ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="partner_last_name">Partner Last Name</label></th>
                    <td><input type="text" id="partner_last_name" name="partner_last_name" value="<?php echo esc_attr($member->partner_last_name ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="family_members">Family Members</label></th>
                    <td><textarea id="family_members" name="family_members" class="large-text" rows="2"><?php echo esc_textarea($member->family_members ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="business_affiliation">Business Affiliation</label></th>
                    <td><input type="text" id="business_affiliation" name="business_affiliation" value="<?php echo esc_attr($member->business_affiliation ?? ''); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h2>Contact Information</h2>
            <table class="form-table">
                <tr>
                    <th><label for="email_1">Primary Email</label></th>
                    <td><input type="email" id="email_1" name="email_1" value="<?php echo esc_attr($member->email_1 ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="email_2">Email 2</label></th>
                    <td><input type="email" id="email_2" name="email_2" value="<?php echo esc_attr($member->email_2 ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="email_3">Email 3</label></th>
                    <td><input type="email" id="email_3" name="email_3" value="<?php echo esc_attr($member->email_3 ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="email_4">Email 4</label></th>
                    <td><input type="email" id="email_4" name="email_4" value="<?php echo esc_attr($member->email_4 ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="phone">Phone</label></th>
                    <td><input type="text" id="phone" name="phone" value="<?php echo esc_attr($member->phone ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="alternate_phone">Alternate Phone</label></th>
                    <td><input type="text" id="alternate_phone" name="alternate_phone" value="<?php echo esc_attr($member->alternate_phone ?? ''); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h2>Address</h2>
            <table class="form-table">
                <tr>
                    <th><label for="address">Address</label></th>
                    <td><input type="text" id="address" name="address" value="<?php echo esc_attr($member->address ?? ''); ?>" class="large-text" /></td>
                </tr>
                <tr>
                    <th><label for="city">City</label></th>
                    <td><input type="text" id="city" name="city" value="<?php echo esc_attr($member->city ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="state">State</label></th>
                    <td><input type="text" id="state" name="state" value="<?php echo esc_attr($member->state ?? ''); ?>" style="width:60px;" maxlength="10" /></td>
                </tr>
                <tr>
                    <th><label for="zip_code">Zip Code</label></th>
                    <td><input type="text" id="zip_code" name="zip_code" value="<?php echo esc_attr($member->zip_code ?? ''); ?>" style="width:100px;" maxlength="10" /></td>
                </tr>
                <tr>
                    <th><label for="alternate_address">Alternate Address</label></th>
                    <td><textarea id="alternate_address" name="alternate_address" class="large-text" rows="2"><?php echo esc_textarea($member->alternate_address ?? ''); ?></textarea></td>
                </tr>
            </table>

            <h2>Membership</h2>
            <table class="form-table">
                <tr>
                    <th><label for="membership_type">Membership Type</label></th>
                    <td>
                        <select id="membership_type" name="membership_type">
                            <option value="">— Select —</option>
                            <?php
                            $types = array('Individual', 'Family');
                            $current_type = $member->membership_type ?? '';
                            foreach ($types as $type) {
                                printf('<option value="%s" %s>%s</option>', esc_attr($type), selected($current_type, $type, false), esc_html($type));
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="status_2024_2025">Status 2024-2025</label></th>
                    <td>
                        <select id="status_2024_2025" name="status_2024_2025">
                            <option value="Unpaid" <?php selected($member->status_2024_2025 ?? '', 'Unpaid'); ?>>Unpaid</option>
                            <option value="Paid" <?php selected($member->status_2024_2025 ?? '', 'Paid'); ?>>Paid</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="status_2023_2024">Status 2023-2024</label></th>
                    <td>
                        <select id="status_2023_2024" name="status_2023_2024">
                            <option value="" <?php selected($member->status_2023_2024 ?? '', ''); ?>>—</option>
                            <option value="Unpaid" <?php selected($member->status_2023_2024 ?? '', 'Unpaid'); ?>>Unpaid</option>
                            <option value="Paid" <?php selected($member->status_2023_2024 ?? '', 'Paid'); ?>>Paid</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="payment_type">Payment Type</label></th>
                    <td>
                        <select id="payment_type" name="payment_type">
                            <option value="">— Select —</option>
                            <?php
                            $pay_types = array('Stripe', 'Check', 'Cash', 'Other');
                            $current_pay = $member->payment_type ?? '';
                            foreach ($pay_types as $pt) {
                                printf('<option value="%s" %s>%s</option>', esc_attr($pt), selected($current_pay, $pt, false), esc_html($pt));
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="membership_amount">Membership Amount ($)</label></th>
                    <td><input type="number" id="membership_amount" name="membership_amount" value="<?php echo esc_attr($member->membership_amount ?? '0.00'); ?>" step="0.01" min="0" style="width:120px;" /></td>
                </tr>
                <tr>
                    <th><label for="donation_amount">Donation Amount ($)</label></th>
                    <td><input type="number" id="donation_amount" name="donation_amount" value="<?php echo esc_attr($member->donation_amount ?? '0.00'); ?>" step="0.01" min="0" style="width:120px;" /></td>
                </tr>
                <tr>
                    <th>Total Amount</th>
                    <td><strong id="total_display">$<?php echo number_format(($member->total_amount ?? 0), 2); ?></strong>
                        <p class="description">Calculated automatically from membership + donation</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="membership_month">Membership Month</label></th>
                    <td><input type="text" id="membership_month" name="membership_month" value="<?php echo esc_attr($member->membership_month ?? ''); ?>" class="regular-text" placeholder="e.g. September 2024" /></td>
                </tr>
            </table>

            <h2>Organization</h2>
            <table class="form-table">
                <tr>
                    <th><label for="on_swca_email_list">On Email List</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="on_swca_email_list" name="on_swca_email_list" value="1" <?php checked($member->on_swca_email_list ?? 1, 1); ?> />
                            Include this member on the organization email list
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="categories">Categories</label></th>
                    <td><input type="text" id="categories" name="categories" value="<?php echo esc_attr($member->categories ?? ''); ?>" class="large-text" />
                        <p class="description">Comma-separated categories</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="tags">Tags</label></th>
                    <td><input type="text" id="tags" name="tags" value="<?php echo esc_attr($member->tags ?? ''); ?>" class="large-text" />
                        <p class="description">Comma-separated tags</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="notes">Notes</label></th>
                    <td><textarea id="notes" name="notes" class="large-text" rows="4"><?php echo esc_textarea($member->notes ?? ''); ?></textarea></td>
                </tr>
            </table>

            <?php
            // Mailing list checkboxes (only if mailing-lists feature is loaded)
            if (function_exists('five01c3po_get_member_lists')) {
                $member_id = $is_edit ? $member->id : 0;
                $all_lists = five01c3po_get_member_lists($member_id);
                if (!empty($all_lists)):
            ?>
            <h2>Mailing Lists</h2>
            <table class="form-table">
                <tr>
                    <th>List Subscriptions</th>
                    <td>
                        <?php foreach ($all_lists as $ml): ?>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="member_lists[]" value="<?php echo intval($ml->id); ?>"
                                    <?php checked(!empty($ml->subscription_status) && $ml->subscription_status === 'active'); ?> />
                                <?php echo esc_html($ml->name); ?>
                                <?php if ($ml->description): ?>
                                    <span style="color:#666;font-size:12px;">&mdash; <?php echo esc_html($ml->description); ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">Select which mailing lists this member belongs to</p>
                    </td>
                </tr>
            </table>
            <?php
                endif;
            }
            ?>

            <?php submit_button($is_edit ? 'Update Member' : 'Add Member', 'primary', 'save_member'); ?>
        </form>
    </div>

    <script>
    (function() {
        var membershipEl = document.getElementById('membership_amount');
        var donationEl = document.getElementById('donation_amount');
        var totalEl = document.getElementById('total_display');
        function updateTotal() {
            var m = parseFloat(membershipEl.value) || 0;
            var d = parseFloat(donationEl.value) || 0;
            totalEl.textContent = '$' + (m + d).toFixed(2);
        }
        membershipEl.addEventListener('input', updateTotal);
        donationEl.addEventListener('input', updateTotal);
    })();
    </script>
    <?php
}

// ============================================================================
// SAVE MEMBER
// ============================================================================

function five01c3po_save_member($data, $id = null) {
    global $wpdb;
    $table = five01c3po_get_members_table();

    $first_name = sanitize_text_field($data['first_name'] ?? '');
    $last_name = sanitize_text_field($data['last_name'] ?? '');

    if (empty($first_name) || empty($last_name)) {
        return new WP_Error('missing_name', 'First name and last name are required.');
    }

    $membership_amount = floatval($data['membership_amount'] ?? 0);
    $donation_amount = floatval($data['donation_amount'] ?? 0);

    $row = array(
        'first_name'           => $first_name,
        'last_name'            => $last_name,
        'partner_first_name'   => sanitize_text_field($data['partner_first_name'] ?? ''),
        'partner_last_name'    => sanitize_text_field($data['partner_last_name'] ?? ''),
        'family_members'       => sanitize_textarea_field($data['family_members'] ?? ''),
        'business_affiliation' => sanitize_text_field($data['business_affiliation'] ?? ''),
        'email_1'              => sanitize_email($data['email_1'] ?? ''),
        'email_2'              => sanitize_email($data['email_2'] ?? ''),
        'email_3'              => sanitize_email($data['email_3'] ?? ''),
        'email_4'              => sanitize_email($data['email_4'] ?? ''),
        'phone'                => sanitize_text_field($data['phone'] ?? ''),
        'alternate_phone'      => sanitize_text_field($data['alternate_phone'] ?? ''),
        'address'              => sanitize_text_field($data['address'] ?? ''),
        'city'                 => sanitize_text_field($data['city'] ?? ''),
        'state'                => sanitize_text_field($data['state'] ?? ''),
        'zip_code'             => sanitize_text_field($data['zip_code'] ?? ''),
        'alternate_address'    => sanitize_textarea_field($data['alternate_address'] ?? ''),
        'membership_type'      => sanitize_text_field($data['membership_type'] ?? ''),
        'status_2024_2025'     => sanitize_text_field($data['status_2024_2025'] ?? 'Unpaid'),
        'status_2023_2024'     => sanitize_text_field($data['status_2023_2024'] ?? ''),
        'payment_type'         => sanitize_text_field($data['payment_type'] ?? ''),
        'membership_amount'    => $membership_amount,
        'donation_amount'      => $donation_amount,
        'total_amount'         => $membership_amount + $donation_amount,
        'membership_month'     => sanitize_text_field($data['membership_month'] ?? ''),
        'on_swca_email_list'   => isset($data['on_swca_email_list']) ? 1 : 0,
        'categories'           => sanitize_text_field($data['categories'] ?? ''),
        'tags'                 => sanitize_text_field($data['tags'] ?? ''),
        'notes'                => sanitize_textarea_field($data['notes'] ?? ''),
    );

    $formats = array(
        '%s', '%s', '%s', '%s', '%s', '%s',       // names, family, business
        '%s', '%s', '%s', '%s',                     // emails
        '%s', '%s',                                  // phones
        '%s', '%s', '%s', '%s', '%s',               // address
        '%s', '%s', '%s', '%s',                     // membership status/type/payment
        '%f', '%f', '%f',                            // amounts
        '%s',                                        // membership_month
        '%d',                                        // on_email_list
        '%s', '%s', '%s',                           // categories, tags, notes
    );

    if ($id) {
        $result = $wpdb->update($table, $row, array('id' => $id), $formats, array('%d'));
    } else {
        $result = $wpdb->insert($table, $row, $formats);
    }

    if ($result === false) {
        return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
    }

    return $id ? $id : $wpdb->insert_id;
}

// ============================================================================
// DELETE MEMBER
// ============================================================================

function five01c3po_member_delete_handler() {
    global $wpdb;
    $table = five01c3po_get_members_table();

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        wp_die('Invalid member ID.');
    }

    check_admin_referer('delete_member_' . $id);

    $member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM $table WHERE id = %d", $id));
    if (!$member) {
        wp_die('Member not found.');
    }

    $wpdb->delete($table, array('id' => $id), array('%d'));

    wp_redirect(admin_url('admin.php?page=501c3PO-members&msg=deleted&name=' . urlencode($member->first_name . ' ' . $member->last_name)));
    exit;
}
