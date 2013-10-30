<?php
/**
 * Plugin Name: Post Monitor
 * Plugin URI: *
 * Description: Adds a simple metabox to each post/page displaying its meta information, even works on Custom Post Types!  Permissions Setting is found under Settings > Writing > Post Monitor User Access.
 * Author: John Regan
 * Author URI: http://johnregan3.me
 * Version: 0.1
 * Text Domain: post-monitor
 *
 * Copyright 2013  John Regan  (email : johnregan3@outlook.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @author John Regan
 * @version 0.1
 */


class Post_Monitor {

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
				add_meta_box( 'post_monitor', 
					__( 'Post Monitor', 
						'post-monitor' ), 
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
		load_plugin_textdomain( 'post-monitor' );
	}


	/**
	 * Quick print CSS instead of including .css file
	 *
	 * @action admin_head
	 */
	static function style() {
		ob_start();
			echo "<style type='text/css'>\n";
			echo "    #post_monitor { overflow-x: scroll; }\n";
			echo "</style>";
		echo ob_get_clean();
	}


	/**
	 * Setup Accesss Level settings field
	 *
	 * @action admin_init
	 */
	static function settings() {
		add_settings_field(
			'access',
			__( 'Post Monitor User Access', 'post-monitor' ),
			array( __CLASS__, 'access' ),
			'writing',
			'default'
		);
		register_setting( 'pijr3_settings_group', 'pijr3_settings' );
	}


	/**
	 * Render Access Level settings field
	 */
	static function access() {
		settings_fields( 'pijr3_settings_group' );
		$settings = get_option( 'pijr3_settings' );
		$access   = isset( $settings['access'] ) ? $settings['access'] : 'edit_users' ;

		$permissions = array(
			'edit_users'    => __( 'Administrator', 'post-monitor' ),
			'edit_pages'    => __( 'Editor', 'post-monitor' ),
			'publish_posts' => __( 'Author', 'post-monitor' ),
			'edit_posts'    => __( 'Contributor', 'post-monitor' ),
		);

		echo "<select name='pijr3_settings[access]'>";
			foreach ( $permissions as $cap => $role ) {
				echo "<option value='" . esc_attr( $cap ) . "' " . selected( $access, $cap, false) . " >"  . esc_html( $role ) . "</option>";
			}
		echo "</select>";
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
	 * Check if user has access to the Post Monitor
	 *
	 * @uses user_caps()
	 * @return bool
	 */
	static function user_has_access() {
		$settings    = get_option( 'pijr3_settings' );
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
	 * @uses get_post_object()
	 * @uses get_post_format()
	 * @uses get_taxonomies()
	 * @uses get_post_meta()
	 * @uses get_author()
	 * @uses get_attachments()
	 * @uses render()
	 */
	static function metabox() {
		global $post;
		$post_id   = $post->ID;
		$post_info = array();

		// Post Object
		$post_info[] = self::get_post_object( $post );

		// Post Format
		$post_info[] = self::get_post_format( $post_id );

		// Non-Heierarchical Taxonomies (tags)
		$post_info[] = self::get_taxonomies( $post_id );

		// Post Meta Fields
		$post_info[] = self::get_post_meta( $post_id );

		// Post Author
		$post_info[] = self::get_author( $post );

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
			echo '<h4>' . esc_html__( 'Post Object', 'post-monitor' ) . '</h4>';
			$post_array = ( array ) $post;
			// Remove Post content from Post Monitor display.
			unset( $post_array['post_content'] );
			echo self::print_formatted_array( $post_array );
		return ob_get_clean();
	}


	/**
	 * Render Post Format Section
	 *
	 * @param  int     $post_id  Post ID of current post
	 * @return string  Post Format
	 */
	static function get_post_format( $post_id ) {
		ob_start();
			echo '<h4>' . esc_html__( 'Post Format', 'post-monitor' ) . '</h4>';
			echo "<pre>";
			$format = get_post_format( $post_id );
			$format = ( $format == false ) ? __( 'standard', 'post-monitor' ) : $format ;
			echo "[" . esc_html__( 'post_format', 'post-monitor' ) . "] => " . esc_html( $format );;
			echo "</pre>";
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
			echo '<h4>' . esc_html__( 'Taxonomies and Terms', 'post-monitor' ) . '</h4>';
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
				echo $terms_list;
			} else {
				echo esc_html__( 'No Terms found', 'post-monitor' );
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
			echo '<h4>' . esc_html__( 'Post Metadata', 'post-monitor' ) . '</h4>';
			$metadata = get_metadata( 'post', $post_id, '' );
			if ( is_array( $metadata ) ) {
				echo self::print_formatted_array( $metadata );
			} else {
				echo esc_html__( 'No Post Metadata found', 'post-monitor' );
			}
		return ob_get_clean();
	}


	/**
	 * Render Post Author Section
	 *
	 * @param  int     $post_id  Post ID of current post
	 * @return string  Post Author
	 */
	static function get_author( $post ) {
		ob_start();
			echo '<h4>' . esc_html__( 'Author', 'post-monitor' ) . '</h4>';
			$author_id = $post->post_author;
			$author    = get_userdata( $author_id );
			echo "<pre>";
			echo get_avatar( $author_id, '50' ) . "<br />";
			echo "[" . __( 'display_name', 'post-monitor' ) . "] => " . $author->data->display_name . "<br />";
			echo "[" . __( 'ID', 'post-monitor' ) . "] => " . $author->data->ID . "<br />";
			echo "</pre>";
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
			echo '<h4>' . esc_html__( 'Post Attachments', 'post-monitor' ) . '</h4>';
			echo "<pre>";
			$args = array(
				'post_parent' => $post_id,
				'post_type' => 'attachment',
				'posts_per_page' => -1,
				'post_status' =>'any',
			);
			$attachments = get_posts( $args );
			if ( ! empty( $attachments ) ) {
				foreach ( $attachments as $attachment ) {
					echo esc_html( $attachment->post_title ) . "<br />";
					the_attachment_link( $attachment->ID );
					echo "<br /><br />";
				}
			} else {
				echo esc_html__( 'No Attachments found', 'post-monitor' );
			}
			echo "</pre>";
		return ob_get_clean();
	}


	/**
	 * Format Arrays into clean format
	 *
	 * @param  int     $post_id  Post ID of current post
	 * @return string  $return   String of formatted array.
	 */
	static function print_formatted_array( $array = array() ) {
		ob_start();
			echo "<pre>";
			foreach ( $array as $child => $childval ) {
					if ( is_array( $childval ) ) {
						echo "[" . esc_html( $child ) . "]" . "\n";

						foreach ( $childval as $value => $key ) {
							if ( is_array( $value ) ) {
								foreach( $value as $key => $value ) {
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
						echo "[" . esc_html( $child ) . "] => " . esc_html( $childval ) . "<br />";
					}
				}
			echo "</pre>";
		return ob_get_clean();
	}


	/**
	* Render the contents of the Post Metabox
	*
	* @param  array  $post_info  Array of information from all sections
	*/
	static function render( $post_info ) {
		foreach ( $post_info as $item ) {
			echo $item;
		}
	}

}

add_action( 'plugins_loaded', array( 'Post_Monitor', 'setup' ) );
