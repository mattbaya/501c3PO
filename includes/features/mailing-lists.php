<?php
/**
 * Mailing List Management Feature Module
 * Named mailing lists with member assignment, independent of any 3rd party email plugin
 */

defined('ABSPATH') or die('No script kiddies please!');

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// ============================================================================
// TABLE HELPERS
// ============================================================================

function five01c3po_get_lists_table() {
    global $wpdb;
    return $wpdb->prefix . 'c3_mailing_lists';
}

function five01c3po_get_list_members_table() {
    global $wpdb;
    return $wpdb->prefix . 'c3_mailing_list_members';
}

// ============================================================================
// ADMIN MENU
// ============================================================================

add_action('admin_menu', 'five01c3po_add_mailing_lists_menu', 16);

function five01c3po_add_mailing_lists_menu() {
    add_submenu_page(
        'membership-management',
        'Mailing Lists',
        '📬 Mailing Lists',
        'manage_options',
        '501c3PO-mailing-lists',
        'five01c3po_mailing_lists_page'
    );
}

// ============================================================================
// PAGE ROUTER
// ============================================================================

function five01c3po_mailing_lists_page() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

    switch ($action) {
        case 'add':
        case 'edit':
            five01c3po_mailing_list_form_page();
            break;
        case 'delete':
            five01c3po_mailing_list_delete_handler();
            break;
        case 'view':
            five01c3po_mailing_list_view_page();
            break;
        case 'add-members':
            five01c3po_mailing_list_add_members_page();
            break;
        default:
            five01c3po_mailing_lists_list_page();
            break;
    }
}

// ============================================================================
// SEED DEFAULT LISTS (called on admin_init, runs once)
// ============================================================================

add_action('admin_init', 'five01c3po_seed_mailing_lists');

function five01c3po_seed_mailing_lists() {
    if (get_option('five01c3po_mailing_lists_seeded')) {
        return;
    }

    global $wpdb;
    $lists_table = five01c3po_get_lists_table();
    $list_members_table = five01c3po_get_list_members_table();
    $members_table = $wpdb->prefix . 'c3_members';

    // Check if tables exist
    if ($wpdb->get_var("SHOW TABLES LIKE '$lists_table'") !== $lists_table) {
        // Tables not created yet, try creating them
        if (function_exists('five01c3po_create_tables')) {
            five01c3po_create_tables();
        }
        // Recheck
        if ($wpdb->get_var("SHOW TABLES LIKE '$lists_table'") !== $lists_table) {
            return; // Still not there, bail
        }
    }

    // Only seed if no lists exist yet
    $existing = $wpdb->get_var("SELECT COUNT(*) FROM $lists_table");
    if ($existing > 0) {
        update_option('five01c3po_mailing_lists_seeded', true);
        return;
    }

    // Create default lists
    $defaults = array(
        array('name' => 'All Members',    'slug' => 'all-members',    'description' => 'All current members who have opted into the email list', 'is_default' => 1),
        array('name' => 'Board',          'slug' => 'board',          'description' => 'Board of directors and officers', 'is_default' => 0),
        array('name' => 'Press Release',  'slug' => 'press-release',  'description' => 'Press release distribution list', 'is_default' => 0),
    );

    foreach ($defaults as $list) {
        $wpdb->insert($lists_table, $list, array('%s', '%s', '%s', '%d'));
    }

    // Migrate on_swca_email_list data: members with on_swca_email_list=1 go into "All Members"
    $all_members_list_id = $wpdb->get_var("SELECT id FROM $lists_table WHERE slug = 'all-members'");
    if ($all_members_list_id) {
        $opted_in = $wpdb->get_results("SELECT id FROM $members_table WHERE on_swca_email_list = 1");
        foreach ($opted_in as $member) {
            $wpdb->insert($list_members_table, array(
                'list_id'   => $all_members_list_id,
                'member_id' => $member->id,
                'status'    => 'active',
            ), array('%d', '%d', '%s'));
        }

        // Update cached count
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $list_members_table WHERE list_id = %d AND status = 'active'",
            $all_members_list_id
        ));
        $wpdb->update($lists_table, array('member_count' => $count), array('id' => $all_members_list_id), array('%d'), array('%d'));
    }

    update_option('five01c3po_mailing_lists_seeded', true);
}

// ============================================================================
// HELPER: Recalculate list member count
// ============================================================================

function five01c3po_update_list_count($list_id) {
    global $wpdb;
    $lists_table = five01c3po_get_lists_table();
    $list_members_table = five01c3po_get_list_members_table();

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $list_members_table WHERE list_id = %d AND status = 'active'",
        $list_id
    ));
    $wpdb->update($lists_table, array('member_count' => $count), array('id' => $list_id), array('%d'), array('%d'));
}

// ============================================================================
// HELPER: Get lists for a member
// ============================================================================

function five01c3po_get_member_lists($member_id) {
    global $wpdb;
    $lists_table = five01c3po_get_lists_table();
    $list_members_table = five01c3po_get_list_members_table();

    return $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, lm.status as subscription_status, lm.subscribed_at
         FROM $lists_table l
         LEFT JOIN $list_members_table lm ON l.id = lm.list_id AND lm.member_id = %d
         ORDER BY l.name",
        $member_id
    ));
}

// ============================================================================
// HELPER: Set member list subscriptions from form checkbox array
// ============================================================================

function five01c3po_save_member_lists($member_id, $selected_list_ids = array()) {
    global $wpdb;
    $lists_table = five01c3po_get_lists_table();
    $list_members_table = five01c3po_get_list_members_table();

    // Get all list IDs
    $all_lists = $wpdb->get_col("SELECT id FROM $lists_table");

    foreach ($all_lists as $list_id) {
        $exists = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM $list_members_table WHERE list_id = %d AND member_id = %d",
            $list_id, $member_id
        ));

        if (in_array($list_id, $selected_list_ids)) {
            // Should be subscribed
            if ($exists) {
                if ($exists->status !== 'active') {
                    $wpdb->update($list_members_table,
                        array('status' => 'active', 'unsubscribed_at' => null),
                        array('id' => $exists->id),
                        array('%s', '%s'), array('%d')
                    );
                }
            } else {
                $wpdb->insert($list_members_table, array(
                    'list_id'   => $list_id,
                    'member_id' => $member_id,
                    'status'    => 'active',
                ), array('%d', '%d', '%s'));
            }
        } else {
            // Should not be subscribed - remove if exists and active
            if ($exists && $exists->status === 'active') {
                $wpdb->delete($list_members_table, array('id' => $exists->id), array('%d'));
            }
        }

        five01c3po_update_list_count($list_id);
    }
}

// ============================================================================
// LIST OF LISTS (main page)
// ============================================================================

function five01c3po_mailing_lists_list_page() {
    global $wpdb;
    $lists_table = five01c3po_get_lists_table();
    $list_members_table = five01c3po_get_list_members_table();

    // Handle success messages
    if (isset($_GET['msg'])) {
        $messages = array(
            'added'   => 'Mailing list created.',
            'updated' => 'Mailing list updated.',
            'deleted' => 'Mailing list deleted.',
            'members_added' => 'Members added to list.',
            'members_removed' => 'Members removed from list.',
        );
        $msg = $messages[$_GET['msg']] ?? '';
        if ($msg) {
            echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
        }
    }

    $lists = $wpdb->get_results("SELECT * FROM $lists_table ORDER BY is_default DESC, name ASC");

    $add_url = admin_url('admin.php?page=501c3PO-mailing-lists&action=add');
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">📬 Mailing Lists</h1>
        <a href="<?php echo esc_url($add_url); ?>" class="page-title-action">Add New List</a>
        <hr class="wp-header-end">

        <table class="wp-list-table widefat fixed striped" style="max-width:900px;margin-top:15px;">
            <thead>
                <tr>
                    <th style="width:30%;">List Name</th>
                    <th style="width:35%;">Description</th>
                    <th style="width:15%;text-align:center;">Members</th>
                    <th style="width:20%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lists)): ?>
                    <tr><td colspan="4">No mailing lists found. <a href="<?php echo esc_url($add_url); ?>">Create one</a>.</td></tr>
                <?php else: ?>
                    <?php foreach ($lists as $list): ?>
                        <?php
                        $view_url = admin_url('admin.php?page=501c3PO-mailing-lists&action=view&id=' . $list->id);
                        $edit_url = admin_url('admin.php?page=501c3PO-mailing-lists&action=edit&id=' . $list->id);
                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=501c3PO-mailing-lists&action=delete&id=' . $list->id),
                            'delete_list_' . $list->id
                        );
                        $add_members_url = admin_url('admin.php?page=501c3PO-mailing-lists&action=add-members&id=' . $list->id);
                        ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo esc_url($view_url); ?>"><?php echo esc_html($list->name); ?></a></strong>
                                <?php if ($list->is_default): ?>
                                    <span style="background:#2271b1;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;margin-left:5px;">Default</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($list->description); ?></td>
                            <td style="text-align:center;font-size:16px;font-weight:600;"><?php echo intval($list->member_count); ?></td>
                            <td>
                                <a href="<?php echo esc_url($view_url); ?>">View</a> |
                                <a href="<?php echo esc_url($edit_url); ?>">Edit</a> |
                                <a href="<?php echo esc_url($add_members_url); ?>">Add Members</a>
                                <?php if (!$list->is_default): ?>
                                    | <a href="<?php echo esc_url($delete_url); ?>" style="color:#b32d2e;" onclick="return confirm('Delete this list? Members will NOT be deleted.')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ============================================================================
// ADD / EDIT LIST FORM
// ============================================================================

function five01c3po_mailing_list_form_page() {
    global $wpdb;
    $lists_table = five01c3po_get_lists_table();

    $list = null;
    $is_edit = false;

    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $list = $wpdb->get_row($wpdb->prepare("SELECT * FROM $lists_table WHERE id = %d", $id));
        if (!$list) {
            wp_die('List not found.');
        }
        $is_edit = true;
    }

    // Handle save
    if (isset($_POST['save_list'])) {
        check_admin_referer('five01c3po_save_list');

        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (empty($name)) {
            echo '<div class="notice notice-error"><p>List name is required.</p></div>';
        } else {
            $slug = sanitize_title($name);

            // Check for duplicate slug (excluding current list on edit)
            $dup_query = $wpdb->prepare("SELECT id FROM $lists_table WHERE slug = %s", $slug);
            if ($is_edit) {
                $dup_query .= $wpdb->prepare(" AND id != %d", $list->id);
            }
            $duplicate = $wpdb->get_var($dup_query);

            if ($duplicate) {
                echo '<div class="notice notice-error"><p>A list with this name already exists.</p></div>';
            } else {
                $data = array(
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description,
                );
                $formats = array('%s', '%s', '%s');

                if ($is_edit) {
                    $wpdb->update($lists_table, $data, array('id' => $list->id), $formats, array('%d'));
                    $msg = 'updated';
                } else {
                    $wpdb->insert($lists_table, $data, $formats);
                    $msg = 'added';
                }

                wp_redirect(admin_url('admin.php?page=501c3PO-mailing-lists&msg=' . $msg));
                exit;
            }
        }
    }

    $back_url = admin_url('admin.php?page=501c3PO-mailing-lists');
    ?>
    <div class="wrap">
        <h1><?php echo $is_edit ? '✏️ Edit Mailing List' : '➕ New Mailing List'; ?></h1>
        <p><a href="<?php echo esc_url($back_url); ?>">&larr; Back to Mailing Lists</a></p>

        <form method="post" style="max-width:600px;">
            <?php wp_nonce_field('five01c3po_save_list'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="name">List Name *</label></th>
                    <td><input type="text" id="name" name="name" value="<?php echo esc_attr($list->name ?? ''); ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="description">Description</label></th>
                    <td><textarea id="description" name="description" class="large-text" rows="3"><?php echo esc_textarea($list->description ?? ''); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button($is_edit ? 'Update List' : 'Create List', 'primary', 'save_list'); ?>
        </form>
    </div>
    <?php
}

// ============================================================================
// DELETE LIST
// ============================================================================

function five01c3po_mailing_list_delete_handler() {
    global $wpdb;
    $lists_table = five01c3po_get_lists_table();
    $list_members_table = five01c3po_get_list_members_table();

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        wp_die('Invalid list ID.');
    }

    check_admin_referer('delete_list_' . $id);

    $list = $wpdb->get_row($wpdb->prepare("SELECT * FROM $lists_table WHERE id = %d", $id));
    if (!$list) {
        wp_die('List not found.');
    }
    if ($list->is_default) {
        wp_die('Cannot delete the default list.');
    }

    // Remove all memberships for this list
    $wpdb->delete($list_members_table, array('list_id' => $id), array('%d'));
    // Delete the list
    $wpdb->delete($lists_table, array('id' => $id), array('%d'));

    wp_redirect(admin_url('admin.php?page=501c3PO-mailing-lists&msg=deleted'));
    exit;
}

// ============================================================================
// VIEW LIST (shows members on this list with remove capability)
// ============================================================================

function five01c3po_mailing_list_view_page() {
    global $wpdb;
    $lists_table = five01c3po_get_lists_table();
    $list_members_table = five01c3po_get_list_members_table();
    $members_table = $wpdb->prefix . 'c3_members';

    $id = intval($_GET['id'] ?? 0);
    $list = $wpdb->get_row($wpdb->prepare("SELECT * FROM $lists_table WHERE id = %d", $id));
    if (!$list) {
        wp_die('List not found.');
    }

    // Handle bulk remove
    if (isset($_POST['bulk_remove']) && !empty($_POST['remove_ids'])) {
        check_admin_referer('five01c3po_list_bulk_action');
        $remove_ids = array_map('intval', $_POST['remove_ids']);
        $placeholders = implode(',', array_fill(0, count($remove_ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $list_members_table WHERE list_id = %d AND member_id IN ($placeholders)",
            array_merge(array($id), $remove_ids)
        ));
        five01c3po_update_list_count($id);
        echo '<div class="notice notice-success"><p>' . count($remove_ids) . ' member(s) removed from list.</p></div>';
        // Refresh list data
        $list = $wpdb->get_row($wpdb->prepare("SELECT * FROM $lists_table WHERE id = %d", $id));
    }

    // Get members on this list
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.first_name, m.last_name, m.email_1, m.membership_type, m.status_2024_2025, lm.subscribed_at
         FROM $list_members_table lm
         JOIN $members_table m ON lm.member_id = m.id
         WHERE lm.list_id = %d AND lm.status = 'active'
         ORDER BY m.last_name, m.first_name",
        $id
    ));

    $back_url = admin_url('admin.php?page=501c3PO-mailing-lists');
    $add_members_url = admin_url('admin.php?page=501c3PO-mailing-lists&action=add-members&id=' . $id);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">📬 <?php echo esc_html($list->name); ?></h1>
        <a href="<?php echo esc_url($add_members_url); ?>" class="page-title-action">Add Members</a>
        <hr class="wp-header-end">

        <p><a href="<?php echo esc_url($back_url); ?>">&larr; Back to Mailing Lists</a></p>

        <?php if ($list->description): ?>
            <p><?php echo esc_html($list->description); ?></p>
        <?php endif; ?>

        <p><strong><?php echo count($members); ?></strong> member(s) on this list.</p>

        <?php if (!empty($members)): ?>
        <form method="post">
            <?php wp_nonce_field('five01c3po_list_bulk_action'); ?>
            <table class="wp-list-table widefat fixed striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all" /></td>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" name="remove_ids[]" value="<?php echo $member->id; ?>" /></th>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=501c3PO-members&action=edit&id=' . $member->id); ?>">
                                    <?php echo esc_html($member->first_name . ' ' . $member->last_name); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($member->email_1); ?></td>
                            <td><?php echo esc_html($member->membership_type); ?></td>
                            <td>
                                <?php
                                $status = $member->status_2024_2025 ?: 'Unknown';
                                $color = (strtolower($status) === 'paid') ? '#00a32a' : '#d63638';
                                printf('<span style="color:%s;font-weight:600;">%s</span>', $color, esc_html(ucfirst($status)));
                                ?>
                            </td>
                            <td><?php echo $member->subscribed_at ? date('M j, Y', strtotime($member->subscribed_at)) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button('Remove Selected from List', 'delete', 'bulk_remove', true, array('onclick' => "return confirm('Remove selected members from this list?')")); ?>
        </form>
        <?php else: ?>
            <p>No members on this list yet. <a href="<?php echo esc_url($add_members_url); ?>">Add members</a>.</p>
        <?php endif; ?>
    </div>

    <script>
    document.getElementById('cb-select-all').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="remove_ids[]"]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = this.checked;
        }
    });
    </script>
    <?php
}

// ============================================================================
// ADD MEMBERS TO LIST (shows members NOT on this list with add capability)
// ============================================================================

function five01c3po_mailing_list_add_members_page() {
    global $wpdb;
    $lists_table = five01c3po_get_lists_table();
    $list_members_table = five01c3po_get_list_members_table();
    $members_table = $wpdb->prefix . 'c3_members';

    $id = intval($_GET['id'] ?? 0);
    $list = $wpdb->get_row($wpdb->prepare("SELECT * FROM $lists_table WHERE id = %d", $id));
    if (!$list) {
        wp_die('List not found.');
    }

    // Handle bulk add
    if (isset($_POST['bulk_add']) && !empty($_POST['add_ids'])) {
        check_admin_referer('five01c3po_list_add_members');
        $add_ids = array_map('intval', $_POST['add_ids']);
        $added = 0;
        foreach ($add_ids as $member_id) {
            // Insert or update (in case they were previously removed)
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $list_members_table WHERE list_id = %d AND member_id = %d",
                $id, $member_id
            ));
            if ($exists) {
                $wpdb->update($list_members_table,
                    array('status' => 'active', 'unsubscribed_at' => null, 'subscribed_at' => current_time('mysql')),
                    array('id' => $exists),
                    array('%s', '%s', '%s'), array('%d')
                );
            } else {
                $wpdb->insert($list_members_table, array(
                    'list_id'   => $id,
                    'member_id' => $member_id,
                    'status'    => 'active',
                ), array('%d', '%d', '%s'));
            }
            $added++;
        }
        five01c3po_update_list_count($id);
        wp_redirect(admin_url('admin.php?page=501c3PO-mailing-lists&action=view&id=' . $id . '&msg=members_added'));
        exit;
    }

    // Search
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Get members NOT already on this list (or inactive)
    $where_search = '';
    $search_values = array($id);
    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_search = "AND (m.first_name LIKE %s OR m.last_name LIKE %s OR m.email_1 LIKE %s OR CONCAT(m.first_name, ' ', m.last_name) LIKE %s)";
        $search_values[] = $like;
        $search_values[] = $like;
        $search_values[] = $like;
        $search_values[] = $like;
    }

    $available = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.first_name, m.last_name, m.email_1, m.membership_type, m.status_2024_2025
         FROM $members_table m
         WHERE m.id NOT IN (
             SELECT member_id FROM $list_members_table WHERE list_id = %d AND status = 'active'
         )
         $where_search
         ORDER BY m.last_name, m.first_name
         LIMIT 200",
        $search_values
    ));

    $back_url = admin_url('admin.php?page=501c3PO-mailing-lists&action=view&id=' . $id);
    ?>
    <div class="wrap">
        <h1>Add Members to "<?php echo esc_html($list->name); ?>"</h1>
        <p><a href="<?php echo esc_url($back_url); ?>">&larr; Back to <?php echo esc_html($list->name); ?></a></p>

        <form method="get" style="margin-bottom:15px;">
            <input type="hidden" name="page" value="501c3PO-mailing-lists" />
            <input type="hidden" name="action" value="add-members" />
            <input type="hidden" name="id" value="<?php echo $id; ?>" />
            <p class="search-box">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by name or email..." />
                <?php submit_button('Search', 'secondary', '', false); ?>
            </p>
        </form>

        <?php if (empty($available)): ?>
            <p><?php echo $search ? 'No matching members found.' : 'All members are already on this list.'; ?></p>
        <?php else: ?>
        <form method="post">
            <?php wp_nonce_field('five01c3po_list_add_members'); ?>
            <p><strong><?php echo count($available); ?></strong> member(s) available to add.</p>
            <table class="wp-list-table widefat fixed striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-add" /></td>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($available as $member): ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" name="add_ids[]" value="<?php echo $member->id; ?>" /></th>
                            <td><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></td>
                            <td><?php echo esc_html($member->email_1); ?></td>
                            <td><?php echo esc_html($member->membership_type); ?></td>
                            <td>
                                <?php
                                $status = $member->status_2024_2025 ?: 'Unknown';
                                $color = (strtolower($status) === 'paid') ? '#00a32a' : '#d63638';
                                printf('<span style="color:%s;font-weight:600;">%s</span>', $color, esc_html(ucfirst($status)));
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button('Add Selected to List', 'primary', 'bulk_add'); ?>
        </form>
        <?php endif; ?>
    </div>

    <script>
    document.getElementById('cb-select-all-add').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="add_ids[]"]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = this.checked;
        }
    });
    </script>
    <?php
}
