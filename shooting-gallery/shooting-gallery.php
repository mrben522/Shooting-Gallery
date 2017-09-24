<?php if(!defined('ABSPATH')) { die(); } // Include in all php files, to prevent direct execution
/**
 * Plugin Name: Shooting Gallery
 * Description: A sweet little gallery plugin
 * Author: Ben Stein
 * Author URI: github.com/mrben522
 * Version: 0.1.0
 * Text Domain: shooting-gallery
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 **/


if( !class_exists('ShootingGallery') ) {
	class ShootingGallery {
		private static $version = '0.1.0';
		private static $_this;
		private $settings;

		public static function Instance() {
			static $instance = null;
			if ($instance === null) {
				$instance = new self();
			}
			return $instance;
		}

		private function __construct() {
			register_activation_hook( __FILE__, array( $this, 'register_activation' ));
			register_deactivation_hook( __FILE__, array( $this, 'register_deactivation' ));
			// Stuff that happens on every page load, once the plugin is active
			$this->initialize_settings();

			if( is_admin() && !( defined('DOING_AJAX') && DOING_AJAX )) {
				add_action( 'admin_init', array( $this, 'admin_init' ));
				add_action( 'admin_menu', array( $this, 'admin_menu' ));
				add_action( 'admin_init', array( $this, 'add_meta_boxes' ));
                add_filter( 'plugin_action_links', array( $this, 'plugin_page_link'), 2, 2);
                //            hide the acf admin screen
//            add_filter('acf/settings/show_admin', '__return_false');

            } else {
				add_filter( 'the_content', array( $this, 'default_gallery' ));
				add_shortcode( 'shooting-gallery', array( $this, 'sg_shortcode' ));
                add_action('wp_enqueue_scripts', array( $this, 'sg_load_scripts'));
                add_action( 'init', array( $this, 'add_meta_boxes' ));

            }
		}
		// PUBLIC STATIC FUNCTIONS
		public static function get_version() {
			return ShootingGallery::$version;
		}
		// PRIVATE STATIC FUNCTIONS

		// PUBLIC FUNCTIONS
		public function register_activation() {
			// Stuff that only has to run once, on plugin activation
            add_option('shooting_gallery_settings', $this->settings, '', 'no');
		}
		public function register_deactivation() {
			// Clean up on deactivation
            {
                $check = $this->settings['settings']['deactivation_delete'];
                if($check === TRUE)
                {
                    delete_option( 'responsive_slideshow_settings' );
                }
            }
		}
		// Load scripts and styles
		public function sg_load_scripts() {
		    $post = get_post();

            if (get_field('gallery_images', $post)) {
                wp_register_script('owl-slider', plugins_url('resources/owl-carousel-1.3.2/owl.carousel.min.js', __FILE__), 'jquery');
                wp_register_script('featherlight', plugins_url('resources/featherlight-1.5.0/featherlight.min.js', __FILE__), 'jquery');
                wp_register_script('shooting-gallery', plugins_url('shooting-gallery.js', __FILE__), 'jquery');

                wp_enqueue_script('jquery');
                wp_enqueue_script('owl-slider');
                wp_enqueue_script('featherlight');
                wp_enqueue_script('shooting-gallery');

                wp_register_style('owl-slider', plugins_url('resources/owl-carousel-1.3.2/owl.carousel.css', __FILE__));
                wp_register_style('featherlight', plugins_url('resources/featherlight-1.5.0/featherlight.min.css', __FILE__));
                wp_register_style('shooting-gallery', plugins_url('shooting-gallery.css', __FILE__));

                wp_enqueue_style('owl-slider');
                wp_enqueue_style('featherlight');
                wp_enqueue_style('shooting-gallery');
            }

        }
        // Settings link in plugin management screen
        public function plugin_page_link($actions, $file) {
            if(false !== strpos($file, 'shooting-gallery'))
                $actions['settings'] = '<a href="options-general.php?page=shooting-gallery-admin">Settings</a>';
            return $actions;
        }
		public function admin_init() {
		    // Register settings
			register_setting( 'shooting_gallery_settings', 'shooting_gallery_settings', array( &$this, 'validate_options' ) );
            add_settings_section( 'shooting_gallery_settings', __( '','shooting_gallery' ), '', 'shooting_gallery_settings' );
            add_settings_field('shooting_gallery_post_types', __( 'Post Types', 'shooting_gallery' ), array( &$this, 'post_types_callback' ), 'shooting_gallery_settings', 'shooting_gallery_settings' );
            add_settings_field('shooting_gallery_min_width', __( 'Minimum Width', 'shooting_gallery' ), array( &$this, 'min_width_callback' ), 'shooting_gallery_settings', 'shooting_gallery_settings' );
            add_settings_field('shooting_gallery_min_height', __( 'Minimum Height', 'shooting_gallery' ), array( &$this, 'min_height_callback' ), 'shooting_gallery_settings', 'shooting_gallery_settings' );
            add_settings_field('shooting_gallery_max_width', __( 'Maximum Width', 'shooting_gallery' ), array( &$this, 'max_width_callback' ), 'shooting_gallery_settings', 'shooting_gallery_settings' );
            add_settings_field('shooting_gallery_max_height', __( 'Maximum Height', 'shooting_gallery' ), array( &$this, 'max_height_callback' ), 'shooting_gallery_settings', 'shooting_gallery_settings' );

        }
		public function admin_menu() {
			add_options_page(
				__( 'Shooting Gallery Settings', 'shooting-gallery' ),
				__( 'Shooting Gallery', 'shooting-gallery' ),
				'manage_options',
				'shooting-gallery-admin',
				array( $this, 'options_page_callback' )
			);
		}
		public function sg_add_options() {

        }
		public function options_page_callback() {
            echo '
			<div class="shooting-gallery-settings">
                <h2>Shooting Gallery Settings</h2>
                <form action="options.php" method="post">';

                    wp_nonce_field( 'update-options' );
                    settings_fields( 'shooting_gallery_settings' );
                    do_settings_sections( 'shooting_gallery_admin' );
                    do_settings_sections('shooting_gallery_settings');
                    '<p>';
                    submit_button( '', 'primary', 'sg_options_submit', false );
                    '</p>';
                '</form>
                <div class="clear"></div>
            </div>';

        }
        public function post_types_callback() {
            echo '
            <input id="post_types" name=shooting_gallery_settings[post_types]" type="text" value="' . esc_attr( implode( ',', $this->settings['post_types'] )) . '">
            <p>
                Enter post types as a list of comma seperated values.
            </p>'
            ;
        }
        public function min_width_callback() {
           echo '<input id="min-width" name="shooting_gallery_settings[min_width]" type="number" value="' . esc_attr( $this->settings['min_width']) . '">';
        }
        public function max_width_callback() {
            echo '<input id="max-width" name="shooting_gallery_settings[max_width]" type="number" value="' . esc_attr( $this->settings['max_width']) . '">';
        }
        public function min_height_callback() {
            echo '<input id="min-height" name="shooting_gallery_settings[min_height]" type="number" value="' . esc_attr( $this->settings['min_height']) . '">';
        }
        public function max_height_callback() {
            echo '<input id="max-height" name="shooting_gallery_settings[max_height]" type="number" value="' . esc_attr( $this->settings['max_height']) . '">';
        }
        public function validate_options( $input ) {

            if ( isset( $input['post_types'] )) {
                $input['post_types'] = explode( ',', sanitize_text_field( $input['post_types'] ));
            }
            if ( isset( $input['min_width'] )) {
                $input['min_width'] = sanitize_text_field( $input['min_width']);
            }
            if ( isset( $input['min_height'] )) {
                $input['min_height'] = sanitize_text_field( $input['min_height']);
            }
            if ( isset( $input['max_width'] )) {
                $input['max_width'] = sanitize_text_field( $input['max_width']);
            }
            if ( isset( $input['max_height'] )) {
                $input['max_height'] = sanitize_text_field( $input['max_height']);
            }

//            update_option('shooting_gallery_settings', $options);

            return $input;
        }

        public function add_meta_boxes() {

            // Using ACF for meta boxes and image selection.  Why re-invent the wheel?
			$post_types = $this->get_setting( 'post_types' );
            $locations = array();

            foreach ($post_types as $post_type) {
                $locations[] = array(
                    array (
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => $post_type,
                    )
                );
            };
            if( function_exists( 'acf_add_local_field_group' )):

                acf_add_local_field_group(array (
                    'key' => 'group_59c4452c339f8',
                    'title' => 'Shooting Gallery',
                    'fields' => array (
                        array (
                            'key' => 'field_59c44538a2aaa',
                            'label' => 'Gallery Images',
                            'name' => 'gallery_images',
                            'type' => 'gallery',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array (
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),
                            'min' => '',
                            'max' => '',
                            'insert' => 'append',
                            'library' => 'all',
                            'min_width' => $this->get_setting( 'min_width' ),
                            'min_height' => $this->get_setting( 'min_height' ),
                            'min_size' => '',
                            'max_width' => $this->get_setting( 'max_width' ),
                            'max_height' => $this->get_setting( 'max_height' ),
                            'max_size' => '',
                            'mime_types' => '',
                        ),
                    ),
                    'location' => $locations,
                    'menu_order' => 0,
                    'position' => 'normal',
                    'style' => 'default',
                    'label_placement' => 'top',
                    'instruction_placement' => 'label',
                    'hide_on_screen' => '',
                    'active' => 1,
                    'description' => '',
                ));

            endif;
		}
		public function sg_shortcode() {

		    $html = '<div class="sg-shortcode-location">';
            $html .= $this->render_gallery();
            $html .= '</div>';

            return $html;
		}
		public function render_gallery() {
		    global $post;
		    $images = get_field('gallery_images', $post);
		    $html = '<div id="shooting-gallery" class="owl-carousel">';

            foreach ( $images as $image ) {
                $html .= '<div class="item" data-featherlight="'. $image['url'] . '">';
                $html .= '<img src="' . $image['url'] . '">';
                $html .= '</div>';
            }
            $html .= '</div>';

            return $html;
        }
        public function default_gallery($content) {
            $output = $content;
            if (!has_shortcode(get_post()->post_content, 'shooting-gallery')) {
                $output = $this->render_gallery();
                $output .= $content;
            }
            return $output;
        }
		// PRIVATE FUNCTIONS
		private function initialize_settings() {
			$default_settings = array(
				'post_types' => array( 'post', 'page' ),
                'min_height' => 400,
                'min_width' => 300,
                'max_height' => 1920,
                'max_width' => 1080
			);
			$this->settings = get_option( 'shooting_gallery_settings', $default_settings );
		}
		private function get_setting( $key ) {
			if( $key && isset( $this->settings[$key] ) ) {
				return $this->settings[$key];
			}
			return null;
		}
	}
	ShootingGallery::Instance();
}
