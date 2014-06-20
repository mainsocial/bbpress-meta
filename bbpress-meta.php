<?php
/**
 * Plugin Name: bbPress Meta
 * Description: bbPress creates a bridge between slow bbPress meta queries and a fully optimize MySQL table.
 * Version: 0.0.1
 * Author: Mainsocial Technology inc, Jonathan Bardo
 * Author URI: http://mainsocial.com/
 * License: GPLv2+
 * Text Domain: bbpress-metas
 * Domain Path: /languages
 */

class Bbpress_Meta {

	/**
	 * Plugin version number following Semantic Versioning
	 * http://semver.org/
	 *
	 * @const string
	 */
	const VERSION = '0.0.1';

	/**
	 * Option key to store database version
	 *
	 * @var string
	 */
	const OPTION_KEY = 'bbpress_meta_db';

	/**
	 * Meta_keys to transfer over to new table.
	 *
	 * @var array
	 */
	public static $meta_key = '_bbp_last_active_time';

	/**
	 * Contains the plugin table name
	 */
	public static $table_name;

	/**
	 * Holds the db current version
	 *
	 * @var mixed|void
	 */
	private static $db_version;

	/**
	 * Array containing all versions of plugin that require an update in order to function properly.
	 *
	 * @var array
	 */
	private static $db_update_versions = array();

	/**
	 * Initialized object of class
	 *
	 * @access private
	 * @var object
	 */
	private static $instance = false;


	/**
	 * Private class constructor to follow singleton pattern.
	 */
	private function __construct() {
		global $wpdb;

		define( 'BBPRESS_METAS_DIR', plugin_dir_path( __FILE__ ) );
		define( 'BBPRESS_METAS_INC_DIR', BBPRESS_METAS_DIR . 'inc/' );

		// Instantiate the table name we are going to use
		self::$table_name = $wpdb->prefix . 'bbpress_meta';
		self::$db_version = get_option( self::OPTION_KEY );

		// If we need to update the db version send a message to the admin.
		if ( version_compare( end( self::$db_update_versions ), self::$db_version, '>' ) ) {
			self::notice(
				__( 'bbPress Metas Database Update Required! Because this might be a very expensive operation, you are required to use the provided wp-cli script to run this update.', 'bbpress_metas' )
			);
		}

		// If we need to install or stop the plugin from pursuing default bootstrap
		if (
			( empty( self::$db_version ) || self::$db_version !== self::VERSION )
			&& defined( 'WP_CLI' ) && WP_CLI
		) {
			// Install or update plugin tables
			require_once BBPRESS_METAS_INC_DIR . 'wp-cli-commands.php';
		} else if ( $this->verify_database_present() || ! empty( self::$db_version ) ) {
			// Instantiate the class that will hook into queries and CRUD post metas
			require BBPRESS_METAS_INC_DIR . 'hooks.php';
			add_action( 'init', array( 'Bbpress_Meta_Hooks', 'get_instance' ) );
		}
	}

	/**
	 * Verify that all needed databases are present and add an error message if not.
	 */
	public function verify_database_present() {
		global $wpdb;

		$table_name        = self::$table_name;
		$database_message  = '';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== self::$table_name ) {
			$database_message .= sprintf( '%s %s', __( 'The following table is not present in the WordPress database. Please run the wp-cli installer of bbPress metas plugin: wp bbpress_metas install', 'bbpress_metas' ), $table_name );
		}

		if ( ! empty( $database_message ) ) {
			self::notice( $database_message, true );
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Show an error or other message, using admin_notice or WP-CLI logger
	 *
	 * @param string $message
	 * @param bool $is_error
	 * @return void
	 */
	public static function notice( $message, $is_error = true ) {
		if ( defined( 'WP_CLI' ) ) {
			$message = strip_tags( $message );
			if ( $is_error ) {
				WP_CLI::warning( $message );
			} else {
				WP_CLI::success( $message );
			}
		} else {
			$print_message = function () use ( $message, $is_error ) {
				$class_name   = ( $is_error ? 'error' : 'updated' );
				$html_message = sprintf( '<div class="%s">%s</div>', $class_name, wpautop( $message ) );
				echo wp_kses_post( $html_message );
			};
			add_action( 'all_admin_notices', $print_message );
		}
	}

	/**
	 * Implement register_deactivation_hook();
	 */
	public static function uninstall() {
		global $wpdb;

		// 1. Drop table
		$table = self::$table_name;
		$wpdb->query( "DROP TABLE $table" );

		// 2. Delete option
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Return active instance of WP_Stream, create one if it doesn't exist
	 *
	 * @return Bbpress_Meta
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}
		return self::$instance;
	}

}

// Instanciate the plugin
add_action( 'plugins_loaded', array( 'Bbpress_Meta', 'get_instance' ) );

// Drop table when deactivating plugin
register_deactivation_hook( __FILE__, array( 'Bbpress_Meta', 'uninstall' ) );
