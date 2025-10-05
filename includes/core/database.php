<?php
/**
 * Database setup and management for 501c3PO
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Create all plugin tables
 */
function five01c3po_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Members table
    $table_name = $wpdb->prefix . 'members';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        partner_first_name varchar(100),
        partner_last_name varchar(100),
        family_members text,
        email_1 varchar(100),
        email_2 varchar(100),
        email_3 varchar(100),
        email_4 varchar(100),
        phone varchar(20),
        alternate_phone varchar(20),
        address varchar(255),
        city varchar(100),
        state varchar(50),
        zip_code varchar(10),
        alternate_address varchar(255),
        membership_type varchar(50),
        status_current_year varchar(10),
        status_previous_year varchar(10),
        membership_amount decimal(10,2),
        donation_amount decimal(10,2),
        total_amount decimal(10,2),
        payment_type varchar(50),
        business_affiliation varchar(255),
        on_email_list tinyint(1) DEFAULT 1,
        notes longtext,
        categories text,
        tags text,
        membership_month int,
        membership_month_previous int,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_last_name (last_name),
        KEY idx_email (email_1),
        KEY idx_status (status_current_year)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Financial transactions table
    $table_name = $wpdb->prefix . 'financial_transactions';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id mediumint(9),
        transaction_date date NOT NULL,
        transaction_type varchar(50) NOT NULL,
        category varchar(100),
        amount decimal(10,2) NOT NULL,
        stripe_fee decimal(10,2),
        net_amount decimal(10,2),
        payment_method varchar(50),
        stripe_transaction_id varchar(255),
        description text,
        fiscal_year varchar(10),
        created_by int,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_member (member_id),
        KEY idx_date (transaction_date),
        KEY idx_type (transaction_type)
    ) $charset_collate;";
    dbDelta($sql);

    // Bank transactions table
    $table_name = $wpdb->prefix . 'bank_transactions';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        account_number varchar(50),
        post_date date NOT NULL,
        check_number varchar(20),
        description text NOT NULL,
        debit decimal(10,2) DEFAULT 0.00,
        credit decimal(10,2) DEFAULT 0.00,
        status varchar(50),
        balance decimal(10,2) DEFAULT 0.00,
        member_id mediumint(9),
        notes text,
        imported_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_post_date (post_date),
        KEY idx_member (member_id),
        KEY idx_status (status)
    ) $charset_collate;";
    dbDelta($sql);

    // Email campaigns table
    $table_name = $wpdb->prefix . 'emails';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        subject varchar(255) NOT NULL,
        content longtext NOT NULL,
        status varchar(50) DEFAULT 'draft',
        created_by int NOT NULL,
        approved_by int,
        scheduled_date datetime,
        sent_date datetime,
        recipient_count int DEFAULT 0,
        open_count int DEFAULT 0,
        click_count int DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_status (status),
        KEY idx_scheduled (scheduled_date)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Email recipients table
    $table_name = $wpdb->prefix . 'email_recipients';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email_id mediumint(9) NOT NULL,
        member_id mediumint(9) NOT NULL,
        email_address varchar(100) NOT NULL,
        status varchar(50) DEFAULT 'pending',
        opened_at datetime,
        clicked_at datetime,
        bounced_at datetime,
        unsubscribed_at datetime,
        PRIMARY KEY (id),
        KEY idx_email (email_id),
        KEY idx_member (member_id),
        KEY idx_status (status)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Events table
    $table_name = $wpdb->prefix . 'events';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description longtext,
        event_type varchar(50),
        start_date datetime NOT NULL,
        end_date datetime,
        location varchar(255),
        address varchar(255),
        max_attendees int,
        rsvp_deadline datetime,
        allow_guests tinyint(1) DEFAULT 1,
        is_public tinyint(1) DEFAULT 0,
        google_calendar_id varchar(255),
        created_by int NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_date (start_date),
        KEY idx_type (event_type)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Event RSVPs table
    $table_name = $wpdb->prefix . 'event_rsvps';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        event_id mediumint(9) NOT NULL,
        member_id mediumint(9) NOT NULL,
        response varchar(20) DEFAULT 'pending',
        guest_count int DEFAULT 0,
        dietary_restrictions text,
        notes text,
        responded_at datetime,
        PRIMARY KEY (id),
        UNIQUE KEY unique_rsvp (event_id, member_id),
        KEY idx_event (event_id),
        KEY idx_member (member_id)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Volunteer slots table
    $table_name = $wpdb->prefix . 'volunteer_slots';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        event_id mediumint(9),
        title varchar(255) NOT NULL,
        description text,
        start_time datetime NOT NULL,
        end_time datetime NOT NULL,
        slots_needed int DEFAULT 1,
        skills_required text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_event (event_id),
        KEY idx_time (start_time)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Volunteer signups table
    $table_name = $wpdb->prefix . 'volunteer_signups';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        slot_id mediumint(9) NOT NULL,
        member_id mediumint(9) NOT NULL,
        emergency_contact varchar(255),
        emergency_phone varchar(20),
        confirmed tinyint(1) DEFAULT 0,
        showed_up tinyint(1),
        notes text,
        signed_up_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_signup (slot_id, member_id),
        KEY idx_slot (slot_id),
        KEY idx_member (member_id)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Committees table
    $table_name = $wpdb->prefix . 'committees';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        description text,
        chair_member_id mediumint(9),
        budget decimal(10,2),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Committee members table
    $table_name = $wpdb->prefix . 'committee_members';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        committee_id mediumint(9) NOT NULL,
        member_id mediumint(9) NOT NULL,
        role varchar(50) DEFAULT 'member',
        joined_date date,
        PRIMARY KEY (id),
        UNIQUE KEY unique_member (committee_id, member_id),
        KEY idx_committee (committee_id),
        KEY idx_member (member_id)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Agendas table
    $table_name = $wpdb->prefix . 'agendas';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        meeting_date date NOT NULL,
        meeting_type varchar(50),
        title varchar(255),
        content longtext,
        attachments text,
        created_by int NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_date (meeting_date)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Minutes table
    $table_name = $wpdb->prefix . 'minutes';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        agenda_id mediumint(9),
        meeting_date date NOT NULL,
        attendees text,
        content longtext,
        action_items text,
        attachments text,
        status varchar(50) DEFAULT 'draft',
        approved_by int,
        approved_date datetime,
        created_by int NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_agenda (agenda_id),
        KEY idx_date (meeting_date)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Documents table
    $table_name = $wpdb->prefix . 'documents';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        file_path varchar(500),
        file_type varchar(50),
        category varchar(100),
        tags text,
        fiscal_year varchar(10),
        google_drive_id varchar(255),
        uploaded_by int NOT NULL,
        uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_category (category),
        KEY idx_year (fiscal_year)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Settings/API keys table
    $table_name = $wpdb->prefix . 'calendar_settings';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        setting_name varchar(100) NOT NULL,
        setting_value longtext,
        encrypted tinyint(1) DEFAULT 0,
        updated_by int,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_setting (setting_name)
    ) $charset_collate;";
    dbDelta($sql);

    // Stripe transactions table (for complete historical Stripe data)
    $table_name = $wpdb->prefix . 'stripe_transactions';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        stripe_charge_id varchar(255) NOT NULL,
        transaction_type varchar(20) NOT NULL,
        member_id mediumint(9),
        customer_email varchar(255),
        amount decimal(10,2) NOT NULL,
        amount_refunded decimal(10,2) DEFAULT 0.00,
        net_amount decimal(10,2) NOT NULL,
        stripe_fee decimal(10,2) DEFAULT 0.00,
        currency varchar(10) DEFAULT 'usd',
        status varchar(50) NOT NULL,
        description text,
        customer_name varchar(255),
        payment_method varchar(50),
        receipt_url varchar(500),
        stripe_created timestamp NOT NULL,
        synced_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_charge (stripe_charge_id),
        KEY idx_member (member_id),
        KEY idx_email (customer_email),
        KEY idx_date (stripe_created),
        KEY idx_type (transaction_type)
    ) $charset_collate;";
    dbDelta($sql);

    // Transaction matches table (for linking Stripe, Gravity Forms, and Bank transactions)
    $table_name = $wpdb->prefix . 'transaction_matches';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        stripe_transaction_id mediumint(9),
        gravity_form_transaction_id mediumint(9),
        bank_transaction_id mediumint(9),
        match_type varchar(50) NOT NULL,
        match_confidence varchar(20) NOT NULL,
        notes text,
        matched_by int,
        matched_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_stripe (stripe_transaction_id),
        KEY idx_gravity (gravity_form_transaction_id),
        KEY idx_bank (bank_transaction_id),
        KEY idx_confidence (match_confidence)
    ) $charset_collate;";
    dbDelta($sql);
}

// Table creation function is called from main plugin file activation hook