<?php
/**
 * Installed plugins: active/inactive status and available updates.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Plugins_Status {

	/**
	 * All installed plugins with status + update info.
	 *
	 * @return array[] Each item: file, name, version, author, active, update_available, new_version.
	 */
	public static function get_all() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$updates = get_site_transient( 'update_plugins' );
		$out     = array();

		foreach ( $plugins as $file => $data ) {
			$has_update  = isset( $updates->response[ $file ] );
			$new_version = $has_update && isset( $updates->response[ $file ]->new_version )
				? $updates->response[ $file ]->new_version
				: '';

			$out[] = array(
				'file'             => $file,
				'name'             => $data['Name'],
				'version'          => $data['Version'],
				'author'           => wp_strip_all_tags( $data['Author'] ),
				'active'           => is_plugin_active( $file ),
				'update_available' => $has_update,
				'new_version'      => $new_version,
			);
		}

		// Plugins needing updates first, then active before inactive, then by name.
		usort(
			$out,
			function ( $a, $b ) {
				if ( $a['update_available'] !== $b['update_available'] ) {
					return $a['update_available'] ? -1 : 1;
				}
				if ( $a['active'] !== $b['active'] ) {
					return $a['active'] ? -1 : 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $out;
	}

	/**
	 * Summary counts for the stat cards.
	 *
	 * @return array
	 */
	public static function summary() {
		$plugins = self::get_all();

		return array(
			'total'    => count( $plugins ),
			'active'   => count( wp_list_filter( $plugins, array( 'active' => true ) ) ),
			'inactive' => count( wp_list_filter( $plugins, array( 'active' => false ) ) ),
			'updates'  => count( wp_list_filter( $plugins, array( 'update_available' => true ) ) ),
		);
	}

	/**
	 * Force a fresh update check against wordpress.org.
	 */
	public static function refresh_update_data() {
		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		wp_update_plugins();
		wp_update_themes();
		wp_version_check( array(), true );
	}
}
