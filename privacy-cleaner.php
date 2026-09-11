<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- 1. INITIALIZATION & SETTINGS ---

add_action( 'admin_menu', 'clean_add_menu' );
function clean_add_menu() {
    add_management_page( 'Info To Clean', 'Privacy Cleaner', 'manage_woocommerce', 'cleaner-settings', 'ui_settings_page' );
}

add_action( 'admin_init', 'clean_register_settings' );
function clean_register_settings() {
    register_setting( 'clean_group', 'clean_statuses' );
    register_setting( 'clean_group', 'clean_interval' );
    register_setting( 'clean_group', 'clean_order_age' );
    
    // Fields
    register_setting( 'clean_group', 'clean_remove_names' );
    register_setting( 'clean_group', 'clean_remove_addresses' );
    register_setting( 'clean_group', 'clean_remove_phone' );
    register_setting( 'clean_group', 'clean_remove_email' );
    register_setting( 'clean_group', 'clean_remove_company' );  
    register_setting( 'clean_group', 'clean_drop_ips' );
    register_setting( 'clean_group', 'clean_remove_notes' );
    
    // Backups
    register_setting( 'clean_group', 'clean_backup_email' );
    register_setting( 'clean_group', 'clean_backup_webhook' );
    register_setting( 'clean_group', 'clean_backup_discord' );
    register_setting( 'clean_group', 'clean_backup_telegram_token' );
    register_setting( 'clean_group', 'clean_backup_telegram_chat' );
    register_setting( 'clean_group', 'clean_backup_zip_password' );
}

add_action( 'admin_footer', 'clean_add_orders_page_shortcut' );
function clean_add_orders_page_shortcut() {
    $screen = get_current_screen();
    if ( $screen && ( $screen->id === 'edit-shop_order' || $screen->id === 'woocommerce_page_wc-orders' ) ) {
        $target_url = esc_url( admin_url( 'tools.php?page=cleaner-settings' ) );
        ?>
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                var headerWrap = document.querySelector('.wrap h1');
                if (headerWrap) {
                    var shortcutBtn = document.createElement('a');
                    shortcutBtn.href = '<?php echo $target_url; ?>';
                    shortcutBtn.className = 'page-title-action';
                    shortcutBtn.style.cssText = 'background: #141414; color: #a855f7; border: 1px solid #a855f7; box-shadow: 0 0 5px rgba(168, 85, 247, 0.3); margin-left:10px; padding: 4px 8px; border-radius: 4px; text-decoration: none;';
                    shortcutBtn.innerHTML = '🛡️ Privacy Scrubber';
                    headerWrap.parentNode.insertBefore(shortcutBtn, headerWrap.nextSibling);
                }
            });
        </script>
        <?php
    }
}

// --- 2. DARK UI SETTINGS DASHBOARD ---

function ui_settings_page() {
    if ( isset( $_POST['clean_manual_trigger'] ) && check_admin_referer( 'clean_run_now_nonce' ) ) {
        clean_purge_engine( true );
        echo '<div class="notice notice-success is-dismissible" style="background: #1a1a1a; border-left-color: #a855f7; color: #fff;"><p><strong>Purge & Backup executed. Check logs/chats for backup status.</strong></p></div>';
    }
    if ( isset( $_POST['clean_manual_backup_trigger'] ) && check_admin_referer( 'clean_run_now_nonce' ) ) {
        clean_purge_engine( false );
        echo '<div class="notice notice-success is-dismissible" style="background: #1a1a1a; border-left-color: #3b82f6; color: #fff;"><p><strong>Backup-Only executed. No data was deleted. Check logs/chats.</strong></p></div>';
    }
    if ( isset( $_POST['clean_test_integrations'] ) && check_admin_referer( 'clean_run_now_nonce' ) ) {
        clean_run_test_integrations();
        echo '<div class="notice notice-success is-dismissible" style="background: #1a1a1a; border-left-color: #10b981; color: #fff;"><p><strong>Test payload dispatched! Check your Email, Google Sheets, Discord, and Telegram.</strong></p></div>';
    }

    $current_statuses = get_option( 'clean_statuses', array( 'completed' ) );
    if ( ! is_array( $current_statuses ) ) { $current_statuses = array(); }
    
    $interval = get_option( 'clean_interval', 'weekly' );
    $order_age = get_option( 'clean_order_age', '30' );
    
    $remove_names = get_option( 'clean_remove_names', 'yes' );
    $remove_addresses = get_option( 'clean_remove_addresses', 'yes' );
    $remove_phone = get_option( 'clean_remove_phone', 'yes' );
    $remove_email = get_option( 'clean_remove_email', 'no' );
    $remove_company = get_option( 'clean_remove_company', 'yes' );
    $remove_notes = get_option( 'clean_remove_notes', 'no' );
    $drop_ips = get_option( 'clean_drop_ips', 'yes' );
    
    $backup_email = get_option( 'clean_backup_email', '' );
    $backup_webhook = get_option( 'clean_backup_webhook', '' );
    $backup_discord = get_option( 'clean_backup_discord', '' );
    $backup_tele_token = get_option( 'clean_backup_telegram_token', '' );
    $backup_tele_chat = get_option( 'clean_backup_telegram_chat', '' );
    $zip_pass = get_option( 'clean_backup_zip_password', '' );
    $wc_statuses = wc_get_order_statuses();
    ?>
    
    <style>
        .clean-dark-wrap { background-color: #121212; color: #e0e0e0; padding: 30px; border-radius: 10px; box-shadow: 0 8px 15px rgba(0,0,0,0.4); max-width: 850px; margin-top: 20px; font-family: sans-serif; }
        .clean-dark-wrap h1 { color: #ffffff; font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .clean-dark-wrap h2 { color: #a855f7; font-weight: 500; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; margin-top: 30px;}
        .clean-dark-wrap .form-table th { color: #b3b3b3; font-weight: 600; }
        .clean-dark-wrap .description { color: #888; font-style: italic; }
        .clean-dark-wrap input[type="text"], .clean-dark-wrap input[type="number"], .clean-dark-wrap input[type="password"], .clean-dark-wrap input[type="email"], .clean-dark-wrap select, .clean-dark-wrap input[type="checkbox"] { background-color: #1e1e1e; color: #fff; border: 1px solid #444; border-radius: 4px; }
        .clean-dark-wrap input[type="text"], .clean-dark-wrap input[type="password"], .clean-dark-wrap input[type="email"] { width: 100%; max-width: 400px; padding: 5px; }
        .clean-dark-wrap input[type="checkbox"]:checked { background-color: #a855f7; border-color: #a855f7; }
        .clean-dark-wrap input[type="checkbox"]:checked::before { filter: invert(1); }
        .clean-btn-primary { background: #a855f7 !important; border-color: #9333ea !important; color: #fff !important; text-shadow: none !important; box-shadow: 0 2px 4px rgba(168, 85, 247, 0.4) !important; padding: 5px 20px !important; border-radius: 5px !important; }
        .clean-btn-secondary { background: #3b82f6 !important; border-color: #2563eb !important; color: #fff !important; text-shadow: none !important; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.4) !important; padding: 5px 20px !important; border-radius: 5px !important; }
        .clean-btn-success { background: #10b981 !important; border-color: #059669 !important; color: #fff !important; text-shadow: none !important; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.4) !important; padding: 5px 20px !important; border-radius: 5px !important; }
        .clean-card-dark { background: #1a1a1a; border: 1px solid #333; border-radius: 8px; padding: 25px; margin-top: 30px; }
        .clean-btn-danger { background: transparent !important; border: 1px solid #ef4444 !important; color: #ef4444 !important; border-radius: 5px !important; }
        .clean-btn-danger:hover { background: #ef4444 !important; color: #fff !important; }
    </style>

    <div class="wrap">
        <div class="clean-dark-wrap">
            <h1>🛡️ Personal Data Cleaner</h1>
            <p style="color:#aaa; margin-top:0;">Automates Personal data removal while retaining encrypted off-site records.</p>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'clean_group' ); ?>
                
                <h2>1. Secure Off-Site Backup</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Email Backup</th>
                        <td>
                            <input type="email" name="clean_backup_email" value="<?php echo esc_attr( $backup_email ); ?>" placeholder="admin@yoursite.com" />
                            <p class="description">Sends the ZIP archive to this email address.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Google Sheets Webhook URL</th>
                        <td>
                            <input type="text" name="clean_backup_webhook" value="<?php echo esc_attr( $backup_webhook ); ?>" placeholder="https://script.google.com/macros/s/.../exec" />
                            <p class="description">Secure POST endpoint. Safely appends rows to a spreadsheet.</p>
                            
                            <details style="margin-top: 12px; background: #1a1a1a; padding: 12px; border-radius: 6px; border: 1px solid #333;">
                                <summary style="cursor: pointer; color: #a855f7; font-weight: 600; outline: none; user-select: none;">👉 How to setup the Google Sheets Webhook</summary>
                                <div style="margin-top: 12px; font-size: 13px; color: #ccc;">
                                    <ol style="margin-left: 20px; margin-bottom: 12px; padding-left: 0;">
                                        <li style="margin-bottom: 4px;">Go to Google Drive and create a new blank Google Sheet.</li>
                                        <li style="margin-bottom: 4px;">In the top menu, click <strong>Extensions &gt; Apps Script</strong>.</li>
                                        <li style="margin-bottom: 4px;">Delete the default code and paste the script below into the editor.</li>
                                        <li style="margin-bottom: 4px;">Click <strong>Deploy &gt; New deployment</strong> in the top right.</li>
                                        <li style="margin-bottom: 4px;">Click the gear icon next to "Select type" and choose <strong>Web app</strong>.</li>
                                        <li style="margin-bottom: 4px;">Set "Execute as" to <strong>Me</strong> and "Who has access" to <strong>Anyone</strong>.</li>
                                        <li style="margin-bottom: 4px;">Click <strong>Deploy</strong>, authorize the permissions, and copy the <strong>Web App URL</strong> into the field above.</li>
                                    </ol>
                                    <textarea readonly style="width: 100%; height: 160px; background: #121212; color: #a855f7; border: 1px solid #444; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 12px; resize: vertical;" onclick="this.select();">
function doPost(e) {
  try {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    var data = JSON.parse(e.postData.contents);
    if (sheet.getLastRow() === 0) {
      sheet.appendRow(["Order ID", "Date", "First Name", "Last Name", "Email", "Phone", "Address", "City", "State", "Zip", "Total"]);
    }
    data.forEach(function(order) {
      sheet.appendRow([order.id, order.date, order.first_name, order.last_name, order.email, order.phone, order.address, order.city, order.state, order.postcode, order.total]);
    });
    return ContentService.createTextOutput(JSON.stringify({"status": "success"})).setMimeType(ContentService.MimeType.JSON);
  } catch(error) {
    return ContentService.createTextOutput(JSON.stringify({"status": "error", "message": error.toString()})).setMimeType(ContentService.MimeType.JSON);
  }
}
                                    </textarea>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Discord Webhook Alerts</th>
                        <td>
                            <p style="color: #ef4444; font-weight: bold; margin-top: 0; margin-bottom: 5px;">⚠️ WARNING: Ensure this webhook points to a strictly PRIVATE channel.</p>
                            <input type="text" name="clean_backup_discord" value="<?php echo esc_attr( $backup_discord ); ?>" placeholder="https://discord.com/api/webhooks/..." />
                            <p class="description">Uploads the ZIP archive directly to your Discord channel.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Telegram Bot Alerts</th>
                        <td>
                            <input type="text" name="clean_backup_telegram_token" value="<?php echo esc_attr( $backup_tele_token ); ?>" placeholder="Bot Token (e.g., 123456:ABCdef...)" style="margin-bottom: 8px;" /><br/>
                            <input type="text" name="clean_backup_telegram_chat" value="<?php echo esc_attr( $backup_tele_chat ); ?>" placeholder="Chat ID (e.g., -1001234567)" />
                            <p class="description">Uploads the ZIP archive directly to a private Telegram chat.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Archive Encryption (AES-256)</th>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="password" id="clean_zip_pass" name="clean_backup_zip_password" value="<?php echo esc_attr( $zip_pass ); ?>" placeholder="Leave blank for no password..." minlength="20" style="width: 100%; max-width: 300px; padding: 5px; background-color: #1e1e1e; color: #fff; border: 1px solid #444; border-radius: 4px;" />
                                <button type="button" onclick="generateSecurePassword()" style="background: #1a1a1a; color: #a855f7; border: 1px solid #a855f7; padding: 4px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; transition: all 0.2s ease;">Generate</button>
                                <button type="button" onclick="toggleZipPassword()" style="background: transparent; border: none; font-size: 18px; cursor: pointer; color: #aaa;" title="Show/Hide Password">👁️</button>
                            </div>
                            <p class="description" style="color: #a855f7; margin-top: 8px;">
                                <strong>Strongly Recommended:</strong> Must be at least <strong>20 characters</strong> long. <br/>
                                <em>⚠️ If you generate a password, ensure you copy and save it to your password manager before hitting Save!</em>
                            </p>

                            <script>
                                function generateSecurePassword() {
                                    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
                                    let password = "";
                                    const array = new Uint32Array(24);
                                    window.crypto.getRandomValues(array);
                                    for (let i = 0; i < array.length; i++) {
                                        password += chars[array[i] % chars.length];
                                    }
                                    const passField = document.getElementById('clean_zip_pass');
                                    passField.value = password;
                                    passField.type = 'text'; 
                                }
                                
                                function toggleZipPassword() {
                                    const passField = document.getElementById('clean_zip_pass');
                                    passField.type = passField.type === 'password' ? 'text' : 'password';
                                }
                            </script>
                        </td>
                    </tr>
                </table>

                <h2>2. Data Cleaning Selection</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Target Data Fields</th>
                        <td>
                            <label style="display:block; margin-bottom: 8px; cursor:pointer;"><input type="checkbox" name="clean_remove_names" value="yes" <?php checked( $remove_names, 'yes' ); ?> /> <strong>Names:</strong> First & Last Names.</label>
                            <label style="display:block; margin-bottom: 8px; cursor:pointer;"><input type="checkbox" name="clean_remove_addresses" value="yes" <?php checked( $remove_addresses, 'yes' ); ?> /> <strong>Addresses:</strong> Street, City, State, & ZIP.</label>
                            <label style="display:block; margin-bottom: 8px; cursor:pointer;"><input type="checkbox" name="clean_remove_phone" value="yes" <?php checked( $remove_phone, 'yes' ); ?> /> <strong>Phone:</strong> Billing phone number.</label>
                            <label style="display:block; margin-bottom: 8px; cursor:pointer;"><input type="checkbox" name="clean_remove_email" value="yes" <?php checked( $remove_email, 'yes' ); ?> /> <strong>Email:</strong> Customer email (Warning: breaks review verification).</label>
                            <label style="display:block; margin-bottom: 8px; cursor:pointer;"><input type="checkbox" name="clean_remove_company" value="yes" <?php checked( $remove_company, 'yes' ); ?> /> <strong>Company:</strong> Business names.</label>
                            <label style="display:block; margin-bottom: 8px; cursor:pointer;"><input type="checkbox" name="clean_remove_notes" value="yes" <?php checked( $remove_notes, 'yes' ); ?> /> <strong>Notes:</strong> Customer and internal order notes.</label>
                            <label style="display:block; margin-bottom: 8px; cursor:pointer;"><input type="checkbox" name="clean_drop_ips" value="yes" <?php checked( $drop_ips, 'yes' ); ?> /> <strong>Network:</strong> Clean IPs & user-agent tags.</label>
                        </td>
                    </tr>
                </table>

                <h2>3. Automation Rules</h2>
                <table class="form-table" role="presentation">
                    <tr <?php echo $interval === 'disabled' ? 'style="opacity:0.4; pointer-events:none;"' : ''; ?>>
                        <th scope="row">Target Order Statuses</th>
                        <td>
                            <fieldset>
                                <?php foreach ( $wc_statuses as $status_key => $status_label ) : 
                                    $clean_key = str_replace( 'wc-', '', $status_key );
                                    $checked = in_array( $clean_key, $current_statuses ) ? 'checked="checked"' : '';
                                ?>
                                    <label style="display:block; margin-bottom: 8px; cursor:pointer;">
                                        <input type="checkbox" name="clean_statuses[]" value="<?php echo esc_attr( $clean_key ); ?>" <?php echo $checked; ?> /> <?php echo esc_html( $status_label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Retention Period (Days)</th>
                        <td>
                            <input type="number" name="clean_order_age" value="<?php echo esc_attr( $order_age ); ?>" min="0" style="width: 80px; padding: 5px; background-color: #1e1e1e; color: #fff; border: 1px solid #444; border-radius: 4px;" />
                            <p class="description">Only target orders that are strictly older than this many days.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Execution Schedule</th>
                        <td>
                            <select name="clean_interval" id="clean_interval" onchange="if(this.value=='disabled'){alert('Automation disabled.');}">
                                <option value="daily" <?php selected( $interval, 'daily' ); ?>>Run Daily</option>
                                <option value="weekly" <?php selected( $interval, 'weekly' ); ?>>Run Weekly</option>
                                <option value="disabled" <?php selected( $interval, 'disabled' ); ?>>Disabled (Manual Only)</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit"><?php submit_button( 'Save Configuration', 'clean-btn-primary', 'submit', false ); ?></p>
            </form>

            <div class="clean-card-dark">
                <h2 style="color: #ef4444; border-bottom-color: #440000; margin-top:0;">Manual Override Engine</h2>
                <p>Execute scripts manually. Processes up to 500 un-scrubbed orders per click.</p>
                <form method="post" action="" style="display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap;">
                    <?php wp_nonce_field( 'clean_run_now_nonce' ); ?>
                    <input type="submit" name="clean_test_integrations" class="button clean-btn-success" value="Test API Connections" onclick="return confirm('This will send a fake test order to all configured backups (Sheets, Discord, etc) to verify your credentials. Proceed?');" />
                    <input type="submit" name="clean_manual_backup_trigger" class="button clean-btn-secondary" value="Execute Backup Only (Safe)" onclick="return confirm('This will generate and send a backup of unscrubbed orders without deleting any data. Proceed?');" />
                    <input type="submit" name="clean_manual_trigger" class="button clean-btn-danger" value="Execute System Purge Now" onclick="return confirm('Warning: This alters the database and permanently scrubs data. Proceed?');" />
                </form>
            </div>
        </div>
    </div>
    <?php
}

add_action( 'update_option_clean_interval', 'clean_reschedule_cron', 10, 2 );
function clean_reschedule_cron( $old_value, $new_value ) {
    wp_clear_scheduled_hook( 'clean_cron_hook' );
    if ( $new_value !== 'disabled' ) { wp_schedule_event( time(), $new_value, 'clean_cron_hook' ); }
}

add_action( 'init', 'clean_snippet_cron_setup' );
function clean_snippet_cron_setup() {
    $interval = get_option( 'clean_interval', 'weekly' );
    if ( $interval !== 'disabled' && ! wp_next_scheduled( 'clean_cron_hook' ) ) {
        wp_schedule_event( time(), $interval, 'clean_cron_hook' );
    }
}

add_action( 'clean_cron_hook', 'clean_purge_engine' );


function clean_purge_engine( $do_scrub = true ) {
    if ( ! is_bool( $do_scrub ) ) {
        $do_scrub = true; 
    }

    $target_statuses = get_option( 'clean_statuses', array( 'completed' ) );
    $order_age       = get_option( 'clean_order_age', '30' );
    
    if ( empty( $target_statuses ) ) return;

    $args = array(
        'type'   => 'shop_order',
        'status' => $target_statuses,
        'limit'  => 500,
        'return' => 'ids',
    );

    if ( is_numeric( $order_age ) && $order_age > 0 ) {
        $args['date_created'] = '<' . strtotime( '-' . absint( $order_age ) . ' days' );
    }

    $orders = wc_get_orders( $args );
    if ( empty($orders) ) return;

    $drop_ips = get_option( 'clean_drop_ips', 'yes' );
    $remove_names = get_option( 'clean_remove_names', 'yes' );
    $remove_addresses = get_option( 'clean_remove_addresses', 'yes' );
    $remove_phone = get_option( 'clean_remove_phone', 'yes' );
    $remove_email = get_option( 'clean_remove_email', 'no' );
    $remove_company = get_option( 'clean_remove_company', 'yes' );
    $remove_notes = get_option( 'clean_remove_notes', 'no' );
    
    $backup_email = get_option( 'clean_backup_email', '' );
    $backup_webhook = get_option( 'clean_backup_webhook', '' );
    $backup_discord = get_option( 'clean_backup_discord', '' );
    $backup_tele_token = get_option( 'clean_backup_telegram_token', '' );
    $backup_tele_chat = get_option( 'clean_backup_telegram_chat', '' );
    $zip_pass = get_option( 'clean_backup_zip_password', '' );

    $backup_payload = array();
    $purged_count = 0;

    foreach ( $orders as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_type() !== 'shop_order' || $order->get_meta( '_info_cleaned' ) === 'yes' ) {
            continue;
        }

        $backup_payload[] = array(
            'id'         => $order->get_order_number(),
            'date'       => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
            'email'      => $order->get_billing_email(),
            'phone'      => $order->get_billing_phone(),
            'address'    => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'city'       => $order->get_billing_city(),
            'state'      => $order->get_billing_state(),
            'postcode'   => $order->get_billing_postcode(),
            'total'      => $order->get_total()
        );

        if ( $do_scrub ) {
            if ( $remove_names === 'yes' ) {
                $order->set_billing_first_name( 'Removed' ); $order->set_billing_last_name( 'Removed' );
                $order->set_shipping_first_name( 'Removed' ); $order->set_shipping_last_name( 'Removed' );
            }
            if ( $remove_company === 'yes' ) { $order->set_billing_company( '' ); $order->set_shipping_company( '' ); }
            if ( $remove_addresses === 'yes' ) {
                $order->set_billing_address_1( 'Removed' ); $order->set_billing_address_2( '' );
                $order->set_billing_city( 'Removed' ); $order->set_billing_state( '' ); $order->set_billing_postcode( '' );
                $order->set_shipping_address_1( 'Removed' ); $order->set_shipping_address_2( '' );
                $order->set_shipping_city( 'Removed' ); $order->set_shipping_state( '' ); $order->set_shipping_postcode( '' );
            }
            if ( $remove_phone === 'yes' ) { $order->set_billing_phone( '' ); }
            if ( $remove_email === 'yes' ) { $order->set_billing_email( 'removed@domain.local' ); }
            if ( $drop_ips === 'yes' ) { $order->set_customer_ip_address( '0.0.0.0' ); $order->set_customer_user_agent( 'Removed' ); }
            if ( $remove_notes === 'yes' ) {
                $notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
                foreach ( $notes as $note ) { wc_delete_order_note( $note->id ); }
            }

            $order->update_meta_data( '_info_cleaned', 'yes' );
            $order->save();
        }
        $purged_count++;
    }

    if ( ! empty( $backup_payload ) ) {
        
        $action_word = $do_scrub ? 'scrubbed' : 'backed up (data retained)';

        if ( ! empty( $backup_webhook ) ) {
            wp_remote_post( $backup_webhook, array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( $backup_payload ),
                'timeout' => 15,
            ));
        }

        $needs_file = ( is_email( $backup_email ) || ! empty( $backup_discord ) || ( ! empty( $backup_tele_token ) && ! empty( $backup_tele_chat ) ) );
        
        if ( $needs_file ) {
            $upload_dir = wp_upload_dir();
            $base_name  = 'backup_orders_' . time();
            $csv_path   = trailingslashit( $upload_dir['basedir'] ) . $base_name . '.csv';
            $zip_path   = trailingslashit( $upload_dir['basedir'] ) . $base_name . '.zip';
            
            $file = fopen( $csv_path, 'w' );
            fputcsv( $file, array( 'Order ID', 'Date', 'First Name', 'Last Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip', 'Total' ) );
            foreach ( $backup_payload as $row ) { fputcsv( $file, $row ); }
            fclose( $file );

            $export_file = $csv_path;
            $export_mime = 'text/csv';

            if ( class_exists( 'ZipArchive' ) ) {
                $zip = new ZipArchive();
                if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
                    $zip->addFile( $csv_path, basename( $csv_path ) );
                    if ( ! empty( $zip_pass ) && strlen( $zip_pass ) >= 20 ) {
                        $zip->setEncryptionName( basename( $csv_path ), ZipArchive::EM_AES_256, $zip_pass );
                    }
                    $zip->close();
                    $export_file = $zip_path;
                    $export_mime = 'application/zip';
                }
            }

            // Export to Email
            if ( is_email( $backup_email ) ) {
                wp_mail( $backup_email, 'Encrypted Data Backup: WooCommerce', "Attached is the backup archive for the orders {$action_word} today.", '', array( $export_file ) );
            }

            // Export to Discord
            if ( ! empty( $backup_discord ) ) {
                $boundary = wp_generate_password( 24, false );
                $payload  = "--{$boundary}\r\n";
                $payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $export_file ) . "\"\r\n";
                $payload .= "Content-Type: {$export_mime}\r\n\r\n" . file_get_contents( $export_file ) . "\r\n";
                $payload .= "--{$boundary}\r\n";
                $payload .= "Content-Disposition: form-data; name=\"payload_json\"\r\n\r\n";
                $payload .= wp_json_encode( array( 'content' => "🛡️ **Data Cleaner**\n`{$purged_count}` orders {$action_word}. Archive attached." ) ) . "\r\n";
                $payload .= "--{$boundary}--\r\n";

                wp_remote_post( $backup_discord, array(
                    'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
                    'body'    => $payload,
                    'timeout' => 20,
                ));
            }

            // Export to Telegram
            if ( ! empty( $backup_tele_token ) && ! empty( $backup_tele_chat ) ) {
                $boundary = wp_generate_password( 24, false );
                $payload  = "--{$boundary}\r\n";
                $payload .= "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n{$backup_tele_chat}\r\n";
                $payload .= "--{$boundary}\r\n";
                $payload .= "Content-Disposition: form-data; name=\"caption\"\r\n\r\n🛡️ Data Cleaner executed.\n{$purged_count} orders {$action_word}.\r\n";
                $payload .= "--{$boundary}\r\n";
                $payload .= 'Content-Disposition: form-data; name="document"; filename="' . basename( $export_file ) . "\"\r\n";
                $payload .= "Content-Type: {$export_mime}\r\n\r\n" . file_get_contents( $export_file ) . "\r\n";
                $payload .= "--{$boundary}--\r\n";

                wp_remote_post( "https://api.telegram.org/bot" . esc_attr( $backup_tele_token ) . "/sendDocument", array(
                    'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
                    'body'    => $payload,
                    'timeout' => 20,
                ));
            }

            // Auto-Destruct Files
            if ( file_exists( $csv_path ) ) unlink( $csv_path );
            if ( file_exists( $zip_path ) ) unlink( $zip_path );
        }
    }
}

// --- 5. TEST API CONNECTIONS FUNCTION ---

function clean_run_test_integrations() {
    $backup_email = get_option( 'clean_backup_email', '' );
    $backup_webhook = get_option( 'clean_backup_webhook', '' );
    $backup_discord = get_option( 'clean_backup_discord', '' );
    $backup_tele_token = get_option( 'clean_backup_telegram_token', '' );
    $backup_tele_chat = get_option( 'clean_backup_telegram_chat', '' );
    $zip_pass = get_option( 'clean_backup_zip_password', '' );

    // Generate dummy order data
    $backup_payload = array(
        array(
            'id'         => 'TEST-001',
            'date'       => current_time('mysql'),
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'email'      => 'john.doe@example.com',
            'phone'      => '555-0199',
            'address'    => '123 Privacy St',
            'city'       => 'Secureville',
            'state'      => 'CA',
            'postcode'   => '90210',
            'total'      => '99.99'
        )
    );

    $purged_count = 1;
    $action_word = 'TESTED (Dummy Data)';

    if ( ! empty( $backup_webhook ) ) {
        wp_remote_post( $backup_webhook, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $backup_payload ),
            'timeout' => 15,
        ));
    }

    $needs_file = ( is_email( $backup_email ) || ! empty( $backup_discord ) || ( ! empty( $backup_tele_token ) && ! empty( $backup_tele_chat ) ) );
    
    if ( $needs_file ) {
        $upload_dir = wp_upload_dir();
        $base_name  = 'test_backup_orders_' . time();
        $csv_path   = trailingslashit( $upload_dir['basedir'] ) . $base_name . '.csv';
        $zip_path   = trailingslashit( $upload_dir['basedir'] ) . $base_name . '.zip';
        
        $file = fopen( $csv_path, 'w' );
        fputcsv( $file, array( 'Order ID', 'Date', 'First Name', 'Last Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip', 'Total' ) );
        foreach ( $backup_payload as $row ) { fputcsv( $file, $row ); }
        fclose( $file );

        $export_file = $csv_path;
        $export_mime = 'text/csv';

        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
                $zip->addFile( $csv_path, basename( $csv_path ) );
                if ( ! empty( $zip_pass ) && strlen( $zip_pass ) >= 20 ) {
                    $zip->setEncryptionName( basename( $csv_path ), ZipArchive::EM_AES_256, $zip_pass );
                }
                $zip->close();
                $export_file = $zip_path;
                $export_mime = 'application/zip';
            }
        }

        if ( is_email( $backup_email ) ) {
            wp_mail( $backup_email, 'Encrypted Data Backup: WooCommerce', "Attached is the backup archive for the orders {$action_word} today.", '', array( $export_file ) );
        }

        if ( ! empty( $backup_discord ) ) {
            $boundary = wp_generate_password( 24, false );
            $payload  = "--{$boundary}\r\n";
            $payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $export_file ) . "\"\r\n";
            $payload .= "Content-Type: {$export_mime}\r\n\r\n" . file_get_contents( $export_file ) . "\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"payload_json\"\r\n\r\n";
            $payload .= wp_json_encode( array( 'content' => "🛡️ **Data Cleaner**\n`{$purged_count}` orders {$action_word}. Archive attached." ) ) . "\r\n";
            $payload .= "--{$boundary}--\r\n";

            wp_remote_post( $backup_discord, array(
                'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
                'body'    => $payload,
                'timeout' => 20,
            ));
        }

        if ( ! empty( $backup_tele_token ) && ! empty( $backup_tele_chat ) ) {
            $boundary = wp_generate_password( 24, false );
            $payload  = "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n{$backup_tele_chat}\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"caption\"\r\n\r\n🛡️ Data Cleaner executed.\n{$purged_count} orders {$action_word}.\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= 'Content-Disposition: form-data; name="document"; filename="' . basename( $export_file ) . "\"\r\n";
            $payload .= "Content-Type: {$export_mime}\r\n\r\n" . file_get_contents( $export_file ) . "\r\n";
            $payload .= "--{$boundary}--\r\n";

            wp_remote_post( "https://api.telegram.org/bot" . esc_attr( $backup_tele_token ) . "/sendDocument", array(
                'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
                'body'    => $payload,
                'timeout' => 20,
            ));
        }

        if ( file_exists( $csv_path ) ) unlink( $csv_path );
        if ( file_exists( $zip_path ) ) unlink( $zip_path );
    }
}

// --- 6. SINGLE ORDER UI (QUICK SCRUB ONLY) ---

add_filter( 'woocommerce_order_actions', 'clean_ui_manual_action' );
function clean_ui_manual_action( $actions ) {
    global $theorder;
    $target_statuses = get_option( 'clean_statuses', array( 'completed' ) );
    if ( $theorder && in_array( $theorder->get_status(), $target_statuses ) && $theorder->get_meta( '_info_cleaned' ) !== 'yes' ) {
        $actions['clean_single_order_manual'] = 'Quick Scrub (No Backup)';
    }
    return $actions;
}

add_action( 'woocommerce_order_action_clean_single_order_manual', 'clean_process_single_manual' );
function clean_process_single_manual( $order ) {
    $drop_ips = get_option( 'clean_drop_ips', 'yes' );
    $remove_names = get_option( 'clean_remove_names', 'yes' );
    $remove_addresses = get_option( 'clean_remove_addresses', 'yes' );
    $remove_phone = get_option( 'clean_remove_phone', 'yes' );
    $remove_email = get_option( 'clean_remove_email', 'no' );
    $remove_company = get_option( 'clean_remove_company', 'yes' );
    $remove_notes = get_option( 'clean_remove_notes', 'no' );
    
    if ( $remove_names === 'yes' ) {
        $order->set_billing_first_name( 'Removed' ); $order->set_billing_last_name( 'Removed' );
        $order->set_shipping_first_name( 'Removed' ); $order->set_shipping_last_name( 'Removed' );
    }
    if ( $remove_company === 'yes' ) { $order->set_billing_company( '' ); $order->set_shipping_company( '' ); }
    if ( $remove_addresses === 'yes' ) {
        $order->set_billing_address_1( 'Removed' ); $order->set_billing_address_2( '' );
        $order->set_billing_city( 'Removed' ); $order->set_billing_state( '' ); $order->set_billing_postcode( '' );
        $order->set_shipping_address_1( 'Removed' ); $order->set_shipping_address_2( '' );
        $order->set_shipping_city( 'Removed' ); $order->set_shipping_state( '' ); $order->set_shipping_postcode( '' );
    }
    if ( $remove_phone === 'yes' ) { $order->set_billing_phone( '' ); }
    if ( $remove_email === 'yes' ) { $order->set_billing_email( 'removed@domain.local' ); }
    if ( $drop_ips === 'yes' ) { $order->set_customer_ip_address( '0.0.0.0' ); $order->set_customer_user_agent( 'Removed' ); }
    if ( $remove_notes === 'yes' ) {
        $notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
        foreach ( $notes as $note ) { wc_delete_order_note( $note->id ); }
    }

    $order->update_meta_data( '_info_cleaned', 'yes' );
    $order->add_order_note( 'Manual UI Override execution: Personal info cleaned (Bypassed backup).' );
    $order->save();
}