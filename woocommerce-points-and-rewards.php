<?php
/*
Plugin Name: WooCommerce Points and Rewards System
Description: Adds a customizable loyalty points system for WooCommerce purchases, including admin manual assignment, birthday management, third-party orders, user account pages, and more.
Version: 2.2.0
Author: Your Name
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Points_And_Rewards {

    // Option keys
    const OPTION_POINTS_PER_DOLLAR = 'wc_points_per_dollar';
    const OPTION_POINT_VALUE = 'wc_point_value';
    const OPTION_POINTS_REVIEW = 'wc_points_for_review';
    const OPTION_POINTS_RATING = 'wc_points_for_rating';
    const OPTION_MINIMUM_REDEEM = 'wc_points_minimum_redeem';
    const OPTION_MAX_REDEEM_PERCENT = 'wc_points_max_redeem_percent';
    const OPTION_POINTS_ON_DISCOUNT = 'wc_points_on_discount';
    const OPTION_POINTS_ON_REDEEM = 'wc_points_on_redeem';
    const OPTION_POINTS_ON_SIGNUP = 'wc_points_on_signup';
    const OPTION_ENABLE_DOB_POINTS = 'wc_enable_dob_points';
    const OPTION_DOB_POINTS_REWARD = 'wc_dob_points_reward';
    const OPTION_BIRTHDAY_POINTS_ENABLED = 'wc_birthday_points_enabled';
    const OPTION_BIRTHDAY_POINTS_AMOUNT = 'wc_birthday_points_amount';
    const OPTION_PROMO_BOX_ENABLED = 'wc_points_promo_box_enabled';
    const OPTION_PROMO_BOX_SHOW_VALUE = 'wc_points_promo_box_show_value';
    const OPTION_PROMO_BOX_TEXT = 'wc_points_promo_box_text';
    // Account/Loyalty Tab
    const OPTION_LOYALTY_POINTS_SAVINGS_ENABLE = 'wc_loyalty_points_savings_enable';
    const OPTION_LOYALTY_POINTS_SAVINGS_TITLE = 'wc_loyalty_points_savings_title';
    const OPTION_LOYALTY_POINTS_SAVINGS_DESC = 'wc_loyalty_points_savings_desc';
    // Third Party Orders
    const OPTION_TP_DESC = 'wc_third_party_orders_desc';
    const OPTION_TP_COUPON_ENABLE = 'wc_third_party_coupon_enable';
    const OPTION_TP_COLLECTED_MSG = 'wc_third_party_points_collected_msg';
    const OPTION_TP_COUPON_SENT_MSG = 'wc_third_party_coupon_sent_msg';
    const OPTION_TP_COUPON_PREFIX = 'wc_third_party_coupon_prefix';
    const OPTION_TP_COUPON_VALIDITY = 'wc_third_party_coupon_validity';

    private static $instance = null;

    public function __construct() {
        add_filter('woocommerce_get_settings_pages', array($this, 'add_settings_tab'));
        add_action('woocommerce_order_status_completed', array($this, 'award_points_for_order'), 10, 1);
        add_action('woocommerce_account_dashboard', array($this, 'show_user_points_on_account'), 5);
        add_action('user_register', array($this, 'initialize_user_points'), 10, 1);
        add_filter('manage_users_columns', array($this, 'add_user_points_column'));
        add_filter('manage_users_custom_column', array($this, 'show_user_points_column'), 10, 3);
        add_action('comment_post', array($this, 'award_points_for_review_and_rating'), 20, 3);
        add_action('wp_set_comment_status', array($this, 'maybe_remove_points_on_review_status_change'), 10, 2);
        add_action('delete_comment', array($this, 'maybe_remove_points_on_review_delete'), 10, 1);
        add_action('woocommerce_before_cart', array($this, 'check_minimum_points_before_redeem'));
        add_action('woocommerce_checkout_process', array($this, 'check_minimum_points_before_redeem'));
        add_action('woocommerce_before_cart', array($this, 'enforce_max_points_redeem_cap'));
        add_action('woocommerce_checkout_process', array($this, 'enforce_max_points_redeem_cap'));
        add_action('woocommerce_edit_account_form', array($this, 'add_dob_field_to_account'));
        add_action('woocommerce_save_account_details', array($this, 'save_dob_field_account'), 10, 1);
        add_action('personal_options_update', array($this, 'maybe_award_dob_points_admin'));
        add_action('edit_user_profile_update', array($this, 'maybe_award_dob_points_admin'));
        add_action('wc_points_rewards_daily_birthday_check', array($this, 'birthday_points_daily_check'));
        add_action('init', array($this, 'maybe_schedule_birthday_cron'));
        add_action('wc_points_rewards_expire_birthday_points', array($this, 'expire_birthday_points'), 10, 2);
        add_filter('woocommerce_email_classes', array($this, 'register_birthday_email_class'));
        add_action('woocommerce_loaded', array($this, 'include_birthday_email_template'));
        add_action('woocommerce_single_product_summary', array($this, 'display_promo_points_box'), 31);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_promo_box_styles'));
        add_action('admin_menu', array($this, 'add_loyalty_points_admin_menu'));
        add_action('woocommerce_applied_coupon', array($this, 'track_points_redemption'), 10, 1);
        add_action('admin_init', array($this, 'handle_loyalty_csv_export'));

        // Account page: add custom tabs and logic
        add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_items'), 30);
        add_filter('woocommerce_account_menu_items', array($this, 'add_third_party_orders_tab'), 40);
        add_filter('woocommerce_account_menu_items', array($this, 'add_loyalty_points_tab'), 20);
        add_action('init', array($this, 'register_account_endpoints'));
        add_action('woocommerce_account_loyalty-points-savings_endpoint', array($this, 'render_loyalty_points_tab_content'));
        add_action('woocommerce_account_my-third-party-orders_endpoint', array($this, 'render_third_party_orders_tab_content'));
        add_action('woocommerce_account_orders_columns', array($this, 'add_orders_points_column'));
        add_action('woocommerce_my_account_my_orders_column_points', array($this, 'render_orders_points_column'));
        add_action('woocommerce_account_orders', array($this, 'render_third_party_orders_history_table_after_orders'), 20);

        // Third Party Orders: admin menu and logic
        add_action('admin_menu', array($this, 'add_third_party_orders_menu_item'));
        add_action('admin_post_wc_add_third_party_order', array($this, 'handle_admin_add_third_party_order'));
        add_action('wp_ajax_wc_redeem_third_party_order', array($this, 'ajax_redeem_third_party_order'));
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new WC_Points_And_Rewards();
        }
        return self::$instance;
    }

    /***********************
     * SETTINGS TAB SECTION
     ***********************/
    public function add_settings_tab($settings) {
        if (class_exists('WC_Settings_Page')) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-wc-settings-points-rewards.php';
            $settings[] = new WC_Settings_Points_Rewards();
        }
        return $settings;
    }

    /***********************
     * ADMIN & USER ACCOUNT
     ***********************/
    // Register endpoints for custom tabs
    public function register_account_endpoints() {
        add_rewrite_endpoint('loyalty-points-savings', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('my-third-party-orders', EP_ROOT | EP_PAGES);
    }

    // Add custom menu item for Loyalty Points & Savings tab
    public function add_loyalty_points_tab($items) {
        $enable = get_option(self::OPTION_LOYALTY_POINTS_SAVINGS_ENABLE, 'yes');
        if ($enable === 'yes') {
            $title = get_option(self::OPTION_LOYALTY_POINTS_SAVINGS_TITLE, __('Loyalty Points & Savings', 'wc-points-rewards'));
            $menu_items = array();
            foreach ($items as $key => $value) {
                $menu_items[$key] = $value;
                if ($key === 'dashboard') {
                    $menu_items['loyalty-points-savings'] = $title;
                }
            }
            return $menu_items;
        }
        return $items;
    }

    // Add custom menu item for Third Party Orders tab
    public function add_third_party_orders_tab($items) {
        $menu_items = array();
        foreach ($items as $key => $value) {
            $menu_items[$key] = $value;
            if ($key === 'orders') {
                $menu_items['my-third-party-orders'] = __('My Third Party Orders', 'wc-points-rewards');
            }
        }
        return $menu_items;
    }

    // Loyalty points summary in Dashboard
    public function show_user_points_on_account() {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();
        $points = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
        $points_redeemed = floatval(get_user_meta($user_id, 'wc_points_redeemed_value', true));
        $point_value = floatval(get_option(self::OPTION_POINT_VALUE, 1.0));
        $balance = number_format($points * $point_value, 2);
        $min_redeem = intval(get_option(self::OPTION_MINIMUM_REDEEM, 50));
        $max_percent = intval(get_option(self::OPTION_MAX_REDEEM_PERCENT, 50));

        // Place just below profile name, above dashboard description
        echo '<div class="woocommerce-loyalty-summary" style="margin-bottom:1em;">';
        echo '<h3>' . esc_html__('Loyalty Points Summary', 'wc-points-rewards') . '</h3>';
        echo '<table class="shop_table shop_table_responsive">';
        echo '<tr><th>' . esc_html__('Point Balance', 'wc-points-rewards') . '</th><td>' . intval($points) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Total Amount Saved', 'wc-points-rewards') . '</th><td>$' . number_format($points_redeemed,2) . '</td></tr>';
        echo '</table>';
        echo '</div>';
    }

    // Loyalty Points & Savings tab page content
    public function render_loyalty_points_tab_content() {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();
        $points = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
        $lifetime_points = (
            intval(get_user_meta($user_id, 'wc_signup_points', true)) +
            intval(get_user_meta($user_id, 'wc_birthday_total_points', true)) +
            intval(get_user_meta($user_id, 'wc_rating_points', true)) +
            intval(get_user_meta($user_id, 'wc_review_points', true)) +
            intval(get_user_meta($user_id, 'wc_order_points', true))
        );
        $points_last_order = intval(get_user_meta($user_id, 'wc_points_last_order', true));
        $points_redeemed = floatval(get_user_meta($user_id, 'wc_points_redeemed_value', true));
        $third_party_points = intval(get_user_meta($user_id, 'wc_third_party_points', true));
        $point_value = floatval(get_option(self::OPTION_POINT_VALUE, 1.0));

        echo '<h2>' . esc_html(get_option(self::OPTION_LOYALTY_POINTS_SAVINGS_TITLE, __('Loyalty Points & Savings', 'wc-points-rewards'))) . '</h2>';
        $desc = get_option(self::OPTION_LOYALTY_POINTS_SAVINGS_DESC, '<p>Earn loyalty points with every purchase and redeem them for discounts!</p>');
        echo '<div class="wc-loyalty-desc">' . $desc . '</div>';

        echo '<table class="shop_table shop_table_responsive" style="margin-top:1em">';
        echo '<tr><th>' . esc_html__('Lifetime Points', 'wc-points-rewards') . '</th><td>' . intval($lifetime_points) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Points from Last Order', 'wc-points-rewards') . '</th><td>' . intval($points_last_order) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Total Coupon Savings', 'wc-points-rewards') . '</th><td>$' . number_format($points_redeemed,2) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Third-party Points', 'wc-points-rewards') . '</th><td>' . intval($third_party_points) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Point Balance', 'wc-points-rewards') . '</th><td>' . intval($points) . '</td></tr>';
        echo '</table>';
    }

    // Third Party Orders tab page content
    public function render_third_party_orders_tab_content() {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();

        $desc = get_option(self::OPTION_TP_DESC, '<p>Enter your delivery app order code here to earn points or receive a discount coupon!</p>');
        echo '<h2>' . esc_html__('My Third Party Orders', 'wc-points-rewards') . '</h2>';
        echo '<div class="wc-third-party-desc">' . $desc . '</div>';
        ?>
        <form id="wc-tp-redeem-form" method="post" style="margin:1em 0;">
            <input type="text" name="tp_order_id" id="tp_order_id" placeholder="<?php esc_attr_e('Enter Your Order Code', 'wc-points-rewards'); ?>" style="width:250px;">
            <button type="button" class="button" id="wc-tp-redeem-submit"><?php esc_html_e('Submit', 'wc-points-rewards'); ?></button>
        </form>
        <div id="wc-tp-redeem-popup" style="display:none;"></div>
        <script>
        jQuery(function($){
            $('#wc-tp-redeem-submit').on('click', function(e){
                var code = $('#tp_order_id').val();
                if(!code) return;
                $.post(ajaxurl, {
                    action: 'wc_redeem_third_party_order',
                    tp_order_id: code,
                    user_id: <?php echo (int)$user_id; ?>,
                    _ajax_nonce: '<?php echo wp_create_nonce('wc_redeem_tp'); ?>'
                }, function(response){
                    $('#wc-tp-redeem-popup').html(response.html).show();
                });
            });
        });
        </script>
        <?php
        // Show user's third-party orders below (table)
        $tp_orders = get_user_meta($user_id, 'wc_tp_orders', true);
        if ($tp_orders && is_array($tp_orders) && count($tp_orders)) {
            echo '<h3>' . esc_html__('Your Third Party Orders', 'wc-points-rewards') . '</h3>';
            echo '<table class="shop_table shop_table_responsive">';
            echo '<thead><tr><th>' . esc_html__('Order Code', 'wc-points-rewards') . '</th><th>' . esc_html__('Date', 'wc-points-rewards') . '</th><th>' . esc_html__('Order Value', 'wc-points-rewards') . '</th><th>' . esc_html__('Points', 'wc-points-rewards') . '</th><th>' . esc_html__('Status', 'wc-points-rewards') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($tp_orders as $order) {
                echo '<tr>';
                echo '<td>' . esc_html($order['id']) . '</td>';
                echo '<td>' . esc_html($order['date']) . '</td>';
                echo '<td>$' . esc_html(number_format($order['value'], 2)) . '</td>';
                echo '<td>' . esc_html($order['points']) . '</td>';
                echo '<td>' . esc_html__('Completed', 'wc-points-rewards') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    // Add "Points Earned" column to Orders table
    public function add_orders_points_column($columns) {
        $columns['points'] = __('Points Earned', 'wc-points-rewards');
        return $columns;
    }

    // Render "Points Earned" column in order history
    public function render_orders_points_column($order) {
        $order_id = is_object($order) ? $order->get_id() : $order;
        $points = get_post_meta($order_id, '_wc_points_awarded', true);
        if ($points) {
            echo intval($points);
        } else {
            echo '—';
        }
    }

    // Show third-party orders table after Woo orders
    public function render_third_party_orders_history_table_after_orders() {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();
        $tp_orders = get_user_meta($user_id, 'wc_tp_orders', true);
        if ($tp_orders && is_array($tp_orders) && count($tp_orders)) {
            echo '<h3>' . esc_html__('Third Party Orders', 'wc-points-rewards') . '</h3>';
            echo '<table class="shop_table shop_table_responsive">';
            echo '<thead><tr><th>' . esc_html__('Order Code', 'wc-points-rewards') . '</th><th>' . esc_html__('Date', 'wc-points-rewards') . '</th><th>' . esc_html__('Points', 'wc-points-rewards') . '</th><th>' . esc_html__('Status', 'wc-points-rewards') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($tp_orders as $order) {
                echo '<tr>';
                echo '<td>' . esc_html($order['id']) . '</td>';
                echo '<td>' . esc_html($order['date']) . '</td>';
                echo '<td>' . esc_html($order['points']) . '</td>';
                echo '<td>' . esc_html__('Completed', 'wc-points-rewards') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    // Add custom admin menu for Third Party Orders management
    public function add_third_party_orders_menu_item() {
        add_submenu_page(
            'wc_points_loyalty',
            __('Third Party Orders', 'wc-points-rewards'),
            __('Third Party Orders', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc_third_party_orders',
            array($this, 'render_third_party_orders_admin_page')
        );
    }

    // Render the admin page for third-party orders
    public function render_third_party_orders_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Third Party Orders', 'wc-points-rewards'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wc_add_third_party_order">
                <?php wp_nonce_field('wc_add_third_party_order_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="tp_order_id"><?php esc_html_e('3rd Party Order ID', 'wc-points-rewards'); ?></label></th>
                        <td><input type="text" name="tp_order_id" required></td>
                    </tr>
                    <tr>
                        <th><label for="tp_order_date"><?php esc_html_e('3rd Party Order Date', 'wc-points-rewards'); ?></label></th>
                        <td><input type="date" name="tp_order_date" required></td>
                    </tr>
                    <tr>
                        <th><label for="tp_order_value"><?php esc_html_e('Order Value', 'wc-points-rewards'); ?></label></th>
                        <td><input type="number" name="tp_order_value" step="0.01" required></td>
                    </tr>
                    <tr>
                        <th><label for="tp_points"><?php esc_html_e('Collectible Points', 'wc-points-rewards'); ?></label></th>
                        <td><input type="number" name="tp_points" min="0" required></td>
                    </tr>
                </table>
                <p><input type="submit" class="button button-primary" value="<?php esc_attr_e('Add Third Party Order', 'wc-points-rewards'); ?>"></p>
            </form>
        <?php
        $tp_orders = get_option('wc_third_party_orders', array());
        if ($tp_orders && is_array($tp_orders) && count($tp_orders)) {
            echo '<h2>' . esc_html__('Existing Third Party Orders', 'wc-points-rewards') . '</h2>';
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>' . esc_html__('Order ID', 'wc-points-rewards') . '</th><th>' . esc_html__('Date', 'wc-points-rewards') . '</th><th>' . esc_html__('Value', 'wc-points-rewards') . '</th><th>' . esc_html__('Points', 'wc-points-rewards') . '</th></tr></thead><tbody>';
            foreach ($tp_orders as $order) {
                echo '<tr>';
                echo '<td>' . esc_html($order['id']) . '</td>';
                echo '<td>' . esc_html($order['date']) . '</td>';
                echo '<td>$' . esc_html(number_format($order['value'], 2)) . '</td>';
                echo '<td>' . esc_html($order['points']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    // Handle admin adding a third-party order
    public function handle_admin_add_third_party_order() {
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('wc_add_third_party_order_nonce')) {
            wp_die(__('Unauthorized', 'wc-points-rewards'));
        }
        $id = sanitize_text_field($_POST['tp_order_id']);
        $date = sanitize_text_field($_POST['tp_order_date']);
        $value = floatval($_POST['tp_order_value']);
        $points = intval($_POST['tp_points']);
        $tp_orders = get_option('wc_third_party_orders', array());
        $tp_orders[] = array(
            'id' => $id,
            'date' => $date,
            'value' => $value,
            'points' => $points,
            'claimed' => false,
        );
        update_option('wc_third_party_orders', $tp_orders);
        wp_redirect(admin_url('admin.php?page=wc_third_party_orders'));
        exit;
    }

    // AJAX: Handle user redeeming third-party order code
    public function ajax_redeem_third_party_order() {
        check_ajax_referer('wc_redeem_tp');
        $code = sanitize_text_field($_POST['tp_order_id']);
        $user_id = intval($_POST['user_id']);
        $tp_orders = get_option('wc_third_party_orders', array());
        $found = false; $order_data = null;
        foreach ($tp_orders as $k => $order) {
            if ($order['id'] === $code && empty($order['claimed'])) {
                $found = true;
                $order_data = $order;
                // Mark as claimed
                $tp_orders[$k]['claimed'] = true;
                update_option('wc_third_party_orders', $tp_orders);
                break;
            }
        }
        if (!$found) {
            wp_send_json(array('success'=>false,'html'=>'<div class="notice notice-error">'.esc_html__('Order code not found or already claimed.','wc-points-rewards').'</div>'));
        }

        $enable_coupon = get_option(self::OPTION_TP_COUPON_ENABLE, 'yes') === 'yes';
        ob_start();
        ?>
        <div class="wc-tp-redeem-popup-inner">
            <p><?php esc_html_e('How would you like to redeem your order?', 'wc-points-rewards'); ?></p>
            <button class="button" id="tp-collect-points"><?php esc_html_e('Collect Points (recommended)', 'wc-points-rewards'); ?></button>
            <?php if ($enable_coupon) { ?>
                <button class="button" id="tp-get-coupon"><?php esc_html_e('Receive a Discount Coupon', 'wc-points-rewards'); ?></button>
            <?php } ?>
        </div>
        <script>
        jQuery(function($){
            $('#tp-collect-points').on('click', function(){
                $.post(ajaxurl, {
                    action: 'wc_tp_redeem_points_final',
                    code: '<?php echo esc_js($code); ?>',
                    user_id: <?php echo (int)$user_id; ?>,
                    points: <?php echo (int)$order_data['points']; ?>,
                    value: '<?php echo number_format($order_data['points']*floatval(get_option('wc_point_value',1)), 2); ?>',
                    _ajax_nonce: '<?php echo wp_create_nonce('wc_tp_redeem_points_final_nonce'); ?>'
                }, function(resp){ $('#wc-tp-redeem-popup').html(resp.html); });
            });
            $('#tp-get-coupon').on('click', function(){
                $.post(ajaxurl, {
                    action: 'wc_tp_redeem_coupon_final',
                    code: '<?php echo esc_js($code); ?>',
                    user_id: <?php echo (int)$user_id; ?>,
                    points: <?php echo (int)$order_data['points']; ?>,
                    value: '<?php echo number_format($order_data['points']*floatval(get_option('wc_point_value',1)), 2); ?>',
                    _ajax_nonce: '<?php echo wp_create_nonce('wc_tp_redeem_coupon_final_nonce'); ?>'
                }, function(resp){ $('#wc-tp-redeem-popup').html(resp.html); });
            });
        });
        </script>
        <?php
        $html = ob_get_clean();
        wp_send_json(array('success'=>true,'html'=>$html));
    }

    // ...Continue in next block...
        // AJAX: Handle user choosing to collect points
    public function ajax_tp_redeem_points_final() {
        check_ajax_referer('wc_tp_redeem_points_final_nonce');
        $code = sanitize_text_field($_POST['code']);
        $user_id = intval($_POST['user_id']);
        $points = intval($_POST['points']);
        $value = $_POST['value'];
        // Add points to user
        $tp_points = intval(get_user_meta($user_id, 'wc_third_party_points', true));
        update_user_meta($user_id, 'wc_third_party_points', $tp_points + $points);
        $all_tp_orders = get_user_meta($user_id, 'wc_tp_orders', true);
        if (!is_array($all_tp_orders)) $all_tp_orders = array();
        $all_tp_orders[] = array(
            'id'=>$code, 'date'=>date('Y-m-d'), 'points'=>$points, 'status'=>'completed', 'value'=>$value
        );
        update_user_meta($user_id, 'wc_tp_orders', $all_tp_orders);
        // Add to total loyalty points too
        $this->add_points($user_id, $points, 'manual_admin');
        $msg = get_option(self::OPTION_TP_COLLECTED_MSG, 'Hooray! {points} Points worth {value} have been added to your account.');
        $msg = str_replace('{points}', $points, $msg);
        $msg = str_replace('{value}', '$'.$value, $msg);
        $html = '<div class="notice notice-success">' . esc_html($msg) . '</div>';
        wp_send_json(array('success'=>true,'html'=>$html));
    }

    // AJAX: Handle user choosing to get a coupon
    public function ajax_tp_redeem_coupon_final() {
        check_ajax_referer('wc_tp_redeem_coupon_final_nonce');
        $code = sanitize_text_field($_POST['code']);
        $user_id = intval($_POST['user_id']);
        $points = intval($_POST['points']);
        $value = $_POST['value'];
        // Generate coupon
        $prefix = get_option(self::OPTION_TP_COUPON_PREFIX, 'TH-');
        $validity = intval(get_option(self::OPTION_TP_COUPON_VALIDITY, 30));
        $coupon_code = $prefix . strtoupper(wp_generate_password(5, false, false));
        $amount = $value;
        $coupon = array(
            'post_title'   => $coupon_code,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => 'shop_coupon'
        );
        $new_coupon_id = wp_insert_post($coupon);
        update_post_meta($new_coupon_id, 'discount_type', 'fixed_cart');
        update_post_meta($new_coupon_id, 'coupon_amount', $amount);
        update_post_meta($new_coupon_id, 'individual_use', 'yes');
        update_post_meta($new_coupon_id, 'usage_limit', 1);
        update_post_meta($new_coupon_id, 'expiry_date', date('Y-m-d', strtotime("+{$validity} days")));
        update_post_meta($new_coupon_id, 'customer_email', wp_get_current_user()->user_email);
        // Log to user's third party orders
        $all_tp_orders = get_user_meta($user_id, 'wc_tp_orders', true);
        if (!is_array($all_tp_orders)) $all_tp_orders = array();
        $all_tp_orders[] = array(
            'id'=>$code, 'date'=>date('Y-m-d'), 'points'=>$points, 'status'=>'coupon', 'value'=>$value, 'coupon'=>$coupon_code
        );
        update_user_meta($user_id, 'wc_tp_orders', $all_tp_orders);
        // Email coupon to user
        $user = get_userdata($user_id);
        $subject = __('Your Discount Coupon', 'wc-points-rewards');
        $message = __('Here is your one-time use coupon: ', 'wc-points-rewards') . $coupon_code;
        wp_mail($user->user_email, $subject, $message);
        $msg = get_option(self::OPTION_TP_COUPON_SENT_MSG, 'Great! Check your inbox — we’ve sent you the coupon code.');
        $html = '<div class="notice notice-success">' . esc_html($msg) . '</div>';
        wp_send_json(array('success'=>true,'html'=>$html));
    }

    /***********************
     * ADMIN PANEL CORE TABS
     ***********************/
    public function add_loyalty_points_admin_menu() {
        add_menu_page(
            __('Points Loyalty', 'wc-points-rewards'),
            __('Points Loyalty', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc_points_loyalty',
            array($this, 'render_loyalty_points_admin_page'),
            'dashicons-awards',
            56
        );
    }

    public function render_loyalty_points_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'users_summary';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Points Loyalty', 'wc-points-rewards'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=wc_points_loyalty&tab=users_summary" class="nav-tab <?php echo $active_tab === 'users_summary' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Users Loyalty Points Summary', 'wc-points-rewards'); ?></a>
                <a href="?page=wc_points_loyalty&tab=assign_points" class="nav-tab <?php echo $active_tab === 'assign_points' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Assign Points', 'wc-points-rewards'); ?></a>
                <a href="?page=wc_points_loyalty&tab=birthday_today" class="nav-tab <?php echo $active_tab === 'birthday_today' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Today\'s Birthdays', 'wc-points-rewards'); ?></a>
                <a href="?page=wc_points_loyalty&tab=third_party_orders" class="nav-tab <?php echo $active_tab === 'third_party_orders' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Third Party Orders', 'wc-points-rewards'); ?></a>
            </h2>
            <div style="margin-top:20px;">
                <?php
                if ($active_tab === 'users_summary') {
                    $this->render_users_summary_tab();
                } elseif ($active_tab === 'assign_points') {
                    $this->render_assign_points_tab();
                } elseif ($active_tab === 'birthday_today') {
                    $this->render_birthday_today_tab();
                } elseif ($active_tab === 'third_party_orders') {
                    $this->render_third_party_orders_admin_page();
                }
                ?>
            </div>
        </div>
        <?php
    }

    // (Users Summary, Assign Points, Birthday Today tabs remain the same as previous blocks.)
    // ...Continue with all the unchanged or previously given methods...
        /***********************
     * USERS SUMMARY ADMIN TAB, ASSIGN POINTS, BIRTHDAY TAB
     ***********************/
    public function render_users_summary_tab() {
        echo '<form method="get" style="margin-bottom:1em">';
        foreach ($_GET as $key => $val) {
            if ($key === 'export_loyalty_csv') continue;
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '">';
        }
        echo '<input type="hidden" name="export_loyalty_csv" value="1">';
        echo '<input class="button button-primary" type="submit" value="' . esc_attr__('Export CSV', 'wc-points-rewards') . '">';
        echo '</form>';
        echo '<table class="widefat striped fixed">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('User Display Name', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('User ID', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('Email Address', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('Signup Points', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('Birthday Points', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('Rating Points', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('Review Points', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('Order Points', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('Point Discount', 'wc-points-rewards') . '</th>';
        echo '<th>' . esc_html__('Points Balance', 'wc-points-rewards') . '</th>';
        echo '</tr></thead><tbody>';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $users_per_page = 30;
        $user_query = new WP_User_Query(array(
            'number' => $users_per_page,
            'paged'  => $paged,
            'orderby'=> 'user_email',
            'order'  => 'ASC',
            'fields' => array('ID', 'user_email', 'display_name')
        ));
        $users = $user_query->get_results();
        foreach ($users as $user) {
            $user_id = $user->ID;
            $signup_points = intval(get_user_meta($user_id, 'wc_signup_points', true));
            $birthday_points = intval(get_user_meta($user_id, 'wc_birthday_total_points', true));
            if (!$birthday_points) $birthday_points = 0;
            $rating_points = intval(get_user_meta($user_id, 'wc_rating_points', true));
            $review_points = intval(get_user_meta($user_id, 'wc_review_points', true));
            $order_points = intval(get_user_meta($user_id, 'wc_order_points', true));
            $points_redeemed = floatval(get_user_meta($user_id, 'wc_points_redeemed_value', true));
            $points_balance = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . '</td>';
            echo '<td>' . esc_html($user_id) . '</td>';
            echo '<td>' . esc_html($user->user_email) . '</td>';
            echo '<td>' . esc_html($signup_points) . '</td>';
            echo '<td>' . esc_html($birthday_points) . '</td>';
            echo '<td>' . esc_html($rating_points) . '</td>';
            echo '<td>' . esc_html($review_points) . '</td>';
            echo '<td>' . esc_html($order_points) . '</td>';
            echo '<td>$' . esc_html(number_format($points_redeemed,2)) . '</td>';
            echo '<td>' . esc_html($points_balance) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        $total_users = $user_query->get_total();
        $total_pages = ceil($total_users / $users_per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $url = add_query_arg(array('paged'=>$i), menu_page_url('wc_points_loyalty', false));
                $url = add_query_arg('tab', 'users_summary', $url);
                $class = $i == $paged ? ' class="current"' : '';
                echo "<a{$class} href='" . esc_url($url) . "'>$i</a> ";
            }
            echo '</div></div>';
        }
    }

    public function render_assign_points_tab() {
        $message = '';
        if (isset($_POST['assign_points_nonce']) && wp_verify_nonce($_POST['assign_points_nonce'], 'assign_points_action')) {
            $user_id = intval($_POST['user_id']);
            $points  = intval($_POST['points']);
            if ($user_id && is_numeric($points)) {
                $this->add_points($user_id, $points, 'manual_admin');
                $message = sprintf(esc_html__('Successfully assigned %d points to user ID %d.', 'wc-points-rewards'), $points, $user_id);
            }
        }
        if (isset($_POST['assign_bulk_points_nonce']) && wp_verify_nonce($_POST['assign_bulk_points_nonce'], 'assign_bulk_points_action')) {
            if (isset($_POST['bulk_points']) && is_array($_POST['bulk_points'])) {
                foreach ($_POST['bulk_points'] as $user_id => $points) {
                    $points = intval($points);
                    $user_id = intval($user_id);
                    if ($user_id && is_numeric($points)) {
                        update_user_meta($user_id, 'wc_loyalty_points', $points);
                    }
                }
                $message = esc_html__('Bulk points updated successfully.', 'wc-points-rewards');
            }
        }
        ?>
        <form method="post" action="">
            <?php if ($message) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>
            <table class="form-table">
                <tr>
                    <th><label for="user_search"><?php esc_html_e('Search User (by email, login, or ID)', 'wc-points-rewards'); ?></label></th>
                    <td>
                        <input type="text" name="user_search" id="user_search" value="<?php echo isset($_POST['user_search']) ? esc_attr($_POST['user_search']) : ''; ?>" />
                        <input type="submit" class="button" value="<?php esc_attr_e('Search', 'wc-points-rewards'); ?>" />
                    </td>
                </tr>
            </table>
        </form>
        <?php
        if (!empty($_POST['user_search'])) {
            $user_search = sanitize_text_field($_POST['user_search']);
            $user = false;
            if (is_numeric($user_search)) $user = get_user_by('id', $user_search);
            if (!$user) $user = get_user_by('email', $user_search);
            if (!$user) $user = get_user_by('login', $user_search);

            if ($user) {
                $points = intval(get_user_meta($user->ID, 'wc_loyalty_points', true));
                ?>
                <h3><?php echo esc_html__('User:', 'wc-points-rewards') . ' ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')'; ?></h3>
                <p><?php printf(esc_html__('Current Loyalty Points: %d', 'wc-points-rewards'), $points); ?></p>
                <form method="post" action="">
                    <input type="hidden" name="assign_points_nonce" value="<?php echo esc_attr(wp_create_nonce('assign_points_action')); ?>" />
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>" />
                    <table class="form-table">
                        <tr>
                            <th><label for="points"><?php esc_html_e('Points to Assign', 'wc-points-rewards'); ?></label></th>
                            <td><input type="number" name="points" id="points" min="0" required value="<?php echo esc_attr($points); ?>" /></td>
                        </tr>
                    </table>
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Assign Points', 'wc-points-rewards'); ?>" />
                </form>
                <?php
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('User not found.', 'wc-points-rewards') . '</p></div>';
            }
        }

        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $users_per_page = 30;
        $user_query = new WP_User_Query(array(
            'number' => $users_per_page,
            'paged'  => $paged,
            'orderby'=> 'user_email',
            'order'  => 'ASC',
            'fields' => array('ID', 'user_email', 'display_name')
        ));
        $users = $user_query->get_results();
        $total_users = $user_query->get_total();
        $total_pages = ceil($total_users / $users_per_page);
        ?>
        <h2 style="margin-top:2em;"><?php esc_html_e('Manage Customer Points', 'wc-points-rewards'); ?></h2>
        <form method="post" action="">
            <input type="hidden" name="assign_bulk_points_nonce" value="<?php echo esc_attr(wp_create_nonce('assign_bulk_points_action')); ?>" />
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Customer', 'wc-points-rewards'); ?></th>
                        <th><?php esc_html_e('Points', 'wc-points-rewards'); ?></th>
                        <th><?php esc_html_e('Update', 'wc-points-rewards'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): 
                    $points = intval(get_user_meta($user->ID, 'wc_loyalty_points', true));
                ?>
                <tr>
                    <td><?php echo esc_html($user->user_email); ?></td>
                    <td><?php echo esc_html($points); ?></td>
                    <td>
                        <input type="number" name="bulk_points[<?php echo esc_attr($user->ID); ?>]" value="<?php echo esc_attr($points); ?>" min="0" style="width:70px;" />
                        <input type="submit" class="button" value="<?php esc_attr_e('Update', 'wc-points-rewards'); ?>" />
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </form>
        <?php
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $url = add_query_arg(array('paged'=>$i), menu_page_url('wc_points_loyalty', false));
                $url = add_query_arg('tab', 'assign_points', $url);
                $class = $i == $paged ? ' class="current"' : '';
                echo "<a{$class} href='" . esc_url($url) . "'>$i</a> ";
            }
            echo '</div></div>';
        }
    }

    public function render_birthday_today_tab() {
        $today = date('m-d');
        $users = get_users(array('meta_key' => 'wc_date_of_birth'));
        $birthday_users = array();
        foreach ($users as $user) {
            $dob = get_user_meta($user->ID, 'wc_date_of_birth', true);
            if (!$dob) continue;
            $dob_parts = explode('-', $dob);
            if (count($dob_parts) !== 3) continue;
            $dob_mmdd = $dob_parts[1] . '-' . $dob_parts[2];
            if ($dob_mmdd === $today) $birthday_users[] = $user;
        }
        $message = '';
        if (isset($_POST['assign_dob_points_nonce']) && wp_verify_nonce($_POST['assign_dob_points_nonce'], 'assign_dob_points_action')) {
            $user_id = intval($_POST['birthday_user_id']);
            $points  = intval($_POST['dob_points']);
            if ($user_id && $points) {
                $this->add_points($user_id, $points, 'dob_admin');
                $message = sprintf(esc_html__('Assigned %d DOB points to user ID %d.', 'wc-points-rewards'), $points, $user_id);
            }
        }
        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        if (empty($birthday_users)) {
            echo '<p>' . esc_html__('No customers have their birthday today.', 'wc-points-rewards') . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__('User', 'wc-points-rewards') . '</th><th>' . esc_html__('Email', 'wc-points-rewards') . '</th><th>' . esc_html__('Current Points', 'wc-points-rewards') . '</th><th>' . esc_html__('DOB Points Awarded', 'wc-points-rewards') . '</th><th>' . esc_html__('Assign DOB Points', 'wc-points-rewards') . '</th></tr></thead><tbody>';
            foreach ($birthday_users as $user) {
                $points = intval(get_user_meta($user->ID, 'wc_loyalty_points', true));
                $year = date('Y');
                $key = "wc_birthday_points_year_" . $year;
                $already_awarded = get_user_meta($user->ID, $key, true);
                echo '<tr>';
                echo '<td>' . esc_html($user->display_name) . '</td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . esc_html($points) . '</td>';
                echo '<td>' . ($already_awarded ? esc_html__('Yes', 'wc-points-rewards') : esc_html__('No', 'wc-points-rewards')) . '</td>';
                echo '<td>';
                if (!$already_awarded) {
                    ?>
                    <form method="post" action="">
                        <input type="hidden" name="assign_dob_points_nonce" value="<?php echo esc_attr(wp_create_nonce('assign_dob_points_action')); ?>" />
                        <input type="hidden" name="birthday_user_id" value="<?php echo esc_attr($user->ID); ?>" />
                        <input type="number" name="dob_points" min="1" required style="width:60px;" />
                        <input type="submit" class="button" value="<?php esc_attr_e('Assign', 'wc-points-rewards'); ?>" />
                    </form>
                    <?php
                } else {
                    echo esc_html__('Birthday points already awarded.', 'wc-points-rewards');
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }
        /***********************
     * POINTS & REWARDS LOGIC (Add points, award, redeem, meta updates, reviews, etc.)
     ***********************/
    // Add points and update respective meta
    private function add_points($user_id, $points, $type, $reference_id = 0, $product_id = 0, $meta_args = array()) {
        if ($points < 0) return;
        $current_points = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
        update_user_meta($user_id, 'wc_loyalty_points', $current_points + $points);
        switch ($type) {
            case 'signup':
                $old = intval(get_user_meta($user_id, 'wc_signup_points', true));
                update_user_meta($user_id, 'wc_signup_points', $old + $points);
                break;
            case 'dob':
                $old = intval(get_user_meta($user_id, 'wc_dob_points', true));
                update_user_meta($user_id, 'wc_dob_points', $old + $points);
                break;
            case 'birthday':
                $old = intval(get_user_meta($user_id, 'wc_birthday_total_points', true));
                update_user_meta($user_id, 'wc_birthday_total_points', $old + $points);
                $log = get_user_meta($user_id, 'wc_birthday_points_log', true);
                if (!is_array($log)) $log = array();
                $log[] = array(
                    'points' => $points,
                    'awarded' => current_time('mysql'),
                    'expires' => date('Y-m-d', strtotime('+6 months')),
                    'expired' => false
                );
                update_user_meta($user_id, 'wc_birthday_points_log', $log);
                break;
            case 'product_rating':
                $old = intval(get_user_meta($user_id, 'wc_rating_points', true));
                update_user_meta($user_id, 'wc_rating_points', $old + $points);
                break;
            case 'product_review':
                $old = intval(get_user_meta($user_id, 'wc_review_points', true));
                update_user_meta($user_id, 'wc_review_points', $old + $points);
                break;
            case 'order':
                $old = intval(get_user_meta($user_id, 'wc_order_points', true));
                update_user_meta($user_id, 'wc_order_points', $old + $points);
                update_user_meta($user_id, 'wc_points_last_order', $points);
                break;
            case 'manual_admin':
            default:
                break;
        }
    }

    // Award points for new order
    public function award_points_for_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        $user_id = $order->get_user_id();
        if (!$user_id) return;
        if (get_post_meta($order_id, '_wc_points_awarded', true)) return;

        $allow_points_on_discount = get_option(self::OPTION_POINTS_ON_DISCOUNT, 'yes') === 'yes';
        $allow_points_on_redeem   = get_option(self::OPTION_POINTS_ON_REDEEM, 'no') === 'yes';

        $total_spent    = $order->get_total();
        $total_discount = $order->get_total_discount();
        $points_redeemed = false;
        foreach ($order->get_coupon_codes() as $code) {
            if (strtolower($code) === 'redeem-points') {
                $points_redeemed = true; break;
            }
        }
        if ((!$allow_points_on_discount && $total_discount > 0) ||
            (!$allow_points_on_redeem && $points_redeemed)) {
            update_post_meta($order_id, '_wc_points_awarded', 1);
            return;
        }

        $points_per_dollar = floatval(get_option(self::OPTION_POINTS_PER_DOLLAR, 0.5));
        $points = floor($total_spent * $points_per_dollar);
        if ($points > 0) {
            $this->add_points($user_id, $points, 'order');
            update_post_meta($order_id, '_wc_points_awarded', $points);
        }
    }

    // Award points for review/rating
    public function award_points_for_review_and_rating($comment_ID, $comment_approved, $commentdata) {
        if ($comment_approved !== 1) return;
        $comment = get_comment($comment_ID); if (!$comment) return;
        if ($comment->comment_type !== 'review' && $comment->comment_type !== '') return;
        $user_id = $comment->user_id; if (!$user_id) return;
        $product_id = $comment->comment_post_ID;
        if (get_post_type($product_id) !== 'product') return;

        $points_review = intval(get_option(self::OPTION_POINTS_REVIEW, 4));
        $points_rating = intval(get_option(self::OPTION_POINTS_RATING, 1));
        $awarded_points = 0;

        $rating = intval(get_comment_meta($comment->comment_ID, 'rating', true));
        if ($rating > 0) {
            $rated_products = get_user_meta($user_id, 'wc_rated_products', true);
            if (!is_array($rated_products)) $rated_products = array();
            if (!in_array($product_id, $rated_products)) {
                $this->add_points($user_id, $points_rating, 'product_rating', $comment_ID, $product_id);
                $rated_products[] = $product_id;
                update_user_meta($user_id, 'wc_rated_products', $rated_products);
                $awarded_points += $points_rating;
            }
        }
        $this->add_points($user_id, $points_review, 'product_review', $comment_ID, $product_id);
        $awarded_points += $points_review;
        add_comment_meta($comment_ID, '_wc_points_awarded', $awarded_points, true);
    }

    // Remove points if review deleted/unapproved
    public function maybe_remove_points_on_review_status_change($comment_id, $new_status) {
        if ($new_status !== 'approve') $this->remove_points_for_comment($comment_id);
    }
    public function maybe_remove_points_on_review_delete($comment_id) {
        $this->remove_points_for_comment($comment_id);
    }
    private function remove_points_for_comment($comment_id) {
        $comment = get_comment($comment_id); if (!$comment) return;
        $user_id = $comment->user_id; if (!$user_id) return;
        $awarded_points = intval(get_comment_meta($comment_id, '_wc_points_awarded', true));
        if ($awarded_points > 0) {
            $current_points = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
            update_user_meta($user_id, 'wc_loyalty_points', max(0, $current_points - $awarded_points));
            delete_comment_meta($comment_id, '_wc_points_awarded');
        }
        $product_id = $comment->comment_post_ID;
        $rating = intval(get_comment_meta($comment_id, 'rating', true));
        if ($rating > 0) {
            $rated_products = get_user_meta($user_id, 'wc_rated_products', true);
            if (is_array($rated_products)) {
                $key = array_search($product_id, $rated_products);
                if ($key !== false) {
                    unset($rated_products[$key]);
                    update_user_meta($user_id, 'wc_rated_products', $rated_products);
                }
            }
        }
    }

    // Signup/init points
    public function initialize_user_points($user_id) {
        add_user_meta($user_id, 'wc_loyalty_points', 0, true);
        add_user_meta($user_id, 'wc_rated_products', array(), true);
        $signup_points = intval(get_option(self::OPTION_POINTS_ON_SIGNUP, 10));
        if ($signup_points > 0) $this->add_points($user_id, $signup_points, 'signup');
    }

    // User meta column in admin
    public function add_user_points_column($columns) {
        $columns['wc_loyalty_points'] = __('Loyalty Points', 'wc-points-rewards');
        return $columns;
    }
    public function show_user_points_column($value, $column_name, $user_id) {
        if ('wc_loyalty_points' === $column_name) {
            $points = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
            return $points;
        }
        return $value;
    }

    // (continue with redemption logic, DOB, birthday, etc.)
        /***********************
     * POINTS REDEMPTION, CART/CHECKOUT ENFORCEMENT
     ***********************/
    public function check_minimum_points_before_redeem() {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();
        $user_points = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
        $min_redeem = intval(get_option(self::OPTION_MINIMUM_REDEEM, 50));
        $cart = WC()->cart;
        if (!$cart) return;
        $has_redeem_coupon = false;
        foreach ($cart->get_applied_coupons() as $coupon_code) {
            if (strtolower($coupon_code) === 'redeem-points') {$has_redeem_coupon = true; break;}
        }
        if ($has_redeem_coupon && $user_points < $min_redeem) {
            $cart->remove_coupon('redeem-points');
            if (is_checkout()) {
                wc_add_notice(__('You are not eligible for redeeming points yet. For more information please see your account or profile page.', 'wc-points-rewards'), 'error');
            } else {
                wc_print_notice(__('You are not eligible for redeeming points yet. For more information please see your account or profile page.', 'wc-points-rewards'), 'error');
            }
        }
    }
    public function enforce_max_points_redeem_cap() {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();
        $user_points = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
        $point_value = floatval(get_option(self::OPTION_POINT_VALUE, 1.0));
        $max_percent = intval(get_option(self::OPTION_MAX_REDEEM_PERCENT, 50));
        $cart = WC()->cart;
        if (!$cart) return;
        $has_redeem_coupon = false;
        foreach ($cart->get_applied_coupons() as $coupon_code) {
            if (strtolower($coupon_code) === 'redeem-points') {$has_redeem_coupon = true; break;}
        }
        if ($has_redeem_coupon) {
            $cart_total = $cart->get_subtotal();
            $max_redeemable = ($max_percent / 100) * $cart_total;
            $available_redeem = $user_points * $point_value;
            if ($available_redeem > $max_redeemable) {
                $cart->remove_coupon('redeem-points');
                $msg = sprintf(__('You can redeem points up to %d%% of your cart total. For more information, please see your profile.', 'wc-points-rewards'), $max_percent);
                if (is_checkout()) {
                    wc_add_notice($msg, 'error');
                } else {
                    wc_print_notice($msg, 'error');
                }
            }
        }
    }

    /***********************
     * DOB FIELD & BIRTHDAY POINTS
     ***********************/
    public function add_dob_field_to_account() {
        $user_id = get_current_user_id();
        $user_dob = get_user_meta($user_id, 'wc_date_of_birth', true);
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_dob"><?php esc_html_e('Date of Birth', 'wc-points-rewards'); ?></label>
            <input type="date" class="woocommerce-Input woocommerce-Input--text input-text" name="account_dob" id="account_dob" value="<?php echo esc_attr($user_dob); ?>" />
        </p>
        <?php
        if (!$user_dob) {
            $dob_points = intval(get_option(self::OPTION_DOB_POINTS_REWARD, 4));
            echo '<div class="woocommerce-info" style="margin-top:10px;">' .
                sprintf(esc_html__('Add your date of birth to receive a birthday gift of %d points every year and start collecting more loyalty points!', 'wc-points-rewards'), $dob_points) .
                '</div>';
        }
    }
    public function save_dob_field_account($user_id) {
        if (isset($_POST['account_dob'])) {
            $dob = sanitize_text_field($_POST['account_dob']);
            $old_dob = get_user_meta($user_id, 'wc_date_of_birth', true);
            if ($dob && (!$old_dob || $old_dob !== $dob)) {
                update_user_meta($user_id, 'wc_date_of_birth', $dob);
                $dob_points_enabled = get_option(self::OPTION_ENABLE_DOB_POINTS, 'yes') === 'yes';
                $dob_points_reward = intval(get_option(self::OPTION_DOB_POINTS_REWARD, 4));
                $dob_rewarded = get_user_meta($user_id, 'wc_dob_points_rewarded', true);
                if ($dob_points_enabled && !$dob_rewarded && $dob_points_reward > 0) {
                    $this->add_points($user_id, $dob_points_reward, 'dob');
                    update_user_meta($user_id, 'wc_dob_points_rewarded', 1);
                }
            }
        }
    }
    public function maybe_award_dob_points_admin($user_id) {
        $dob = get_user_meta($user_id, 'wc_date_of_birth', true);
        $dob_points_enabled = get_option(self::OPTION_ENABLE_DOB_POINTS, 'yes') === 'yes';
        $dob_points_reward = intval(get_option(self::OPTION_DOB_POINTS_REWARD, 4));
        $dob_rewarded = get_user_meta($user_id, 'wc_dob_points_rewarded', true);
        if ($dob && $dob_points_enabled && !$dob_rewarded && $dob_points_reward > 0) {
            $this->add_points($user_id, $dob_points_reward, 'dob');
            update_user_meta($user_id, 'wc_dob_points_rewarded', 1);
        }
    }

    /***********************
     * BIRTHDAY DAILY CRON, EMAIL, EXPIRY
     ***********************/
    public function maybe_schedule_birthday_cron() {
        if (!wp_next_scheduled('wc_points_rewards_daily_birthday_check')) {
            wp_schedule_event(time(), 'daily', 'wc_points_rewards_daily_birthday_check');
        }
    }
    public function birthday_points_daily_check() {
        $enabled = get_option(self::OPTION_BIRTHDAY_POINTS_ENABLED, 'yes') === 'yes';
        if (!$enabled) return;
        $birthday_points = intval(get_option(self::OPTION_BIRTHDAY_POINTS_AMOUNT, 10));
        if ($birthday_points <= 0) return;
        $today = date('m-d');
        $users = get_users(array('meta_key' => 'wc_date_of_birth'));
        foreach ($users as $user) {
            $dob = get_user_meta($user->ID, 'wc_date_of_birth', true);
            if (!$dob) continue;
            $dob_parts = explode('-', $dob);
            if (count($dob_parts) !== 3) continue;
            $dob_mmdd = $dob_parts[1] . '-' . $dob_parts[2];
            if ($dob_mmdd !== $today) continue;
            $year = date('Y');
            $key = "wc_birthday_points_year_" . $year;
            $already_awarded = get_user_meta($user->ID, $key, true);
            if (!$already_awarded) {
                $this->add_points($user->ID, $birthday_points, 'birthday');
                update_user_meta($user->ID, $key, 1);
                $mailer = WC()->mailer();
                $email_class = $mailer->emails['WC_Email_Birthday_Points'];
                $email_class->trigger($user->ID, $birthday_points);
                $expire_time = strtotime('+6 months');
                wp_schedule_single_event($expire_time, 'wc_points_rewards_expire_birthday_points', array($user->ID, $year));
            }
        }
    }
    public function expire_birthday_points($user_id, $year) {
        $log = get_user_meta($user_id, 'wc_birthday_points_log', true);
        if (!is_array($log) || empty($log)) return;
        $birthday_points = intval(get_option(self::OPTION_BIRTHDAY_POINTS_AMOUNT, 10));
        foreach ($log as $index => $entry) {
            $awarded_year = substr($entry['awarded'], 0, 4);
            if ($awarded_year == $year && !$entry['expired']) {
                $current_points = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
                update_user_meta($user_id, 'wc_loyalty_points', max(0, $current_points - $birthday_points));
                $log[$index]['expired'] = true;
            }
        }
        update_user_meta($user_id, 'wc_birthday_points_log', $log);
    }

    /***********************
     * BIRTHDAY EMAIL
     ***********************/
    public function register_birthday_email_class($email_classes) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-email-birthday-points.php';
        $email_classes['WC_Email_Birthday_Points'] = new WC_Email_Birthday_Points();
        return $email_classes;
    }
    public function include_birthday_email_template() { }

    /***********************
     * PRODUCT PAGE PROMO BOX
     ***********************/
    public function display_promo_points_box() {
        if (!is_product()) return;
        $enabled = get_option(self::OPTION_PROMO_BOX_ENABLED, 'yes');
        if ($enabled !== 'yes') return;
        global $product;
        if (!$product instanceof WC_Product) return;
        $price = floatval($product->get_price());
        $points_per_dollar = floatval(get_option(self::OPTION_POINTS_PER_DOLLAR, 0.5));
        $point_value = floatval(get_option(self::OPTION_POINT_VALUE, 1.0));
        $show_value = get_option(self::OPTION_PROMO_BOX_SHOW_VALUE, 'yes') === 'yes';
        $custom_text = get_option(self::OPTION_PROMO_BOX_TEXT, '');
        $points = floor($price * $points_per_dollar);
        $money = number_format($points * $point_value, 2);
        echo '<div class="wc-points-promo-box">';
        if (!empty($custom_text)) {
            $msg = $custom_text;
            $msg = str_replace('{points}', $points, $msg);
            $msg = str_replace('{value}', '$' . $money, $msg);
            $msg = str_replace('{price}', '$' . $price, $msg);
            echo wpautop(wp_kses_post($msg));
        } else {
            if ($show_value) {
                echo sprintf(
                    esc_html__('Earn %d points (worth $%s) with this purchase!', 'wc-points-rewards'),
                    $points,
                    $money
                );
            } else {
                echo sprintf(
                    esc_html__('Earn %d points with this purchase!', 'wc-points-rewards'),
                    $points
                );
            }
        }
        echo '</div>';
    }
    public function enqueue_promo_box_styles() {
        wp_add_inline_style(
            'woocommerce-inline',
            '.wc-points-promo-box {
                background: #f9f6e7;
                border: 1px solid #ecd97c;
                color: #b49c29;
                font-size: 1.1em;
                margin: 16px 0 0 0;
                padding: 18px 20px 16px 20px;
                border-radius: 6px;
                font-weight: bold;
                text-align: center;
            }'
        );
    }

    /***********************
     * POINTS REDEMPTION VIA COUPON
     ***********************/
    public function track_points_redemption($coupon_code) {
        if (strtolower($coupon_code) === 'redeem-points' && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $cart = WC()->cart;
            $discount_total = 0;
            foreach ($cart->get_applied_coupons() as $code) {
                if (strtolower($code) === 'redeem-points') {
                    foreach ($cart->get_coupons() as $coupon) {
                        if (strtolower($coupon->get_code()) === 'redeem-points') {
                            $discount_total += $coupon->get_amount();
                        }
                    }
                }
            }
            $old = floatval(get_user_meta($user_id, 'wc_points_redeemed_value', true));
            update_user_meta($user_id, 'wc_points_redeemed_value', $old + $discount_total);
        }
    }

    /***********************
     * CSV EXPORT
     ***********************/
    public function handle_loyalty_csv_export() {
        if (!is_admin() || !isset($_GET['export_loyalty_csv']) || $_GET['export_loyalty_csv'] != 1) return;
        if (!current_user_can('manage_woocommerce')) return;
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=users-loyalty-summary.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array(
            'User Display Name', 'User ID', 'Email Address', 'Signup Points', 'Birthday Points', 'Rating Points', 'Review Points', 'Order Points', 'Point Discount', 'Points Balance'
        ));
        $paged = 1; $users_per_page = 100;
        do {
            $user_query = new WP_User_Query(array(
                'number' => $users_per_page,
                'paged'  => $paged,
                'orderby'=> 'user_email',
                'order'  => 'ASC',
                'fields' => array('ID', 'user_email', 'display_name')
            ));
            $users = $user_query->get_results();
            foreach ($users as $user) {
                $user_id = $user->ID;
                $signup_points = intval(get_user_meta($user_id, 'wc_signup_points', true));
                $birthday_points = intval(get_user_meta($user_id, 'wc_birthday_total_points', true));
                if (!$birthday_points) $birthday_points = 0;
                $rating_points = intval(get_user_meta($user_id, 'wc_rating_points', true));
                $review_points = intval(get_user_meta($user_id, 'wc_review_points', true));
                $order_points = intval(get_user_meta($user_id, 'wc_order_points', true));
                $points_redeemed = floatval(get_user_meta($user_id, 'wc_points_redeemed_value', true));
                $points_balance = intval(get_user_meta($user_id, 'wc_loyalty_points', true));
                fputcsv($out, array(
                    $user->display_name,
                    $user_id,
                    $user->user_email,
                    $signup_points,
                    $birthday_points,
                    $rating_points,
                    $review_points,
                    $order_points,
                    number_format($points_redeemed,2),
                    $points_balance
                ));
            }
            $paged++;
        } while (count($users) === $users_per_page);
        fclose($out);
        exit;
    }

}

WC_Points_And_Rewards::instance();