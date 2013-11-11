<?php
/**
 * Plugin Name: Post Inspector
 * Description: Adds a simple metabox to each post/page displaying its meta information, even works on Custom Post Types!  Permissions Setting is found under Settings > Writing > Post Inspector User Access.
 * Version: 0.1
 * Author: X-Team
 * Author URI: http://x-team.com/wordpress/
 * Text Domain: wp-post-inspector
 * License: GPLv2+
 */

/**
 * Copyright (c) 2013 X-Team (http://x-team.com/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class WP_Post_Inspector {

	static function setup() {
		add_action( 'admin_init', array( __CLASS__, 'add_metabox' ) );
		add_action( 'admin_init', array( __CLASS__, 'textdomain' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'admin_head', array( __CLASS__, 'style' ) );
	}


	/**
	 * Register Metabox for all Post Types
	 *
	 * Check user roles against saved settings to gain access to the metabox for all post types
	 *
	 * @uses user_has_access()
	 * @action admin_init
	 */
	static function add_metabox() {
		$post_types = get_post_types();
		unset( $post_types['revision'] );
		unset( $post_types['nav_menu_item'] );
		$post_types = array_values( $post_types );
		if ( self::user_has_access() == true ) {
			foreach ( $post_types as $post_type ) {
				add_meta_box(
					'post_inspector',
					__( 'Post Inspector', 'wp-post-inspector' ),
					array( __CLASS__, 'metabox' ),
					$post_type,
					'normal',
					'low'
				);
			}
		}
	}


	/**
	 * Register text domain
	 *
	 * @action admin_init
	 */
	static function textdomain() {
		load_plugin_textdomain( 'wp-post-inspector' );
	}


	/**
	 * Quick print CSS instead of including .css file
	 *
	 * @action admin_head
	 */
	static function style() {
		ob_start();
			echo "<style type='text/css'>\n";
			echo "    #post_inspector { overflow-x: scroll; }\n";
			echo '</style>';
		echo ob_get_clean(); //xss okay
	}


	/**
	 * Setup Accesss Level settings field
	 *
	 * @action admin_init
	 */
	static function settings() {
		add_settings_field(
			'access',
			__( 'Post Inspector User Access', 'wp-post-inspector' ),
			array( __CLASS__, 'access' ),
			'writing',
			'default'
		);
		register_setting( 'wppi_settings_group', 'wppi_settings' );
	}


	/**
	 * Render Access Level settings field
	 *
	 * @return  string  Capability levels
	 */
	static function access() {
		settings_fields( 'wppi_settings_group' );
		$settings = get_option( 'wppi_settings' );
		$access   = isset( $settings['access'] ) ? $settings['access'] : 'edit_users' ;

		$caps = array(
			'create_users'      => __( 'Create Users', 'wp-post-inspector' ),
			'edit_others_posts' => __( 'Edit Others\' Posts', 'wp-post-inspector' ),
			'publish_posts'     => __( 'Publish Posts', 'wp-post-inspector' ),
			'edit_posts'        => __( 'Edit Posts', 'wp-post-inspector' ),
		);

		echo "<select name='wppi_settings[access]'>";
		foreach ( $caps as $cap => $role ) {
			echo '<option value="' . esc_attr( $cap ) . '" ' . selected( $access, $cap, false ) . ' >'  . esc_html( $role ) . '</option>';
		}
		echo '</select>';
	}


	/**
	 * Get the capabilities for the current user
	 *
	 * @return  array  $user_caps  Array of capabilities assigned to the user
	 */
	static function user_caps() {
		$user_id   = get_current_user_id();
		$user      = get_userdata( $user_id );
		$user_caps = array_keys( $user->allcaps );
		return $user_caps;
	}


	/**
	 * Check if user has access to the Post Inspector
	 *
	 * @uses   user_caps
	 * @return bool
	 */
	static function user_has_access() {
		$settings    = get_option( 'wppi_settings' );
		$access_caps = isset( $settings['access'] ) ? $settings['access'] : 'edit_users' ;
		$user_caps   = self::user_caps();
		if ( is_array( $user_caps ) && in_array( $access_caps, $user_caps ) ) {
			return true;
		}
		return false;
	}


	/**
	 * Render Metabox
	 *
	 * @uses get_post_object
	 * @uses get_post_format
	 * @uses get_taxonomies
	 * @uses get_post_meta
	 * @uses get_author
	 * @uses get_attachments
	 * @uses render
	 */
	static function metabox() {
		global $post;
		$post_id   = $post->ID;
		$post_info = array();

		// Post Object
		$post_info[] = self::get_post_object( $post );

		// Non-Heierarchical Taxonomies (tags)
		$post_info[] = self::get_taxonomies( $post_id );

		// Post Meta Fields
		$post_info[] = self::get_post_meta( $post_id );

		// Post Author
		$post_info[] = self::get_author( $post );

		// Featured Image
		$post_info[] = self::get_featured_image( $post_id );

		// Post Attachments
		$post_info[] = self::get_attachments( $post_id );

		// Render the Metabox
		self::render( $post_info );
	}


	/**
	 * Render Post Object Section
	 *
	 * @uses   print_formatted_array()
	 * @param  obj     $post  WP Post Object
	 * @return string  Formatted Conetents of Post Object
	 */
	static function get_post_object( $post ) {
		ob_start();
			echo '<h4>' . esc_html__( 'Post Object', 'wp-post-inspector' ) . '</h4>';
			$post_array = ( array ) $post;
			// Remove Post content from Post Inspector display.
			unset( $post_array['post_content'] );
			echo self::print_formatted_array( $post_array ); //xss okay
		return ob_get_clean();
	}

	/**
	 * Render Taxonomy Section
	 *
	 * @param  int     $post_id  Post ID of current post
	 * @return string  Formatted list of Post Taxonomies
	 */
	static function get_taxonomies( $post_id ) {
		ob_start();
		echo '<h4>' . esc_html__( 'Taxonomies and Terms', 'wp-post-inspector' ) . '</h4>';
		echo '<pre>';
		$terms_list = '';
		$taxonomies = get_taxonomies();
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$terms_list .= '[' . esc_html( $term->taxonomy ) . '] => ' . esc_html( $term->name ) . '<br />';
					}
				}
			}
		}
		if ( ! empty( $terms_list ) ){
			echo $terms_list; //xss okay
		} else {
			echo esc_html__( 'No Terms found', 'wp-post-inspector' );
		}
		echo '</pre>';
		return ob_get_clean();
	}


	/**
	 * Render Post Metadata Section
	 *
	 * @uses   print_formatted_array()
	 * @param  int    $post_id  Post ID of current post
	 * @return sring  Post Metadata
	 */
	static function get_post_meta( $post_id ) {
		ob_start();
		echo '<h4>' . esc_html__( 'Post Metadata', 'wp-post-inspector' ) . '</h4>';
		$metadata = get_metadata( 'post', $post_id, '' );
		if ( is_array( $metadata ) ) {
			echo self::print_formatted_array( $metadata ); //xss okay
		} else {
			echo '<pre>' . esc_html__( 'No Post Metadata found', 'wp-post-inspector' ) . '</pre>';
		}
		return ob_get_clean();
	}


	/**
	 * Render Post Author Section
	 *
	 * @param  int     $post  Post object current post
	 * @return string  Post Author
	 */
	static function get_author( $post ) {
		ob_start();
		echo '<h4>' . esc_html__( 'Author', 'wp-post-inspector' ) . '</h4>';
		$author_id = $post->post_author;
		$author    = get_userdata( $author_id );
		echo '<pre>';
		echo get_avatar( $author_id, 100 ) . '<br />';
		echo '[' . __( 'display_name', 'wp-post-inspector' ) . '] => ' . $author->data->display_name . '<br />';
		echo '[' . __( 'ID', 'wp-post-inspector' ) . '] => ' . $author->data->ID . '<br />';
		echo '</pre>';
		return ob_get_clean();
	}


	/**
	 * Render Featured Image (Post Thumbnail) Section
	 *
	 * @param  int     $post_id  Post ID of current post
	 * @return string  Post Author
	 */
	static function get_featured_image( $post_id ) {
		ob_start();
		echo '<h4>' . esc_html__( 'Featured Image', 'wp-post-inspector' ) . '</h4>';
		if ( has_post_thumbnail( $post_id ) ) {
			$link = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
			echo '<a href="' . $link[0] . '" >';
				the_post_thumbnail( array( 100, 100 ) );
			echo '</a>';
		} else {
			echo '<pre>' . esc_html__( 'No Featured Image found.', 'wp-post-inspector' ) . '</pre>';
		}
		return ob_get_clean();
	}



	/**
	 * Render Attachments Section
	 *
	 * @param  int     $post_id  Post ID of current post
	 * @return string  Post attachments information
	 */
	static function get_attachments( $post_id ) {
		ob_start();
		echo '<h4>' . esc_html__( 'Post Attachments', 'wp-post-inspector' ) . '</h4>';
		echo '<pre>';
		$args = array(
			'post_parent'    => $post_id,
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		);
		$attachments = get_posts( $args );
		if ( ! empty( $attachments ) ) {
			foreach ( $attachments as $attachment ) {
				echo esc_html( $attachment->post_title ) . '<br />';

				$link = wp_get_attachment_image_src( $attachment->ID, 'large' );
				echo '<a href="' . $link[0] . '" >';
					echo wp_get_attachment_link( $attachment->ID, array( 100, 100 ) );
				echo '</a>';
				echo '<br /><br />';
			}
		} else {
			echo esc_html__( 'No Attachments found', 'wp-post-inspector' );
		}
		echo '</pre>';
		return ob_get_clean();
	}


	/**
	 * Format Arrays into clean format.
	 *
	 * @param  array   Array to be formatted.
	 * @return string  String of formatted array.
	 */
	static function print_formatted_array( $array = array() ) {
		ob_start();
		echo '<pre>';
		foreach ( $array as $child => $childval ) {
			if ( is_array( $childval ) ) {
				echo '[' . esc_html( $child ) . ']' . '<br />';

				foreach ( $childval as $value => $key ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $key => $value ) {
							echo '        [' . esc_html( $key ) . '] => ' . esc_html( $value ) . '<br />';
						}
					} elseif ( @unserialize( $key ) !== false ) {
						$array = @unserialize( $key );
						foreach ( $array as $key => $value ) {
							echo '    [' . esc_html( $key ) . '] => ' . esc_html( $value ) . '<br />';
						}
					} else {
						echo '    [0] => ' . esc_html( $key ) . '<br />';
					}
				}
			} else {
				echo '[' . esc_html( $child ) . '] => ' . esc_html( $childval ) . '<br />';
			}
		}
		echo '</pre>';
		return ob_get_clean();
	}


	/**
	* Render the contents of the Post Metabox.
	*
	* @param  array  $post_info  Array of information from all sections.
	*/
	static function render( $post_info ) {
		foreach ( $post_info as $item ) {
			echo $item; //xss okay
		}
	}


	/**
	 * Remove all plugin data
	 */
	public static function uninstall() {
		unregister_setting( 'wppi_settings_group', 'wppi_settings' );
	}

}

add_action( 'plugins_loaded', array( 'WP_Post_Inspector', 'setup' ) );
