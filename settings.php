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
            add_meta_box('m4w_wcg_meta_box', 'One time coupons', array($this, 'm4w_wcg_add_meta_box'), 'shop_coupon', 'side', 'high');
        }

        public function enqueue_scripts($hook) {
            global $AR_ONE_TIME_COUPON_ENABLED, $AR_ONE_TIME_COUPON_PREFIX;

            global $post;

            if (($hook !== 'post.php' && $hook !== 'post-new.php') || !$post || $post->post_type !== 'shop_coupon') {
                return;
            }

            wp_enqueue_script(
                'm4w-woo-coupon-gen-admin',
                plugins_url('/assets/js/admin_scripts.js', __FILE__),
                ['jquery'],
                '1.3.0',
                true
            );

            wp_enqueue_style(
                'm4w-woo-coupon-gen-admin-css',
                plugins_url('/assets/css/admin_styles.css', __FILE__),
                [],
                '1.3.0'
            );

            wp_localize_script('m4w-woo-coupon-gen-admin', 'woo_copoun_generator', [
                'coupon_enabled_id' => $AR_ONE_TIME_COUPON_ENABLED,
                'coupon_prefix_id' => $AR_ONE_TIME_COUPON_PREFIX,
                'mailpoet_shortcode' => ar_mailpoet_coupon_gen_shortcode(),
                'fluentcrm_smartcode' => ar_fluentcrm_coupon_gen_smartcode(),
                'post_id' => $post->ID,
                'remove_nonce' => wp_create_nonce('ar_remove_child_coupons_' . $post->ID),
            ]);
        }

        public function m4w_wcg_add_meta_box()
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

        public function ajax_remove_child_coupons() {
            check_ajax_referer('ar_remove_child_coupons_' . $_POST['post_id'], 'nonce');

            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => 'Insufficient permissions.']);
                return;
            }

            $parent_id = intval($_POST['post_id']);
            if (!$parent_id) {
                wp_send_json_error(['message' => 'Invalid parent coupon ID.']);
                return;
            }

            $this->link_legacy_child_coupons($parent_id, get_the_title($parent_id));

            global $wpdb;
            $parent_coupon_keys = "'" . implode("','", ar_parent_coupon_meta_keys()) . "'";
            $child_ids = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM $wpdb->posts
                WHERE post_type = 'shop_coupon'
                AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM $wpdb->postmeta
                    WHERE meta_key IN ($parent_coupon_keys)
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

        private function link_legacy_child_coupons($parent_id, $parent_title) {
            if ($parent_title === '') {
                return;
            }

            global $wpdb;

            $candidates = $wpdb->get_results("
                SELECT p.ID, p.post_content
                FROM $wpdb->posts p
                WHERE p.post_type = 'shop_coupon'
                AND p.post_status = 'publish'
                AND p.post_content LIKE 'Generated from%'
                AND NOT EXISTS (
                    SELECT 1 FROM $wpdb->postmeta pm
                    WHERE pm.post_id = p.ID
                    AND pm.meta_key IN ('" . M4W_WCG_PARENT_COUPON_KEY . "', '" . M4W_WCG_LEGACY_PARENT_COUPON_KEY . "')
                )
            ");

            foreach ($candidates as $candidate) {
                if (!self::description_references_parent($candidate->post_content, $parent_title)) {
                    continue;
                }

                update_post_meta($candidate->ID, M4W_WCG_PARENT_COUPON_KEY, $parent_id);
            }
        }

        private static function extract_parent_title_from_content($content) {
            $content = trim($content);

            $prefix = 'Generated from ';
            if (strncmp($content, $prefix, strlen($prefix)) !== 0) {
                return null;
            }

            $rest = substr($content, strlen($prefix));

            $parent_coupon = 'parent coupon ';
            if (strncmp($rest, $parent_coupon, strlen($parent_coupon)) === 0) {
                $rest = substr($rest, strlen($parent_coupon));
            }

            $rest = trim($rest);
            if ($rest !== '' && $rest[0] === '"') {
                $rest = substr($rest, 1);
            }

            $end = strpos($rest, ' " for ');
            if ($end === false) {
                $end = strpos($rest, ' for ');
            }
            if ($end !== false) {
                $rest = substr($rest, 0, $end);
            }

            $rest = trim($rest);
            if (substr($rest, -1) === '"') {
                $rest = substr($rest, 0, -1);
            }

            return trim($rest);
        }

        private static function description_references_parent($content, $parent_title) {
            return self::extract_parent_title_from_content($content) === $parent_title;
        }
    }
}

function ar_key($id){
    return '_ar_' . $id;
}

function ar_parent_coupon_meta_keys(){
    return [M4W_WCG_PARENT_COUPON_KEY, M4W_WCG_LEGACY_PARENT_COUPON_KEY];
}

new WooCommerce_Coupon_Generator_Settings();
?>
