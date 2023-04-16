<?php

namespace Ionos\Performance;

// Do not allow direct access!
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Menu class
 * Provides an unified way to add multiple submenu items for a toplevel menu point
 */
class Menu {
	/**
	 * Adds a submenu page. If the toplevel menu pages doesn’t exist, it will added too.
	 *
	 * @param string    $page_title  The text to be displayed in the title tags of the page when the menu
	 *                               is selected.
	 * @param string    $menu_title  The text to be used for the menu.
	 * @param string    $capability  The capability required for this menu to be displayed to the user.
	 * @param string    $menu_slug   The slug name to refer to this menu by. Should be unique for this menu
	 *                               and only include lowercase alphanumeric, dashes, and underscores characters
	 *                               to be compatible with sanitize_key().
	 * @param callable  $callback    The function to be called to output the content for this page.
	 * @param int|float $position    Optional. The position in the menu order this item should appear.
	 * @param string    $plugin_file Optional. The plugin basename or __FILE__ of the plugin.
	 *                               If given it will automatically add a link to the settings to the plugin listing.
	 */
	public static function add_submenu_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $position = null, $plugin_file = null ) {
		if ( empty( menu_page_url( self::get_slug(), false ) ) ) {
			self::add_menu_page();
		}

		add_submenu_page(
			sanitize_title( self::get_tenant() ),
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$callback,
			$position
		);

		if ( is_string( $plugin_file ) && ! empty( $plugin_file ) ) {
			$plugin_file = plugin_basename( $plugin_file );
			add_filter(
				"plugin_action_links_{$plugin_file}",
				function( $links ) use ( $menu_slug ) {
					$url = esc_url(
						add_query_arg(
							'page',
							$menu_slug,
							get_admin_url() . 'admin.php'
						)
					);

					$links[] = '<a href="' . $url . '">' . __( 'Settings' ) . '</a>';
					return $links;
				}
			);
		}
	}

	/**
	 * Removes the unwanted submenu item named like the tenant
	 *
	 * After adding a toplevel and submenu page there will be a submenu item with the tenant name.
	 * This method removes this item because we don’t want it to be there.
	 */
	public static function remove_unwanted_submenu_item() {
		remove_submenu_page( Menu::get_slug(), Menu::get_slug() );
	}

	private static function add_menu_page() {
		add_menu_page(
			self::get_tenant(),
			self::get_tenant(),
			'manage_options',
			self::get_slug()
		);
	}

	/**
	 * Returns the tenant name as slugified lowercase version
	 *
	 * @return string
	 */
	private static function get_slug() {
		return strtolower( sanitize_title( self::get_tenant() ) );
	}

	/**
	 * Returns the tenant name, fetches it via Meta class if necessary
	 *
	 * @return string
	 */
	private static function get_tenant() {
		return Meta::get_meta( 'AuthorName' );
	}
}

add_action( 'admin_menu', array( Menu::class, 'remove_unwanted_submenu_item' ), 999 );