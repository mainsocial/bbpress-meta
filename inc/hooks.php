<?php

class Bbpress_Meta_Hooks {

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
		// Declare all hooks here
		add_filter( 'query',         array( $this, 'query' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'bbp_topic_pagination', array( $this, 'pagination' ) );

		// Make sure we respect data integrity on the new table (all CRUD operation)
		add_action( 'updated_post_meta', array( $this, 'updated_post_meta' ), 10, 4 );
		add_action( 'added_post_meta',   array( $this, 'added_post_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'deleted_post_meta' ), 10, 4 );
	}

	/**
	 * Implement filter query.
	 *
	 * @param string $query
	 *
	 * @return string
	 */
	public function query( $query ) {
		global $wpdb;

		if (
			strpos( $query, Bbpress_Meta::$meta_key ) !== false
			&& strpos( $query, 'SELECT' ) !== false
		) {
			$query = str_replace( $wpdb->postmeta, Bbpress_Meta::$table_name, $query );
		}

		return $query;
	}

	/**
	 * Implement pre_get_posts action.
	 *
	 * @param object $wp_query
	 */
	public function pre_get_posts( $wp_query ) {
		if (
			$wp_query->query['post_type'] === bbp_get_topic_post_type()
			&& $wp_query->query['meta_key'] === Bbpress_Meta::$meta_key
		) {
			// Remove SQL_CALC_FOUND_ROWS as it is not efficient on large DB
			$wp_query->set( 'no_found_rows', true );
			// Remove GROUP BY on bbpress meta query because we only deal with one value.
			add_filter( 'posts_groupby', '__return_empty_string' );
		}
	}

	/**
	 * Implement bbp_topic_pagination filter.
	 *
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function pagination( $args ) {
		global $wpdb;

		// Get bbPress numbers of topics per page.
		$per_page = bbp_get_topics_per_page();

		// This is the way we deal with paging.
		// This is more efficient than doing SQL_CAL_FOUND_ROWS
		$total = $GLOBALS['wpdb']->get_var(
			"
			SELECT count(*) FROM {$wpdb->prefix}posts
			WHERE 1=1
			AND post_type = 'topic'
			AND post_status = 'publish'
			"
		);

		$args['total'] = ceil( (int) $total / (int) $per_page );

		return $args;
	}

	/**
	 * Implement action update_post_meta.
	 *
	 * Update our version of the key in the database
	 */
	public function updated_post_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		global $wpdb;

		if ( $meta_key != Bbpress_Meta::$meta_key ) {
			return;
		}

		$wpdb->update(
			Bbpress_Meta::$table_name,
			array(
				'meta_id'    => $meta_id,
				'post_id'    => $object_id,
				'meta_key'   => $meta_key,
				'meta_value' => $_meta_value,
			),
			array(
				'meta_id' => $meta_id,
			)
		);
	}

	/**
	 * Implement action added_post_meta.
	 *
	 * @param $meta_id
	 * @param $object_id
	 * @param $meta_key
	 * @param $_meta_value
	 */
	public function added_post_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		global $wpdb;

		if ( $meta_key != Bbpress_Meta::$meta_key ) {
			return;
		}

		$wpdb->insert(
			Bbpress_Meta::$table_name,
			array(
				'meta_id'    => $meta_id,
				'post_id'    => $object_id,
				'meta_key'   => $meta_key,
				'meta_value' => $_meta_value,
			)
		);
	}

	/**
	 * Implement action deleted_post_meta.
	 */
	public function deleted_post_meta( $meta_ids, $object_id, $meta_key, $_meta_value ) {
		global $wpdb;

		if ( $meta_key != Bbpress_Meta::$meta_key ) {
			return;
		}

		foreach ( $meta_ids as $meta_id ) {
			$wpdb->delete(
				Bbpress_Meta::$table_name,
				array(
					'meta_id' => $meta_id,
				),
				'%d'
			);
		}
	}

	/**
	 * Return active instance of WP_Stream, create one if it doesn't exist
	 *
	 * @return Bbpress_Metas
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}
		return self::$instance;
	}

}
