<?php

/*
 * Author: Nabeel Sulieman
 * Description: This class handles the plugin's settings page.
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_SiftScience_Admin' ) ) :

	include_once( 'class-wc-siftscience-options.php' );

	class WC_SiftScience_Admin {
		private $id = 'siftsci';
		private $label = 'SiftScience';
		private $options;
		private $logger;

		public function __construct( WC_SiftScience_Options $options, WC_SiftScience_Comm $comm, WC_SiftScience_Logger $logger )
		{
			$this->options = $options;
			$this->comm = $comm;
			$this->logger = $logger;
		}

		public function check_api() {
			// try requesting a non-existent user score and see that the response isn't a permission fail
			$response = $this->comm->get_user_score( '_dummy_' . rand( 1000, 9999 ) );
			$this->logger->log_info( '[api check response] ' . json_encode( $response ) );
			return isset( $response->status ) && ( $response->status === 0 || $response->status === 54 );
		}

		public function get_sections() {
			global $current_section;
			$sections  = array(
				'' => 'Settings',
				'debug' => 'Debug',
			);

			echo '<ul class="subsubsub">';
			$array_keys = array_keys( $sections );

			foreach ( $sections as $id => $label ) {
				echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
			}

			echo '</ul><br class="clear" />';
		}

		public function output_settings_fields() {
			global $current_section;
			if ( 'debug' === $current_section ) {
				$log_file = dirname( __DIR__ ) . '/debug.log';
				if ( isset( $_GET[ 'clear_logs' ] ) ) {
					$url = home_url( remove_query_arg( 'clear_logs' ) );
					$fh = fopen( $log_file, 'w' );
					fclose( $fh );
					wp_redirect( $url );
				}

				$GLOBALS['hide_save_button'] = true;
				$logs = 'none';
				if ( file_exists( $log_file ) ) {
					$logs = file_get_contents( $log_file );
				}
				$logs = nl2br( esc_html( $logs ) );
				echo '<h2>Logs</h2>';
				echo "<p>$logs</p>";
				$url = home_url( add_query_arg( array( 'clear_logs' => 1 ) ) );
				echo "<a href='$url' class=\"button-primary woocommerce-save-button\">Clear Logs</a>";
				return;
			}

			WC_Admin_Settings::output_fields( $this->get_settings() );

			$jsPath = $this->options->get_react_app_path();
			echo $this->batch_upload();
			$data = array(
				'apiUrl' => admin_url( 'admin-ajax.php' ),
			);
			wp_enqueue_script( 'wc-siftsci-react-app', $jsPath, array(), $this->options->get_version(), true );
			wp_localize_script( 'wc-siftsci-react-app', "_siftsci_app_input_data", $data );
		}

		public function save_settings() {
			global $current_section;
			if ( '' !== $current_section ) {
				return;
			}
			WC_Admin_Settings::save_fields( $this->get_settings() );
			$is_api_working = $this->check_api() ? 1 : 0;
			update_option( WC_SiftScience_Options::$is_api_setup, $is_api_working );
			if ( $is_api_working === 1 ) {
				WC_Admin_Settings::add_message( 'API is correctly configured' );
			} else {
				WC_Admin_Settings::add_error( 'API settings are broken' );
			}
		}

		public function add_settings_page( $pages ) {
			$pages[$this->id] = $this->label;
			return $pages;
		}

		private function get_settings() {
			return array(
				$this->get_title( 'siftsci_title', 'SiftScience Settings' ),

				$this->get_text_input( WC_SiftScience_Options::$api_key,
					'Rest API Key', 'The API key for production' ),

				$this->get_text_input( WC_SiftScience_Options::$js_key,
					'Javascript Snippet Key', 'Javascript snippet key for production' ),

				$this->get_number_input( WC_SiftScience_Options::$threshold_good,
					'Good Score Threshold', 'Scores below this value are considered good and shown in green', 30),

				$this->get_number_input( WC_SiftScience_Options::$threshold_bad,
					'Bad Score Threshold', 'Scores above this value are considered bad and shown in red', 60 ),

				$this->get_text_input( WC_SiftScience_Options::$name_prefix,
					'User & Order Name Prefix',
					'Prefix to give order and user names. '
					. 'Useful when you have have multiple stores and one Sift Science account.' ),

				$this->get_check_box( WC_SiftScience_Options::$send_on_create_enabled,
					'Automatically send data',
					'Automatically send data to SiftScience when an order is created'
				),

				$this->get_drop_down( WC_SiftScience_Options::$log_level_key,
					'Log Level',
					'How much logging information to generate',
					array( 2 => 'Errors', 1 => 'Errors & Warnings', 0 => 'Errors, Warnings & Info' )
				),
				
				$this->get_section_end( 'sifsci_section_main' ),
			);
		}

		private function get_title( $id, $title ) {
			return array( 'title' => $title, 'type' => 'title', 'desc' => '', 'id' => $id );
		}

		private function get_text_input( $id, $title, $desc ) {
			return array(
				'title' => $title,
				'desc' => $desc,
				'desc_tip' => true,
				'type' => 'text',
				'id' => $id,
			);
		}

		private function get_number_input( $id, $title, $desc, $default ) {
			return array(
				'title' => $title,
				'desc' => $desc,
				'desc_tip' => true,
				'type' => 'number',
				'id' => $id,
				'default' => $default,
			);
		}

		private function get_section_end( $id ) {
			return array( 'type' => 'sectionend', 'id' => $id );
		}

		private function get_check_box( $id, $title, $desc ) {
			return array(
				'title' => $title,
				'desc' => $desc,
				'desc_tip' => true,
				'type' => 'checkbox',
				'id' => $id,
			);
		}

		private function get_drop_down( $id, $title, $desc, $options ) {
			return array(
				'id' => $id,
				'title' => $title,
				'desc' => $desc,
				'desc_tip' => true,
				'options' => $options,
				'type' => 'select',
			);
		}

		public function settings_notice() {
			$uri = $_SERVER['REQUEST_URI'];
			$is_admin_page = ( strpos( $uri, 'tab=siftsci') > 0 ) ? true : false;
			if ( $is_admin_page || $this->options->is_setup() ) {
				return;
			}

			$link = admin_url( 'admin.php?page=wc-settings&tab=siftsci' );
			$here = "<a href='$link'>here</a>";
			echo "<div class='notice notice-error is-dismissible'>" .
			     "<p>SiftScience configuration is invalid. Click $here to update.</p>" .
			     "</div>";
		}

		public function batch_upload() {
			return "
<table class='form-table'>
<tbody>
	<tr valign='top'>
		<th scope='row' class='titledesc'>
			<label>Batch Upload</label>
		</th>
		<td class='forminp forminp-text'>
			<div id='batch-upload'></div>
		</tr>
	</tbody>
</table>
";
		}
	}

endif;
