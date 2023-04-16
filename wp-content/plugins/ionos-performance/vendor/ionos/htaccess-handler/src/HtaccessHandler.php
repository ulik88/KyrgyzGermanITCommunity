<?php

namespace Ionos\HtaccessHandler;

use Exception;
use RuntimeException;

class HtaccessHandler {
	/**
	 * Path to the .htaccess-file.
	 *
	 * @var string|null
	 */
	private $path;

	/**
	 * Array of .htaccess snippet templates.
	 *
	 * @var array
	 */
	private $templates = [];

	/**
	 * Array of .htaccess markers.
	 *
	 * @var array
	 */
	private $markers;

	public function __construct( array $markers, $path = null ) {
		$this->path = isset( $path ) ? $path : ABSPATH . '.htaccess';

		$this->markers = $this->validate_markers( $markers );
		if ( empty( $this->markers ) ) {
			return;
		}

		$this->templates = $this->get_templates();
	}

	/**
	 * Read content from .htaccess.
	 *
	 * @throws RuntimeException if .htaccess is not readable.
	 *
	 * @return string
	 */
	private function get_htaccess_content() {
		if ( ! @is_readable( $this->path ) ) {
			throw new RuntimeException( 'Could not read .htaccess file.' );
		}

		$content = file_get_contents( $this->path );
		return $this->maybe_migrate_markers( $content );
	}

	/**
	 * Validates .htaccess markers.
	 *
	 * @param array $markers Array of markers.
	 *
	 * @return array
	 */
	private function validate_markers( array $markers ) {
		$filtered_markers = [];

		foreach ( $markers as $key => $marker ) {
			if (
				! isset( $marker['wrapperMarker'] )
				|| ! isset( $marker['versionMarker'] )
				|| ! isset( $marker['version'] )
				|| ! isset( $marker['templatePath'] )
			) {
				continue;
			}
			$filtered_markers[ $key ] = $marker;
		}

		return $filtered_markers;
	}

	/**
	 * Reads the snippet versions from the .htaccess.
	 *
	 * @param string $content .htaccess content.
	 *
	 * @return array
	 */
	private function get_versions_from_htaccess( $content ) {
		$versions = [];

		foreach ( $this->markers as $key => $data ) {
			if ( preg_match( '/# ' . preg_quote( $data['versionMarker'] ) . ' ([^\r\n]*)/', $content, $matches ) ) {
				$versions[ $key ] = next( $matches );
			}
		}

		return $versions;
	}

	/**
	 * Gets templates and does replacements if necessary.
	 *
	 * @return array
	 */
	private function get_templates() {
		$templates = [];

		foreach ( $this->markers as $key => $marker ) {
			if ( @is_readable( $marker['templatePath'] ) ) {
				$template = trim( file_get_contents( $marker['templatePath'] ) );

				if ( isset( $marker['replacements'] ) ) {
					foreach ( $marker['replacements'] as $search => $replace ) {
						$template = str_replace( $search, $replace, $template );
					}
				}

				$templates[ $key ] = $template;
			}
		}

		return $templates;
	}

	/**
	 * Migrates markers if needed.
	 *
	 * @param string $content The .htaccess content.
	 *
	 * @return string
	 */
	private function maybe_migrate_markers( $content ) {
		foreach ( $this->markers as $marker ) {
			if ( ! isset( $marker['migrations'] ) ) {
				continue;
			}
			foreach ( $marker['migrations'] as $migration_key => $migrations ) {
				if ( ! isset( $marker[ $migration_key ] ) ) {
					continue;
				}

				$new_marker = $marker[ $migration_key ];

				foreach ( $migrations as $migration ) {
					$content = str_replace( $migration, $new_marker, $content );
				}
			}
		}

		return $content;
	}

	/**
	 * Writes content into the .htaccess file.
	 *
	 * @param string $content The content to write into the .htaccess file.
	 *
	 * @return void
	 */
	public function write( $content ) {
		$temp_file_path = ABSPATH . md5( $content );
		file_put_contents( $temp_file_path, $content, LOCK_EX );
		rename( $temp_file_path, $this->path );
	}

	/**
	 * Returns .htaccess content.
	 *
	 * @return string
	 */
	public function get_content() {
		try {
			return $this->get_htaccess_content();
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Checks content for a given marker.
	 *
	 * @param string      $marker The marker to search for.
	 * @param null|string $content The content to search in. If null, the content of the .htaccess file is used.
	 *
	 * @return false|int|null
	 */
	public function has_snippet( $marker, $content = null ) {
		if ( $content === null ) {
			try {
				$content = $this->get_htaccess_content();
			} catch ( Exception $e ) {
				return false;
			}
		}

		return preg_match_all( '/# START ' . preg_quote( $this->markers[ $marker ]['wrapperMarker'], '/' ) . '(.|\n)*?# END ' . preg_quote( $this->markers[ $marker ]['wrapperMarker'], '/' ) . '/', $content );
	}

	/**
	 * Updates existing snippets, if necessary.
	 */
	public function maybe_update_snippets() {
		try {
			$content = $this->get_htaccess_content();
		} catch ( Exception $e ) {
			return;
		}

		$versions = $this->get_versions_from_htaccess( $content );

		foreach ( $this->markers as $marker_key => $marker_data ) {
			if ( ! $this->has_snippet( $marker_key, $content ) || ! isset( $this->templates[ $marker_key ] ) ) {
				continue;
			}

			if ( ! isset( $versions[ $marker_key ] ) ) {
				continue;
			}

			if ( $marker_data['version'] === $versions[ $marker_key ] ) {
				continue;
			}

			$wrapper_marker = $marker_data['wrapperMarker'];
			$version_marker = $marker_data['versionMarker'];
			$version        = $marker_data['version'];
			$template       = $this->templates[ $marker_key ];

			$wrapper_start = "# START {$wrapper_marker}";
			$wrapper_end   = "# END {$wrapper_marker}";

			$version_line = "# {$version_marker} {$version}";

			$snippet = "{$wrapper_start}\n{$version_line}\n{$template}\n{$wrapper_end}";

			$content = preg_replace( '/' . preg_quote( $wrapper_start, '/' ) . "(.|\n)*?" . preg_quote( $wrapper_end, '/' ) . '/', $snippet, $content );
		}

		$this->write( $content );
	}


	/**
	 * Adds snippet to .htaccess.
	 *
	 * @param string $marker The marker to add.
	 */
	public function insert_snippet( $marker ) {
		try {
			$content = $this->get_htaccess_content();
		} catch ( Exception $e ) {
			return;
		}

		if ( $this->has_snippet( $marker, $content ) ) {
			return;
		}

		$marker_data = $this->markers[ $marker ];

		$wrapper_marker = $marker_data['wrapperMarker'];
		$version_marker = $marker_data['versionMarker'];
		$version        = $marker_data['version'];
		$template       = $this->templates[ $marker ];

		$wrapper_start = "# START {$wrapper_marker}";
		$wrapper_end   = "# END {$wrapper_marker}";

		$version_line = "# {$version_marker} {$version}";

		$snippet = "{$wrapper_start}\n{$version_line}\n{$template}\n{$wrapper_end}";

		$wordpress_marker = '# BEGIN WordPress';

		if ( false !== strpos( $content, $wordpress_marker ) ) {
			$content = str_replace( $wordpress_marker, "$snippet\n\n$wordpress_marker", $content );
		} else {
			$content .= "\n\n" . $snippet;
		}

		$this->write( $content );
	}

	/**
	 * Removes snippet from .htaccess.
	 *
	 * @param string $marker The marker that should be removed.
	 */
	public function remove_snippet( $marker ) {
		try {
			$content = $this->get_htaccess_content();
		} catch ( Exception $e ) {
			return;
		}

		if ( ! $this->has_snippet( $marker, $content ) ) {
			return;
		}

		$wrapper_start = "# START {$this->markers[$marker]['wrapperMarker']}";
		$wrapper_end   = "# END {$this->markers[$marker]['wrapperMarker']}";

		$content = preg_replace( '/' . preg_quote( $wrapper_start, '/' ) . "(.|\n)*?" . preg_quote( $wrapper_end, '/' ) . "(\n*)/", '', $content );
		$this->write( $content );
	}

	/**
	 * Flush rewrite rules if permalink structure is set but no `# BEGIN WordPress` marker is found.
	 */
	public static function flush_rewrite_rules_if_wp_htaccess_snippet_missing() {
		if ( empty( get_option( 'permalink_structure' ) ) ) {
			return;
		}

		$handler = new self( [] );
		if ( ! @is_readable( $handler->path ) || ! @is_writable( $handler->path ) ) {
			return;
		}

		if ( false === strpos( $handler->get_content(), '# BEGIN WordPress' ) ) {
			flush_rewrite_rules();
		}
	}
}
