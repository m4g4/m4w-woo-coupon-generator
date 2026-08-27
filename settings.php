<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$AR_ONE_TIME_COUPON_ENABLED = 'ar_custom_coupon_gen_enabled';
$AR_ONE_TIME_COUPON_PREFIX = 'ar_custom_coupon_gen_prefix';

if ( ! class_exists( 'WooCommerce_Coupon_Generator_Settings' ) ) {

	class WooCommerce_Coupon_Generator_Settings {

		public function __construct() {
            add_action('save_post', array($this, 'save_postdata'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_ajax_ar_remove_child_coupons', [$this, 'ajax_remove_child_coupons']);
        }

        public function add_meta_boxes() {
            add_meta_box('ar_meta_coupon_box', 'One time coupons', array($this, 'ar_add_meta_coupon_box'), 'shop_coupon', 'side', 'high');
        }

        public function enqueue_scripts($hook) {
            global $AR_ONE_TIME_COUPON_ENABLED, $AR_ONE_TIME_COUPON_PREFIX;

            global $post;

            if (($hook !== 'post.php' && $hook !== 'post-new.php') || !$post || $post->post_type !== 'shop_coupon') {
                return;
            }

            wp_enqueue_script(
                'woo-coupon-gen-admin',
                plugins_url('/assets/js/admin_scripts.js', __FILE__),
                ['jquery'],
                '0.1.0',
                true
            );

            wp_enqueue_style(
                'woo-coupon-gen-admin-css',
                plugins_url('/assets/css/admin_styles.css', __FILE__),
                [],
                '0.1.0'
            );

            wp_localize_script('woo-coupon-gen-admin', 'woo_copoun_generator', [
                'coupon_enabled_id' => $AR_ONE_TIME_COUPON_ENABLED,
                'coupon_prefix_id' => $AR_ONE_TIME_COUPON_PREFIX,
                'mailpoet_shortcode' => ar_mailpoet_coupon_gen_shortcode(),
                'fluentcrm_smartcode' => ar_fluentcrm_coupon_gen_smartcode(),
                'post_id' => $post->ID,
                'remove_nonce' => wp_create_nonce('ar_remove_child_coupons_' . $post->ID),
            ]);
        }

        public function ar_add_meta_coupon_box()
        {
            global $post, $AR_ONE_TIME_COUPON_ENABLED, $AR_ONE_TIME_COUPON_PREFIX;

            $one_time_coupon_enabled = $this->load_checkbox_value($post->ID, $AR_ONE_TIME_COUPON_ENABLED);
            $one_time_coupon_prefix = get_post_meta($post->ID, ar_key($AR_ONE_TIME_COUPON_PREFIX), true);
            if (empty($one_time_coupon_prefix))
                $one_time_coupon_prefix = "coupon_";

            $post_title = get_the_title($post->ID);

            include(dirname(__FILE__) . '/meta_box_coupon.php');
        }

        public function load_checkbox_value($post_id, $id)
        {
            $value = get_post_meta($post_id, ar_key($id), true);
            if ($value == 'true') {
                $value = 'checked="checked"';
            } else {
                $value = '';
            }

            return $value;
        }

        public function save_postdata($post_id)
        {
            global $AR_ONE_TIME_COUPON_ENABLED, $AR_ONE_TIME_COUPON_PREFIX;

            // Security check
            if (!isset($_POST['one_time_coupon_nonce'])) {
                return $post_id;
            }
            if (!wp_verify_nonce($_POST['one_time_coupon_nonce'], 'one_time_coupon')) {
                return $post_id;
            }
            
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return $post_id;
            }
            $this->save_checkbox_value($post_id, $AR_ONE_TIME_COUPON_ENABLED);
            $this->save_input_value($post_id, $AR_ONE_TIME_COUPON_PREFIX);

            return $post_id;
        }

        public function save_checkbox_value($post_id, $id)
        {
            if (isset($_POST[$id]) && $_POST[$id] == 'true') {
                update_post_meta($post_id, ar_key($id), 'true');
            } else {
                delete_post_meta($post_id, ar_key($id));
            }
        }

        public function save_input_value($post_id, $id)
        {
            if (isset($_POST[$id])) {
                update_post_meta($post_id, ar_key($id), $_POST[$id]);
            } else {
                delete_post_meta($post_id, ar_key($id));
            }
        }
    }
}

function ar_key($id){
    return '_ar_' . $id;
}

public function ajax_remove_child_coupons() {
    check_ajax_referer('ar_remove_child_coupons_' . $_POST['post_id'], 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions.');
        return;
    }

    $parent_id = intval($_POST['post_id']);
    if (!$parent_id) {
        wp_send_json_error('Invalid parent coupon ID.');
        return;
    }

    global $wpdb;
    $child_ids = $wpdb->get_col($wpdb->prepare("
        SELECT ID FROM $wpdb->posts
        WHERE post_type = 'shop_coupon'
        AND post_status = 'publish'
        AND ID IN (
            SELECT post_id FROM $wpdb->postmeta
            WHERE meta_key = '_ar_parent_coupon_id'
            AND meta_value = %d
        )
    ", $parent_id));

    $count = 0;
    foreach ($child_ids as $child_id) {
        $result = wp_delete_post($child_id, true);
        if ($result !== false) {
            $count++;
        }
    }

    wp_send_json_success(['count' => $count]);
}

new WooCommerce_Coupon_Generator_Settings();
?>
