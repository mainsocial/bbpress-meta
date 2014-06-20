<?php


class Bbpress_Meta_Commands extends WP_CLI_Command {

	/**
	 * This will install bbPress metas plugin required table and migrate data over.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bbpress_metas install
	 */
	public function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Notify the user we are putting the site into maintenance mode
		WP_CLI::log( 'Putting the site into maintenance mode and installing the new schema' );

		// This puts WordPress in maintenance mode
		$maintenance_file = ABSPATH . '.maintenance';

		// 1. Put the website into maintenance mode for a short period
		if ( ! file_put_contents( $maintenance_file, '<?php $upgrading = time(); ?>' ) ) {
			// If we are unable to write the maintenance file, we return right away to keep data integrity
			WP_CLI::error( 'Unable to put the website into maintenance mode. Please check file permissions in the root.' );
		}

		// 2. Install the new table
		$table = Bbpress_Meta::$table_name;

		$sql = "CREATE TABLE {$table} (
			meta_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NULL,
			meta_value varchar(255) NULL,
			PRIMARY KEY  (meta_id),
			KEY post_id_meta_value (post_id, meta_value),
			KEY meta_value (meta_value),
			KEY post_id (post_id)
		) ENGINE = MyISAM";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		dbDelta( $sql );

		// MySQL to migrate data over the new table
		WP_CLI::log( 'Starting data migration...' );

		// 3. Query old data over from the postmeta table
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s", Bbpress_Meta::$meta_key ),
			ARRAY_A
		);

		// Populate the new table... this might take a while so we keep the user posted
		foreach ( $results as $key => $row ) {
			// Inform the user every 5 000 entries
			if ( ( $key % 5000 ) === 0 && $key !== 0 ) {
				WP_CLI::log( sprintf( 'We have just processed %s entries', number_format( $key ) ) );
			}

			$wpdb->insert(
				$table,
				$row
			);
		}

		// Notify the user we are done
		WP_CLI::success( 'Done migrating content. We will now remove maintenance mode and update db option' );

		// Database table created, update option to reflect plugin version
		update_option( Bbpress_Meta::OPTION_KEY, Bbpress_Meta::VERSION );

		// Remove maintenance mode
		if ( ! unlink( $maintenance_file ) ) {
			// If we are unable to unlink the file we need to warn the user right now.
			WP_CLI::error( 'The script was unable to delete the .maintenance file required for the site to run properly. Please delete it manually.' );
		}
	}

	public function update() {
		// No update for now.
	}

}
WP_CLI::add_command( 'bbpress-meta', 'Bbpress_Meta_Commands' );
