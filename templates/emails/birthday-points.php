<?php
/**
 * Birthday Points Email (HTML)
 * @package WC_Points_Rewards/Templates/Emails
 */
if ( ! defined( 'ABSPATH' ) ) exit;
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( __( 'Happy Birthday, %s!', 'wc-points-rewards' ), esc_html( $user_display_name ) ); ?></p>
<p><?php printf( __( 'As a special birthday treat, you have received <strong>%d loyalty points</strong>! You can use these points for your next purchases within 6 months.', 'wc-points-rewards' ), intval( $points ) ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>