<?php
/*
Plugin Name: Better Author Metabox
Plugin URI: http://www.shooflydesign.org/
Description: Allows the Author metabox to be overridden with one that includes different users.
Author: Joe Chellman
Author URI: http://www.shooflydesign.org/
Version: 1.0
Text Domain: better-author-metabox

	Plugin: Copyright (c) 2014-2015 Joe Chellman

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// If this file is called directly, then abort execution.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class BetterAuthorMetabox {
	const LANG = 'better-author-metabox';
	const CONFIG = 'ba-metabox-config';
	
	protected $plugin_dir = '';

	private $options;

	function init() {
		static $instance = false;

		if ( !$instance ) {
			$instance = new BetterAuthorMetabox;
		}

		return $instance;
	}
	
	public function __construct() {
		$this->plugin_dir = plugin_dir_path( __FILE__ );
	
		// call all filters and actions
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_menu', array( $this, 'reset_author_metabox' ) );
		add_action( 'admin_init', array( $this, 'init_options' ) );

    }

    /**
     * Adds the settings page
     */
    public function add_settings_page() {
		add_options_page(
			__('Better Author Metabox Settings', self::LANG),
			__('Better Author Metabox', self::LANG),
			'manage_options',
			self::CONFIG,
			array( $this, 'display_options_page' )
		);
	}


    /**
     * Changes the Author metabox so it display all users for the post types where we want this.
     */
    public function reset_author_metabox() {
        // don't do anything if the user can't edit others posts.
        if (!current_user_can('edit_others_posts')) return;

        $options = get_option( 'BAM_config' );

        // no options (yet) - forget it!
        if (!$options) return;

        foreach ($options['enabled_post_types'] as $post_type => $val) {
            if ($val == 1) {
                remove_meta_box('authordiv', $post_type, 'normal');
                add_meta_box('authordiv', __('Author'), array($this, 'post_author_meta_box'), $post_type);       
            }
        }
	}


    /**
     * Very similar to core post_author_meta_box function, except this version always returns all users
     *
     * @param $post - the post being edited
     */
    public function post_author_meta_box($post) {
        global $user_ID;
        ?>
        <label class="screen-reader-text" for="post_author_override"><?php _e('Author'); ?></label>
        <?php
        $this->wp_dropdown_users( array(
            'name' => 'post_author_override',
            'selected' => empty($post->ID) ? $user_ID : $post->post_author,
            'include_selected' => true
        ) );
	}


    /**
     * Custom version of wp_dropdown_users that will query users by multiple roles
     *
     * @param string $args
     * @return mixed|void
     */
    protected function wp_dropdown_users( $args = '' ) {
        $defaults = array(
            'show_option_all' => '', 'show_option_none' => '', 'hide_if_only_one_author' => '',
            'orderby' => 'display_name', 'order' => 'ASC',
            'include' => '', 'exclude' => '', 'multi' => 0,
            'show' => 'display_name', 'echo' => 1,
            'selected' => 0, 'name' => 'user', 'class' => '', 'id' => '',
            'blog_id' => $GLOBALS['blog_id'], 'include_selected' => false,
            'option_none_value' => -1
        );

        $defaults['selected'] = is_author() ? get_query_var( 'author' ) : 0;

        $r = wp_parse_args( $args, $defaults );
        $show = $r['show'];
        $show_option_all = $r['show_option_all'];
        $show_option_none = $r['show_option_none'];
        $option_none_value = $r['option_none_value'];

        $query_args = wp_array_slice_assoc( $r, array( 'blog_id', 'include', 'exclude', 'orderby', 'order' ) );
        $query_args['fields'] = array( 'ID', 'user_login', $show );

        $users = array();

        $options = get_option( 'BAM_config' );

        // if roles have been selected, use them
        if (count($options['enabled_roles'])) {
            foreach ($options['enabled_roles'] as $role => $enabled) {
                if ($enabled == 1) {
                    $query_args['role'] = $role;
                    $role_users = get_users($query_args);

                    $users = array_merge($role_users);
                }
            }
        // if no roles have been selected, use the default of authors
        } else {
            $query_args['who'] = 'authors';
            $users = get_users($query_args);
        }

        $output = '';
        if ( ! empty( $users ) && ( empty( $r['hide_if_only_one_author'] ) || count( $users ) > 1 ) ) {
            $name = esc_attr( $r['name'] );
            if ( $r['multi'] && ! $r['id'] ) {
                $id = '';
            } else {
                $id = $r['id'] ? " id='" . esc_attr( $r['id'] ) . "'" : " id='$name'";
            }
            $output = "<select name='{$name}'{$id} class='" . $r['class'] . "'>\n";

            if ( $show_option_all ) {
                $output .= "\t<option value='0'>$show_option_all</option>\n";
            }

            if ( $show_option_none ) {
                $_selected = selected( $option_none_value, $r['selected'], false );
                $output .= "\t<option value='" . esc_attr( $option_none_value ) . "'$_selected>$show_option_none</option>\n";
            }

            $found_selected = false;
            foreach ( (array) $users as $user ) {
                $user->ID = (int) $user->ID;
                $_selected = selected( $user->ID, $r['selected'], false );
                if ( $_selected ) {
                    $found_selected = true;
                }
                $display = ! empty( $user->$show ) ? $user->$show : '('. $user->user_login . ')';
                $output .= "\t<option value='$user->ID'$_selected>" . esc_html( $display ) . "</option>\n";
            }

            if ( $r['include_selected'] && ! $found_selected && ( $r['selected'] > 0 ) ) {
                $user = get_userdata( $r['selected'] );
                $_selected = selected( $user->ID, $r['selected'], false );
                $display = ! empty( $user->$show ) ? $user->$show : '('. $user->user_login . ')';
                $output .= "\t<option value='$user->ID'$_selected>" . esc_html( $display ) . "</option>\n";
            }

            $output .= "</select>";
        }

        // different filter for the output of this version, so we don't mess up core
        $html = apply_filters( 'bam_wp_dropdown_users', $output );

        if ( $r['echo'] ) {
            echo $html;
        }
        return $html;
    }

    /**
     * Initializes plugin options
     */
    public function init_options() {
		register_setting(
			self::CONFIG,
			'BAM_config',
			array( $this, 'sanitize_options' )
		);
		
		add_settings_section(
			'BAM_config_main',
			'',
			function() { print ''; },
			self::CONFIG
		);
		
		add_settings_field(
			'enabled_post_types',
			__('Enabled Post Types', self::LANG),
			array( $this, 'setting_post_types' ), // Callback
			self::CONFIG, // Page
			'BAM_config_main' // Section           
		);
		
		add_settings_field(
			'enabled_roles',
			__('Enabled Roles', self::LANG),
			array( $this, 'setting_enabled_roles' ), // Callback
			self::CONFIG, // Page
			'BAM_config_main' // Section           
		);
	}

    /**
     * Sanitizes the data from the options page
     *
     * @param $input - the incoming option data
     * @return array
     */
	public function sanitize_options($input) {
        $safe_input = array();

        foreach ($input['enabled_post_types'] as $post_type => $enabled) {
            // post type settings - only allowed value is 1
            if ($enabled == 1) {
                $safe_input['enabled_post_types'][$post_type] = 1;
            }
        }

        foreach ($input['enabled_roles'] as $role => $enabled) {
            // user role setting - only allowed value is 1
            if ($enabled == 1) {
                $safe_input['enabled_roles'][$role] = 1;
            }
        }

		return $safe_input;
	}

    /**
     * Callback to display the plugin options page.
     */
	public function display_options_page() {
		// collect defaults
		$this->options = get_option( 'BAM_config' );
		
		include($this->plugin_dir . '/options-page.php');
	}
	
	/** 
	 * Callback for display_options_page() and init_options()
	 * to display the post types where the box will be overridden
	 */
	public function setting_post_types() {
	
	    $post_types = get_post_types(array(
	        'show_ui' => TRUE,
	    ), 'objects');
	    
	    foreach ($post_types as $p) {
	        $slug = $p->name;
	        $label = $p->labels->singular_name . ' <small>(' . $slug . ')</small>';
	        $item_id = "post_type_$slug";
	        $checked = checked( 1, $this->options[enabled_post_types][$slug], FALSE );

            printf(
                    '<label for="%s"><input type="checkbox" id="%s" name="BAM_config[enabled_post_types][%s]" value="1" %s /> %s</label><br />',
                    $item_id, $item_id, $slug, $checked, $label
            );
		}
		
		echo '<p class="description">Enable the expanded Author metabox for these post types.</p>';
	}
	
	/** 
	 * Callback for display_options_page() and init_options()
	 * to display the roles for users that should be displayed
	 */
	public function setting_enabled_roles() {
	    global $wp_roles;

	    foreach ($wp_roles->role_names as $slug => $label) {
	        $item_id = "role_$slug";
	        $checked = checked( 1, $this->options['enabled_roles'][$slug], FALSE );
	        
            printf(
                    '<label for="%s"><input type="checkbox" id="%s" name="BAM_config[enabled_roles][%s]" value="1" %s /> %s</label><br />',
                    $item_id, $item_id, $slug, $checked, $label
            );
		}
		
		echo '<p class="description">Show users from these roles in the author metabox.</p>';
	}
}

// Instantiate the plugin
add_action( 'init', array( 'BetterAuthorMetabox', 'init' ) );