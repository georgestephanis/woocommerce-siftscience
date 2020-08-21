<?php
/**
 * WordPress Plugin: SiftScience for WooCommerce
 *
 * @author Nabeel Sulieman, Rami Jamleh, Lucas Svec
 * @package sift-for-woocommerce
 * @license GPL2
 *
 * @wordpress-plugin
 * Plugin Name: Sift for WooCommerce
 * Plugin URI: https://github.com/Fermiac/woocommerce-siftscience
 * Description: Get a handle on fraud with Sift - a modern approach to fraud prevention that uses machine learning.
 * Author: Nabeel Sulieman, Rami Jamleh, Lukas Svec
 * Version: 1.1.0
 * Author URI: https://github.com/Fermiac/woocommerce-siftscience/wiki
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true )
	&& ! class_exists( 'WCSiftScience' ) ) :

	// Make sure session is started as soon as possible.
	if ( ! headers_sent() && session_status() !== PHP_SESSION_ACTIVE ) {
		session_start();
	}

	require_once dirname( __FILE__ ) . '/class-wc-siftscience-dependencies.php';
	WC_SiftScience_Dependencies::require_all_php_files( dirname( __FILE__ ) . '/includes' );

	/**
	 * Class WC_SiftScience_Plugin Main class for the Sift plugin
	 */
	class WC_SiftScience_Plugin {
		public const PLUGIN_VERSION = '1.1.0';

		/**
		 * Initialize all the classes and hook into everything
		 */
		public function run() {
			$deps = new WC_SiftScience_Dependencies();

			/** @var WC_SiftScience_Options $o */
			$o = $deps->get( 'WC_SiftScience_Options' );

			/** @var WC_SiftScience_Logger $l */
			$l = $deps->get( 'WC_SiftScience_Logger' );

			/** @var WC_SiftScience_Stats $s */
			$s = $deps->get( 'WC_SiftScience_Stats' );

			// Wrap all the classes in error catcher.
			$events = new WC_SiftScience_Instrumentation( $deps->get( 'WC_SiftScience_Events' ), $l, $s );
			$orders = new WC_SiftScience_Instrumentation( $deps->get( 'WC_SiftScience_Orders' ), $l, $s );
			$admin  = new WC_SiftScience_Instrumentation( $deps->get( 'WC_SiftScience_Admin' ), $l, $s );
			$api    = new WC_SiftScience_Instrumentation( $deps->get( 'WC_SiftScience_Api' ), $l, $s );
			$stripe = new WC_SiftScience_Instrumentation( $deps->get( 'WC_SiftScience_Stripe' ), $l, $s );

			// Admin hooks.
			add_filter( 'woocommerce_settings_tabs_array', array( $admin, 'add_settings_page' ), 30 );
			add_filter( 'woocommerce_sections_siftsci', array( $admin, 'get_sections' ) );
			add_action( 'woocommerce_settings_siftsci', array( $admin, 'output_settings_fields' ) );
			add_action( 'woocommerce_settings_save_siftsci', array( $admin, 'save_settings' ) );
			add_action( 'admin_notices', array( $admin, 'settings_notice' ) );

			// Order hooks.
			add_filter( 'manage_edit-shop_order_columns', array( $orders, 'create_header' ), 100 );
			add_action( 'manage_shop_order_posts_custom_column', array( $orders, 'create_row' ), 11 );
			add_action( 'add_meta_boxes', array( $orders, 'add_meta_box' ) );

			// Events hooks.
			add_action( 'wp_enqueue_scripts', array( $events, 'add_script' ) );
			add_action( 'login_enqueue_scripts', array( $events, 'add_script' ) );
			add_action( 'wp_logout', array( $events, 'logout' ), 100, 2 );
			add_action( 'wp_login', array( $events, 'login_success' ), 100, 2 );
			add_action( 'wp_login_failed', array( $events, 'login_failure' ), 100 );
			add_action( 'user_register', array( $events, 'create_account' ), 100 );
			add_action( 'profile_update', array( $events, 'update_account' ), 100, 2 );
			add_action( 'woocommerce_add_to_cart', array( $events, 'add_to_cart' ), 100 );
			add_action( 'woocommerce_remove_cart_item', array( $events, 'remove_from_cart' ), 100 );

			if ( $o->auto_send_enabled() ) {
				add_action( 'woocommerce_checkout_order_processed', array( $events, 'create_order' ), 100 );
			}

			add_action( 'woocommerce_new_order', array( $events, 'add_session_info' ), 100 );
			add_action( 'woocommerce_order_status_changed', array( $events, 'update_order_status' ), 100 );
			add_action( 'post_updated', array( $events, 'update_order' ), 100 );
			add_action( 'shutdown', array( $events, 'shutdown' ) );

			// Ajax API hook.
			add_action( 'wp_ajax_wc_siftscience_action', array( $api, 'handle_ajax' ), 100 );

			// Run stats update at shutdown.
			add_action( 'shutdown', array( $s, 'shutdown' ) );

			// Stripe.
			add_action( 'wc_gateway_stripe_process_payment', array( $stripe, 'stripe_payment' ), 10, 2 );
			add_filter( 'wc_siftscience_order_payment_method', array( $stripe, 'order_payment_method' ), 10, 2 );
		}
	}

	$wc_siftscience_plugin = new WC_SiftScience_Plugin();
	add_action( 'init', array( $wc_siftscience_plugin, 'run' ) );

endif;
