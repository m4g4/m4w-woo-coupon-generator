<?php
/**
 * Plugin Name: M4W Woo Coupon Generator
 * Description: Dynamically generates unique WooCommerce coupons for email marketing tools. Supports FluentCRM and MailPoet.
 * Version:     1.3.0
 * Author:      m4g4
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Requires at least: 5.6
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// Parent-coupon link meta keys. New children store the m4w_wcg key; the legacy
// key is still honored so children created by older plugin versions keep working.
if ( ! defined( 'M4W_WCG_PARENT_COUPON_KEY' ) ) {
	define( 'M4W_WCG_PARENT_COUPON_KEY', '_m4w_wcg_parent_coupon_id' );
}
if ( ! defined( 'M4W_WCG_LEGACY_PARENT_COUPON_KEY' ) ) {
	define( 'M4W_WCG_LEGACY_PARENT_COUPON_KEY', '_ar_parent_coupon_id' );
}

require_once __DIR__ . '/fluent_crm_coupon_generator.php';
require_once __DIR__ . '/mailpoet_coupon_generator.php';
require_once __DIR__ . '/settings.php';

?>