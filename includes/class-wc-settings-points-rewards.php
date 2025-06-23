<?php

if (!defined('ABSPATH')) exit;

if (!class_exists('WC_Settings_Points_Rewards')) :

class WC_Settings_Points_Rewards extends WC_Settings_Page {

    public function __construct() {
        $this->id    = 'points_rewards';
        $this->label = __('Points and Rewards', 'wc-points-rewards');

        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_page'), 50);
        add_action('woocommerce_settings_' . $this->id, array($this, 'output'));
        add_action('woocommerce_settings_save_' . $this->id, array($this, 'save'));
    }

    public function get_settings() {
        $settings = array(
            array(
                'name' => __('Points and Rewards Settings', 'wc-points-rewards'),
                'type' => 'title',
                'id'   => 'wc_points_rewards_section_title'
            ),
            array(
                'name' => __('Points per $1 spent', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('How many points are earned per $1 spent? Default: 0.5', 'wc-points-rewards'),
                'id'   => 'wc_points_per_dollar',
                'default' => '0.5',
                'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            ),
            array(
                'name' => __('Monetary Value of Each Point ($)', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('How much is each point worth? Default: $1', 'wc-points-rewards'),
                'id'   => 'wc_point_value',
                'default' => '1',
                'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            ),
            array(
                'name' => __('Points for Product Review', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('How many points should be awarded for a review? Default: 4', 'wc-points-rewards'),
                'id'   => 'wc_points_for_review',
                'default' => '4',
                'custom_attributes' => array('step' => '1', 'min' => '0'),
            ),
            array(
                'name' => __('Points for Product Rating', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('How many points should be awarded for a product rating? Default: 1', 'wc-points-rewards'),
                'id'   => 'wc_points_for_rating',
                'default' => '1',
                'custom_attributes' => array('step' => '1', 'min' => '0'),
            ),
            array(
                'name' => __('Minimum Points Required to Redeem', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('Users must have at least this many points to redeem. Default: 50', 'wc-points-rewards'),
                'id'   => 'wc_points_minimum_redeem',
                'default' => '50',
                'custom_attributes' => array('step' => '1', 'min' => '1'),
            ),
            array(
                'name' => __('Maximum Redeemable per Order (%)', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('Set the maximum percent of the cart total that points can cover. Default: 50', 'wc-points-rewards'),
                'id'   => 'wc_points_max_redeem_percent',
                'default' => '50',
                'custom_attributes' => array('step' => '1', 'min' => '1', 'max' => '100'),
            ),
            array(
                'name' => __('Award Points When Coupon Discount Applied?', 'wc-points-rewards'),
                'type' => 'select',
                'desc' => __('Allow customers to collect points when they use a coupon or discount? Default: Yes', 'wc-points-rewards'),
                'id'   => 'wc_points_on_discount',
                'default' => 'yes',
                'options' => array('yes' => __('Yes', 'wc-points-rewards'), 'no'  => __('No', 'wc-points-rewards')),
            ),
            array(
                'name' => __('Award Points When Redeeming Points?', 'wc-points-rewards'),
                'type' => 'select',
                'desc' => __('Allow customers to collect points when they redeem points? Default: No', 'wc-points-rewards'),
                'id'   => 'wc_points_on_redeem',
                'default' => 'no',
                'options' => array('yes' => __('Yes', 'wc-points-rewards'), 'no'  => __('No', 'wc-points-rewards')),
            ),
            array(
                'name' => __('Signup Points Reward', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('How many points to award when a user signs up? Default: 10', 'wc-points-rewards'),
                'id'   => 'wc_points_on_signup',
                'default' => '10',
                'custom_attributes' => array('step' => '1', 'min' => '0'),
            ),
            array(
                'name' => __('Enable Date of Birth Points Reward', 'wc-points-rewards'),
                'type' => 'select',
                'desc' => __('Allow users to receive points when they provide their date of birth? Default: Yes', 'wc-points-rewards'),
                'id'   => 'wc_enable_dob_points',
                'default' => 'yes',
                'options' => array('yes' => __('Yes', 'wc-points-rewards'), 'no'  => __('No', 'wc-points-rewards')),
            ),
            array(
                'name' => __('Date of Birth Points Reward', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('How many points to award when a user enters their date of birth? Default: 4', 'wc-points-rewards'),
                'id'   => 'wc_dob_points_reward',
                'default' => '4',
                'custom_attributes' => array('step' => '1', 'min' => '0'),
            ),
            array(
                'name' => __('Birthday Points Settings', 'wc-points-rewards'),
                'type' => 'title',
                'id' => 'wc_birthday_points_section'
            ),
            array(
                'name' => __('Enable Birthday Points', 'wc-points-rewards'),
                'type' => 'select',
                'desc' => __('Award birthday points to users on their birthday and send them an email. Default: Yes', 'wc-points-rewards'),
                'id'   => 'wc_birthday_points_enabled',
                'default' => 'yes',
                'options' => array('yes' => __('Yes', 'wc-points-rewards'), 'no'  => __('No', 'wc-points-rewards')),
            ),
            array(
                'name' => __('Birthday Points Amount', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('How many points to award on user\'s birthday? Default: 10', 'wc-points-rewards'),
                'id'   => 'wc_birthday_points_amount',
                'default' => '10',
                'custom_attributes' => array('step' => '1', 'min' => '0'),
            ),
            array(
                'name' => __('Product Page Promotion Box', 'wc-points-rewards'),
                'type' => 'title',
                'id' => 'wc_points_promo_box_section'
            ),
            array(
                'name' => __('Show Promotion Box on Product Page', 'wc-points-rewards'),
                'type' => 'select',
                'desc' => __('Display a promotional box on single product pages showing points earned. Default: Yes', 'wc-points-rewards'),
                'id'   => 'wc_points_promo_box_enabled',
                'default' => 'yes',
                'options' => array('yes' => __('Yes', 'wc-points-rewards'), 'no'  => __('No', 'wc-points-rewards')),
            ),
            array(
                'name' => __('Show Monetary Value in Promo Box', 'wc-points-rewards'),
                'type' => 'select',
                'desc' => __('Show the money value of points in the promo box. Default: Yes', 'wc-points-rewards'),
                'id'   => 'wc_points_promo_box_show_value',
                'default' => 'yes',
                'options' => array('yes' => __('Yes', 'wc-points-rewards'), 'no'  => __('No', 'wc-points-rewards')),
            ),
            array(
                'name' => __('Promotion Box Custom Message', 'wc-points-rewards'),
                'type' => 'textarea',
                'desc' => __('Customize the Product Page Promotion Box text. You can use: {points}, {value}, {price} as placeholders.', 'wc-points-rewards'),
                'id'   => 'wc_points_promo_box_text',
                'default' => '',
            ),
            array(
                'name' => __('Redeem Points UI Locations', 'wc-points-rewards'),
                'type' => 'multiselect',
                'desc' => __('Choose where the Redeem Points option appears for customers.', 'wc-points-rewards'),
                'id'   => 'wc_redeem_points_ui_locations',
                'default' => array('cart','checkout'),
                'options' => array(
                    'mini_cart' => __('Mini-Cart', 'wc-points-rewards'),
                    'cart'      => __('Cart Page', 'wc-points-rewards'),
                    'checkout'  => __('Checkout Page', 'wc-points-rewards'),
                    'order_summary' => __('Order Summary', 'wc-points-rewards'),
                ),
                'class' => 'wc-enhanced-select',
            ),
            array(
                'name' => __('Redeem Points Cart Footnote', 'wc-points-rewards'),
                'type' => 'textarea',
                'desc' => __('Custom footnote displayed below Redeem Points UI: Use {point_value} for $ per point and {max_percent} for max redeemable percent. Example: "1 Point Spent=${point_value} Saving, and you can redeem up to {max_percent}% of your cart total."', 'wc-points-rewards'),
                'id'   => 'wc_redeem_points_cart_footnote',
                'default' => '1 Point Spent=${point_value} Saving, and you can redeem up to {max_percent}% of your cart total.',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_points_rewards_section_end'
            ),
            array(
                'name' => __('Loyalty Points & Savings Tab', 'wc-points-rewards'),
                'type' => 'title',
                'id' => 'wc_loyalty_points_savings_section'
            ),
            array(
                'name' => __('Show Loyalty Points & Savings Tab', 'wc-points-rewards'),
                'type' => 'checkbox',
                'desc' => __('Enable to show Loyalty Points & Savings tab on user account page.', 'wc-points-rewards'),
                'id'   => 'wc_loyalty_points_savings_enable',
                'default' => 'yes',
            ),
            array(
                'name' => __('Loyalty Points & Savings Tab Title', 'wc-points-rewards'),
                'type' => 'text',
                'desc' => __('The title of the Loyalty Points & Savings tab.', 'wc-points-rewards'),
                'id'   => 'wc_loyalty_points_savings_title',
                'default' => __('Loyalty Points & Savings', 'wc-points-rewards'),
            ),
            array(
                'name' => __('Loyalty Points & Savings Description', 'wc-points-rewards'),
                'type' => 'textarea',
                'desc' => __('Description shown on the Loyalty Points & Savings tab. Supports HTML.', 'wc-points-rewards'),
                'id'   => 'wc_loyalty_points_savings_desc',
                'default' => '<p>Earn loyalty points with every purchase and redeem them for discounts!</p>',
                'css' => 'min-height:120px;',
            ),
            array(
                'name' => __('Third Party Orders Feature', 'wc-points-rewards'),
                'type' => 'title',
                'id' => 'wc_third_party_orders_section'
            ),
            array(
                'name' => __('Third Party Orders Description', 'wc-points-rewards'),
                'type' => 'textarea',
                'desc' => __('Description shown on the “My Third Party Orders” tab. Supports HTML.', 'wc-points-rewards'),
                'id'   => 'wc_third_party_orders_desc',
                'default' => '<p>Enter your delivery app order code here to earn points or receive a discount coupon!</p>',
                'css' => 'min-height:120px;',
            ),
            array(
                'name' => __('Show Discount Coupon Option', 'wc-points-rewards'),
                'type' => 'checkbox',
                'desc' => __('Enable to allow users to choose a discount coupon instead of points for third-party orders.', 'wc-points-rewards'),
                'id'   => 'wc_third_party_coupon_enable',
                'default' => 'yes',
            ),
            array(
                'name' => __('Third Party Points Collected Message', 'wc-points-rewards'),
                'type' => 'text',
                'desc' => __('Message shown after user chooses to collect points. Use {points} and {value}.', 'wc-points-rewards'),
                'id'   => 'wc_third_party_points_collected_msg',
                'default' => 'Hooray! {points} Points worth {value} have been added to your account.',
            ),
            array(
                'name' => __('Third Party Coupon Sent Message', 'wc-points-rewards'),
                'type' => 'text',
                'desc' => __('Message shown after user chooses to get a discount coupon.', 'wc-points-rewards'),
                'id'   => 'wc_third_party_coupon_sent_msg',
                'default' => 'Great! Check your inbox — we’ve sent you the coupon code.',
            ),
            array(
                'name' => __('Coupon Code Prefix', 'wc-points-rewards'),
                'type' => 'text',
                'desc' => __('Prefix for all generated third-party coupons (default: TH-)', 'wc-points-rewards'),
                'id'   => 'wc_third_party_coupon_prefix',
                'default' => 'TH-',
            ),
            array(
                'name' => __('Coupon Validity (days)', 'wc-points-rewards'),
                'type' => 'number',
                'desc' => __('How many days should generated coupons be valid for?', 'wc-points-rewards'),
                'id'   => 'wc_third_party_coupon_validity',
                'default' => '30',
                'custom_attributes' => array('step' => '1', 'min' => '1'),
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_third_party_orders_section_end'
            ),
        );
        return apply_filters('wc_points_rewards_settings', $settings);
    }

    public function output() {
        $settings = $this->get_settings();
        WC_Admin_Settings::output_fields($settings);
    }

    public function save() {
        $settings = $this->get_settings();
        WC_Admin_Settings::save_fields($settings);
    }
}
endif;