<?php
/**
 * Installed themes: active status and available updates.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Themes_Status {

	/**
	 * All installed themes with status + update info.
	 *
	 * @return array[] Each item: stylesheet, name, version, author, active, update_available, new_version.
	 */
	public static function get_all() {
		$themes  = wp_get_themes();
		$updates = get_site_transient( 'update_themes' );
		$current = get_stylesheet();
		$out     = array();

		foreach ( $themes as $stylesheet => $theme ) {
			$has_update  = isset( $updates->response[ $stylesheet ] );
			$new_version = $has_update && isset( $updates->response[ $stylesheet ]['new_version'] )
				? $updates->response[ $stylesheet ]['new_version']
				: '';

			$out[] = array(
				'stylesheet'       => $stylesheet,
				'name'             => $theme->get( 'Name' ),
				'version'          => $theme->get( 'Version' ),
				'author'           => wp_strip_all_tags( $theme->get( 'Author' ) ),
				'active'           => $stylesheet === $current,
				'update_available' => $has_update,
				'new_version'      => $new_version,
			);
		}

		// Themes needing updates first, then the active theme, then by name.
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
		$themes = self::get_all();

		return array(
			'total'   => count( $themes ),
			'updates' => count( wp_list_filter( $themes, array( 'update_available' => true ) ) ),
		);
	}
}
