<?php
/**
 * XLSX export of pending updates (active plugins + themes).
 *
 * Writes the OOXML parts directly with ZipArchive — no external library.
 * Layout mirrors the "Update Dogma" report format: title, blue header band,
 * zebra-striped rows, bold-orange status for major version jumps, and a
 * COUNTA total row.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Export {

	/**
	 * Register the download handler.
	 */
	public static function init() {
		add_action( 'admin_post_wpdm_export_updates', array( __CLASS__, 'download' ) );
	}

	/**
	 * URL for the export button.
	 *
	 * @return string
	 */
	public static function url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=wpdm_export_updates' ),
			'wpdm_export_updates'
		);
	}

	/**
	 * Stream the workbook to the browser.
	 */
	public static function download() {
		if ( ! current_user_can( 'manage_options' ) || ! WPDM_Settings::enabled( 'export' ) ) {
			wp_die( esc_html__( 'You are not allowed to export this report.', 'wp-dashboard-management' ) );
		}
		check_admin_referer( 'wpdm_export_updates' );

		$plugins = array();
		foreach ( WPDM_Plugins_Status::get_all() as $plugin ) {
			if ( $plugin['active'] && $plugin['update_available'] ) {
				$plugins[] = array( $plugin['name'], $plugin['version'], $plugin['new_version'] );
			}
		}

		$themes = array();
		foreach ( WPDM_Themes_Status::get_all() as $theme ) {
			if ( $theme['update_available'] ) {
				$themes[] = array( $theme['name'], $theme['version'], $theme['new_version'] );
			}
		}

		$file = self::build(
			array(
				array(
					'name'        => 'Plugins',
					'title'       => 'WordPress Plugins Update',
					'item_header' => 'Plugin Name',
					'total_label' => 'Total Plugins Requiring Updates',
					'rows'        => $plugins,
				),
				array(
					'name'        => 'Themes',
					'title'       => 'WordPress Themes Update',
					'item_header' => 'Theme Name',
					'total_label' => 'Total Themes Requiring Updates',
					'rows'        => $themes,
				),
			)
		);

		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ) );
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="wordpress-updates-' . gmdate( 'Ymd-His' ) . '.xlsx"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Build the workbook file.
	 *
	 * @param array $sheets Sheet definitions.
	 * @return string|WP_Error Path to the temp xlsx file.
	 */
	private static function build( array $sheets ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'wpdm_no_zip', __( 'The ZipArchive PHP extension is required for XLSX export.', 'wp-dashboard-management' ) );
		}

		$path = wp_tempnam( 'wpdm-export.xlsx' );
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'wpdm_zip_open', __( 'Could not create the export file.', 'wp-dashboard-management' ) );
		}

		$sheet_overrides = '';
		$sheet_entries   = '';
		$sheet_rels      = '';
		foreach ( $sheets as $i => $sheet ) {
			$n                = $i + 1;
			$sheet_overrides .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$sheet_entries   .= '<sheet name="' . self::esc( $sheet['name'] ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$sheet_rels      .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
		}
		$styles_rid = count( $sheets ) + 1;

		$zip->addFromString(
			'[Content_Types].xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. $sheet_overrides
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>'
		);

		$zip->addFromString(
			'_rels/.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>'
		);

		$zip->addFromString(
			'xl/workbook.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets>' . $sheet_entries . '</sheets>'
			. '</workbook>'
		);

		$zip->addFromString(
			'xl/_rels/workbook.xml.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. $sheet_rels
			. '<Relationship Id="rId' . $styles_rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>'
		);

		$zip->addFromString( 'xl/styles.xml', self::styles_xml() );

		foreach ( $sheets as $i => $sheet ) {
			$zip->addFromString( 'xl/worksheets/sheet' . ( $i + 1 ) . '.xml', self::sheet_xml( $sheet ) );
		}

		$zip->close();

		return $path;
	}

	/**
	 * Style part.
	 *
	 * Fonts:  0 body (10) · 1 title (16 bold #1E395B) · 2 header (11 bold white)
	 *         3 bold body · 4 bold orange (#B76700)
	 * Fills:  0 none · 1 gray125 (required stub) · 2 header (#365F91) · 3 stripe (#F2F5F8)
	 * cellXfs (the "s" attribute on cells):
	 *         0 body · 1 title · 2 header · 3 stripe · 4 bold · 5 orange
	 *         6 orange+stripe · 7 bold+stripe
	 *
	 * @return string
	 */
	private static function styles_xml() {
		$font = '<font><sz val="%s"/>%s<color rgb="%s"/><name val="Quattrocento Sans"/></font>';

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="5">'
			. sprintf( $font, '10', '', 'FF000000' )
			. sprintf( $font, '16', '<b/>', 'FF1E395B' )
			. sprintf( $font, '11', '<b/>', 'FFFFFFFF' )
			. sprintf( $font, '10', '<b/>', 'FF000000' )
			. sprintf( $font, '10', '<b/>', 'FFB76700' )
			. '</fonts>'
			. '<fills count="4">'
			. '<fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FF365F91"/><bgColor indexed="64"/></patternFill></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F5F8"/><bgColor indexed="64"/></patternFill></fill>'
			. '</fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="8">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
			. '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="4" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '</cellXfs>'
			. '</styleSheet>';
	}

	/**
	 * One worksheet in the report layout.
	 *
	 * @param array $sheet Sheet definition (title, item_header, total_label, rows).
	 * @return string
	 */
	private static function sheet_xml( array $sheet ) {
		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<cols>'
			. '<col min="1" max="1" width="60.7" customWidth="1"/>'
			. '<col min="2" max="2" width="19" customWidth="1"/>'
			. '<col min="3" max="3" width="21" customWidth="1"/>'
			. '<col min="4" max="4" width="20" customWidth="1"/>'
			. '</cols>'
			. '<sheetData>';

		// Row 1: title. Rows 2-3 blank. Row 4: header band.
		$xml .= '<row r="1">' . self::str_cell( 'A1', $sheet['title'], 1 ) . '</row>';
		$xml .= '<row r="4">'
			. self::str_cell( 'A4', $sheet['item_header'], 2 )
			. self::str_cell( 'B4', 'Current Version', 2 )
			. self::str_cell( 'C4', 'Available Version', 2 )
			. self::str_cell( 'D4', 'Status', 2 )
			. '</row>';

		$row = 5;
		foreach ( $sheet['rows'] as $i => $item ) {
			$striped = ( $i % 2 ) === 1;
			$body    = $striped ? 3 : 0;
			$status  = self::is_major_jump( $item[1], $item[2] )
				? ( $striped ? 6 : 5 )
				: $body;

			$xml .= '<row r="' . $row . '">'
				. self::str_cell( 'A' . $row, $item[0], $body )
				. self::str_cell( 'B' . $row, $item[1], $body )
				. self::str_cell( 'C' . $row, $item[2], $body )
				. self::str_cell( 'D' . $row, 'Update Available', $status )
				. '</row>';
			$row++;
		}

		if ( empty( $sheet['rows'] ) ) {
			$xml .= '<row r="' . $row . '">' . self::str_cell( 'A' . $row, 'No updates pending', 0 ) . '</row>';
			$row++;
		}

		// Total row with a live COUNTA over the data range.
		$first = 5;
		$last  = max( $first, $row - 1 );
		$count = count( $sheet['rows'] );
		$xml  .= '<row r="' . $row . '">'
			. self::str_cell( 'A' . $row, $sheet['total_label'], 4 )
			. '<c r="B' . $row . '" s="4"><f>COUNTA(A' . $first . ':A' . $last . ')</f><v>' . ( $count ? $count : 1 ) . '</v></c>'
			. '</row>';

		return $xml . '</sheetData></worksheet>';
	}

	/**
	 * An inline-string cell.
	 *
	 * @param string $ref   Cell reference, e.g. "A1".
	 * @param string $value Text value.
	 * @param int    $style cellXfs index.
	 * @return string
	 */
	private static function str_cell( $ref, $value, $style ) {
		return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc( $value ) . '</t></is></c>';
	}

	/**
	 * Whether the update crosses a major version boundary.
	 *
	 * @param string $current Current version.
	 * @param string $new     New version.
	 * @return bool
	 */
	private static function is_major_jump( $current, $new ) {
		return (int) $current !== (int) $new;
	}

	/**
	 * XML-escape a value.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	private static function esc( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}
