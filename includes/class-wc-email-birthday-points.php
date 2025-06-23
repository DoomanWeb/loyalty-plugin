<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'WC_Email_Birthday_Points' ) ) :

class WC_Email_Birthday_Points extends WC_Email {
    public function __construct() {
        $this->id = 'birthday_points';
        $this->title = __( 'Birthday Points Gift', 'wc-points-rewards' );
        $this->description = __( 'This email is sent to customers on their birthday when they are awarded loyalty points.', 'wc-points-rewards' );
        $this->template_html  = 'emails/birthday-points.php';
        $this->template_plain = 'emails/plain/birthday-points.php';
        $this->customer_email = true;
        $this->heading        = __( 'Happy Birthday!', 'wc-points-rewards' );
        $this->subject        = __( 'Happy Birthday! You have received [[points]] loyalty points', 'wc-points-rewards' );
        add_action( 'wc_points_rewards_send_birthday_email_notification', array( $this, 'trigger' ), 10, 2 );
        parent::__construct();
    }
    public function trigger( $user_id, $points ) {
        if ( ! $user_id || ! $points ) { return; }
        $user = get_userdata( $user_id ); if ( ! $user ) return;
        $this->recipient = $user->user_email;
        $this->points = $points;
        $this->user_display_name = $user->display_name;
        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }
    public function get_subject() {
        $subject = $this->format_string( $this->subject );
        $subject = str_replace('[[points]]', $this->points, $subject);
        return apply_filters( 'woocommerce_email_subject_' . $this->id, $subject, $this->object );
    }
    public function get_content_html() {
        ob_start();
        wc_get_template(
            $this->template_html,
            array(
                'email_heading' => $this->get_heading(),
                'points'        => $this->points,
                'user_display_name' => $this->user_display_name,
                'email'         => $this,
            ),
            '',
            plugin_dir_path(__FILE__) . '../templates/'
        );
        return ob_get_clean();
    }
    public function get_content_plain() {
        ob_start();
        wc_get_template(
            $this->template_plain,
            array(
                'email_heading' => $this->get_heading(),
                'points'        => $this->points,
                'user_display_name' => $this->user_display_name,
                'email'         => $this,
            ),
            '',
            plugin_dir_path(__FILE__) . '../templates/'
        );
        return ob_get_clean();
    }
}
endif;