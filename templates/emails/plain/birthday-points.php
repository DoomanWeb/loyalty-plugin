<?php
/**
 * Birthday Points Email (plain text)
 * @package WC_Points_Rewards/Templates/Emails
 */
if ( ! defined( 'ABSPATH' ) ) exit;
echo "Happy Birthday, {$user_display_name}!\n\n";
echo sprintf( __( 'As a special birthday treat, you have received %d loyalty points! You can use these points for your next purchases within 6 months.', 'wc-points-rewards' ), intval( $points ) );
echo "\n";