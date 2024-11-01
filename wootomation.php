<?php
/**
 * Plugin Name: Wootomation - Machine Learning AI
 * Plugin URI: https://wpharvest.com/
 * Description: 🤖 Increase the sales of your WooCommerce shop by suggesting the right products to your customers, with the help of Machine Learning Artificial Intelligence. To take advantage of its power, just install and activate, it works out of the box.
 * Version: 2.0.3
 * Stable tag: 2.0.3
 * Author: Dragos Micu
 * Author URI: https://wpharvest.com/
 * Text Domain: Wootomation
 * WC tested up to: 5.5.2
 */

if( ! defined( 'ABSPATH' ) ) {
	die;
}

require_once dirname( __FILE__ ) . '/vendor/tgm/class-tgm-plugin-activation.php';
require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload.php';

require __DIR__ . '/includes/class-sales.php';
require __DIR__ . '/includes/class-train.php';
require __DIR__ . '/includes/class-similarity.php';

if( !class_exists( 'Wootomation' ) ){
	class Wootomation {

		public $plugin_name;
		public $plugin_version;
		public $ajax_url;
		public $suggestions_per_page;
		public $location;
		public $titles;

		function __construct() {
			$this->plugin_name = plugin_basename( __FILE__ );
			$this->plugin_version = '2.0.0';
			$this->ajax_url = admin_url('admin-ajax.php');
			$this->suggestions_per_page = get_option('wootomation_suggestions_per_page') ? get_option('wootomation_suggestions_per_page') : 3;
			$this->location = esc_attr( get_option('wootomation_after_cart_table') );
			$this->titles = apply_filters( 'wootomation_titles', array(
				'wootomation_after_cart_table_title' => get_option('wootomation_after_cart_table_title') ? get_option('wootomation_after_cart_table_title') : "People who bought this, also bought&hellip;",
			));

			// Add admin notices
			add_action( 'admin_notices', array( $this, 'wt_admin_notices' ) );

			// Register TGM PA
			add_action( 'tgmpa_register', array( $this, 'register_required_plugins' ) );

			// Enqueue assets
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			// Register ajax calls
			add_action( 'wp_ajax_train_data', array( $this, 'force_retrain' ) );

			// Add settings links
			add_filter( "plugin_action_links_$this->plugin_name", array( $this, 'add_settings_links' ) );

			// Add main functions
			if( $this->location ){
				add_action( 'woocommerce_after_cart_table', array( $this, 'ai_suggestions_display' ), 9 );
			}

			$this->init_setup();
		}

		/**
		 * Runs on activation
		 */
		public function activate() {
			// Set thank you notice transient
			set_site_transient( 'wt-admin-notices-on-install', true, 5 );
			set_site_transient( 'wt-init-indexing', true, 5 );
			set_site_transient( 'wt-admin-notices-after-one-month', true, 30 * DAY_IN_SECONDS );
			set_site_transient( 'wt-admin-notices-after-two-months', true, 60 * DAY_IN_SECONDS );
			$this->maybe_create_suggestions_db_table();
			$this->maybe_create_indexing_db_table();
			flush_rewrite_rules();
		}

		/**
		 * Runs on load
		 */
		public function init_setup() {
			$this->maybe_create_suggestions_db_table();
			$this->maybe_create_indexing_db_table();
			$training = new Wootomation_Train();
			// clears up old data
			// delete_site_option( 'wootomation-associator' );
			// delete_site_option( 'wootomation-product-sales' );
		}

		/**
		 * Runs on deactivation
		 */
		public function deactivate() {
			flush_rewrite_rules();
		}

		/**
		 * Adds admin notices
		 */
		public function wt_admin_notices() {
			// Adds notice of require WooCommerce plugin
			if( !class_exists('WooCommerce') ){
				?>
				<div class="notice-warning settings-error notice">
				   <p><?php _e('Wootomation is aiming to improve your sales on WooCommerce. Please install and activate WooCommerce first, then click "Force Retrain AI" or wait for the next purchase.', 'wootomation'); ?></p>
				</div>
				<?php
			}
			// Check and display on install notices
		    if( get_site_transient( 'wt-admin-notices-on-install' ) ){
		        ?>
		        <div class="updated woocommerce-message woocommerce-admin-promo-messages is-dismissible">
		            <p><?php _e('Thank you for installing! 🚀 The smart AI has been deployed and it will process the sales in the background...', 'wootomation')?></p>
		            <p><?php _e('Meanwhile, start by updating the <a href="/wp-admin/admin.php?page=wootomation">Settings</a> to best match your theme style.', 'wootomation')?></p>
		            <p><?php _e('You can view the <a href="/wp-admin/admin.php?page=wc-status&tab=action-scheduler&status=pending&s=wootomation&action=-1&paged=1&action2=-1">progress here</a>.', 'wootomation')?></p>
		        </div>
		        <?php
		        /* Delete transient, only display this notice once. */
		        delete_site_transient( 'wt-admin-notices-on-install' );
		    }
		}

		/**
		 * Enqueues admin scripts
		 */
		public function enqueue_scripts() {
			wp_enqueue_style( 'wootomation_main_css', plugin_dir_url( __FILE__ ) . 'assets/wootomation-main.css', array(), $this->plugin_version );
			wp_enqueue_script( 'wootomation_main_js', plugin_dir_url( __FILE__ ) . 'assets/wootomation-main.js', array(), $this->plugin_version, true );
		}

		/**
		 * Adds admin links on plugin page
		 */
		public function add_settings_links( $links ){
			$nonce = wp_create_nonce("wt-force-train");
			$setting_links = array(
				'<a href="#" id="train_data" data-nonce="'.$nonce.'">Force Retrain AI</a>',
				'<a href="/wp-admin/admin.php?page=wootomation" target="">Settings</a>',
				// '<a href="https://paypal.me/dragosmicu" target="_blank">Donate</a>',
			);

			return array_merge($setting_links, $links);
		}

		/**
		 * Creates suggestions table on activation
		 */
		public function maybe_create_suggestions_db_table() {
			if( get_option('wootomation_suggestions_db_version') ){
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . "wootomation_suggestions";
			$wootomation_suggestions_db_version = '1.0.0';
			$charset_collate = $wpdb->get_charset_collate();

			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

			    $sql = "CREATE TABLE $table_name (
			            `id` int NOT NULL AUTO_INCREMENT,
			            `product_1` int NOT NULL,
			            `product_2` int NOT NULL,
			            `similarity` float NOT NULL,
			            PRIMARY KEY  (ID)
			    )    $charset_collate;";

			    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			    dbDelta( $sql );
			    add_option( 'wootomation_suggestions_db_version', $wootomation_suggestions_db_version );
			}
		}

		/**
		 * Creates indexing table on activation
		 */
		public function maybe_create_indexing_db_table() {
			if( get_option('wootomation_indexing_db_version') ){
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . "wootomation_indexing";
			$wootomation_indexing_db_version = '1.0.0';
			$charset_collate = $wpdb->get_charset_collate();

			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

			    $sql = "CREATE TABLE $table_name (
			            `id` int NOT NULL AUTO_INCREMENT,
			            `user_id` int NOT NULL,
			            `full_name` text NOT NULL,
			            `product_id` int NOT NULL,
			            PRIMARY KEY  (ID)
			    )    $charset_collate;";

			    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			    dbDelta( $sql );
			    add_option( 'wootomation_indexing_db_version', $wootomation_indexing_db_version );
			}
		}

		/**
		 * Cleares up all queued training cron jobs
		 * so the plugin can create new ones on init
		 */
		public function force_retrain(){
			as_unschedule_all_actions('wootomation_ai_train_init');
			as_unschedule_all_actions('wootomation_ai_train_orders');
			as_unschedule_all_actions('wootomation_ai_train_products');
			$response['type'] = "success";
			$response = json_encode($response);
			echo $response;
			die();
		}

		/**
		 * Displays suggestions on the front end
		 * @return [type] [description]
		 */
		public function ai_suggestions_display(){
			global $wpdb;

			// get all products in cart
			$products_in_cart = Wootomation_Sales::get_cart_items();

			// get all suggestions based off all products
			$suggestions = array();
			$final_predictions = array();
			$number_of_suggestions = 0;

			if( get_site_option('wootomation-last-training', false) && !empty(get_site_option('wootomation-last-training')) ){
				foreach ($products_in_cart as $product_id) {
					// for each combination AI predict

					// Retrieve suggestions from the DB
					$predictions = $wpdb->get_results(
						$wpdb->prepare("SELECT product_2 FROM {$wpdb->prefix}wootomation_suggestions WHERE product_1=%d ORDER BY `similarity` DESC", $product_id), ARRAY_A
					);
					// add prediction to suggestions array
					foreach ($predictions as $prediction) {
						foreach( $prediction as $key => $value ){
							$suggestions[] = $value;
						}
					}
				}

				if( $suggestions ){
					// order suggestions by most frequest first
					$sorted_data = Wootomation_Sales::get_most_frequent($suggestions);

					// remove products from suggestions for various reasons
					$final_predictions = array();
					foreach ($sorted_data as $product_id) {

						// get options
						$optionOutOfStock = esc_attr( get_option('wootomation_exclude_out_of_stock') );
						$optionBackorders = esc_attr( get_option('wootomation_include_backorders') );
						$hideProduct = false;
						// if hide out of stock option is active
						if( $optionOutOfStock ){
							$product = wc_get_product( $product_id );
							$status = $product->get_stock_status();

							// if out of stock
							if( $status != 'instock' ){
								// if include backorders option is acitve
								if( $optionBackorders ){
									// if actual backorders are not allowed
									if( $status != 'onbackorder' ) {
										$hideProduct = true;
									}
								} else {
									$hideProduct = true;
								}
							}
						}
						// remove products from suggestions that are already in cart
						if( !$hideProduct && !in_array($product_id, $products_in_cart) ){
							$final_predictions[] = $product_id;
						}
					}
					$number_of_suggestions = count( $final_predictions );
				}
			}

			/**
			 * If number of suggestions not sufficient
			 * then get remaining number of products
			 * randomly, based off related categories
			 */
			if( $number_of_suggestions < $this->suggestions_per_page ){
				$needed_sugestions = $this->suggestions_per_page - $number_of_suggestions;

				$all_categories = array();
				foreach ($products_in_cart as $product_in_cart) {
					$categories = get_the_terms( $product_in_cart, 'product_cat' );

					if( $categories ){
						foreach ($categories as $cat) {
							$all_categories[] = $cat->slug;
						}
					}
				}
				array_unique($all_categories);

				if( $all_categories ){
					$args = array(
						'post_type'		 => 'product',
						'post_status'	 => 'publish',
						'posts_per_page' => $needed_sugestions,
						'post__not_in'   => $products_in_cart,
						'tax_query' => array(
							array (
								'taxonomy' => 'product_cat',
								'field' => 'slug',
								'terms' => $all_categories,
							)
						),
					);

					$fallback_products = new WP_Query($args);
					if( $fallback_products->have_posts() ):
						while( $fallback_products->have_posts() ): $fallback_products->the_post();
							$final_predictions[] = get_the_ID();
						endwhile;
					endif;

					// to try this instead of the above
					// $post_ids = wp_list_pluck( $latest->posts, 'ID' );
				}
			}


			/**
			 * Creates final display query using all found predictions
			 * Uses default WooCommerce product template
			 */
			$args = array(
				'post_type'		 => 'product',
				'post_status'	 => 'publish',
				'post__in'       => $final_predictions,
				'orderby'		 => 'post__in',
				'posts_per_page' => $this->suggestions_per_page,
			);

			$display_products = new WP_Query($args);

			if( $display_products->have_posts() ): ?>
				<section class="up-sells upsells product-suggestions products">

					<?php if( is_cart() ): ?>
						<h2><?php echo $this->titles['wootomation_after_cart_table_title']; ?></h2>
					<?php else: ?>
						<h2><?php echo apply_filters( 'wootomation_checkout_title', "Other people also bought&hellip;" ) ?></h2>
					<?php endif; ?>

					<?php woocommerce_product_loop_start(); ?>

					<?php while( $display_products->have_posts() ): $display_products->the_post();
						wc_get_template_part( 'content', 'product' );
					endwhile; ?>

					<?php woocommerce_product_loop_end(); ?>

				</section>
			<?php endif; ?>
			<?php wp_reset_postdata();
		}

		/**
		 * Registers required plugins
		 */
		public function register_required_plugins() {
			/*
			 * Array of plugin arrays. Required keys are name and slug.
			 */
			$plugins = array(

				array(
					'name'      => 'WooCommerce',
					'slug'      => 'woocommerce',
					'required'  => true,
				),

			);

			/*
			 * Array of configuration settings.
			 */
			$config = array(
				'id'           => 'wootomation',                 // Unique ID for hashing notices for multiple instances of TGMPA.
				'default_path' => '',                      // Default absolute path to bundled plugins.
				'menu'         => 'tgmpa-install-plugins', // Menu slug.
				'parent_slug'  => 'plugins.php',            // Parent menu slug.
				'capability'   => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
				'has_notices'  => true,                    // Show admin notices or not.
				'dismissable'  => false,                    // If false, a user cannot dismiss the nag message.
				'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
				'is_automatic' => false,                   // Automatically activate plugins after installation or not.
				'message'      => '',                      // Message to output right before the plugins table.
				'strings'      => array(
					'page_title'                      => __( 'Install Required Plugins', 'wootomation' ),
					'menu_title'                      => __( 'Install Plugins', 'wootomation' ),
					/* translators: %s: plugin name. */
					'installing'                      => __( 'Installing Plugin: %s', 'wootomation' ),
					/* translators: %s: plugin name. */
					'updating'                        => __( 'Updating Plugin: %s', 'wootomation' ),
					'oops'                            => __( 'Something went wrong with the plugin API.', 'wootomation' ),
					'notice_can_install_required'     => _n_noop(
						/* translators: 1: plugin name(s). */
						'This theme requires the following plugin: %1$s.',
						'This theme requires the following plugins: %1$s.',
						'wootomation'
					),
					'notice_can_install_recommended'  => _n_noop(
						/* translators: 1: plugin name(s). */
						'This theme recommends the following plugin: %1$s.',
						'This theme recommends the following plugins: %1$s.',
						'wootomation'
					),
					'notice_ask_to_update'            => _n_noop(
						/* translators: 1: plugin name(s). */
						'The following plugin needs to be updated to its latest version to ensure maximum compatibility with this theme: %1$s.',
						'The following plugins need to be updated to their latest version to ensure maximum compatibility with this theme: %1$s.',
						'wootomation'
					),
					'notice_ask_to_update_maybe'      => _n_noop(
						/* translators: 1: plugin name(s). */
						'There is an update available for: %1$s.',
						'There are updates available for the following plugins: %1$s.',
						'wootomation'
					),
					'notice_can_activate_required'    => _n_noop(
						/* translators: 1: plugin name(s). */
						'The following required plugin is currently inactive: %1$s.',
						'The following required plugins are currently inactive: %1$s.',
						'wootomation'
					),
					'notice_can_activate_recommended' => _n_noop(
						/* translators: 1: plugin name(s). */
						'The following recommended plugin is currently inactive: %1$s.',
						'The following recommended plugins are currently inactive: %1$s.',
						'wootomation'
					),
					'install_link'                    => _n_noop(
						'Begin installing plugin',
						'Begin installing plugins',
						'wootomation'
					),
					'update_link' 					  => _n_noop(
						'Begin updating plugin',
						'Begin updating plugins',
						'wootomation'
					),
					'activate_link'                   => _n_noop(
						'Begin activating plugin',
						'Begin activating plugins',
						'wootomation'
					),
					'return'                          => __( 'Return to Required Plugins Installer', 'wootomation' ),
					'plugin_activated'                => __( 'Plugin activated successfully.', 'wootomation' ),
					'activated_successfully'          => __( 'The following plugin was activated successfully:', 'wootomation' ),
					/* translators: 1: plugin name. */
					'plugin_already_active'           => __( 'No action taken. Plugin %1$s was already active.', 'wootomation' ),
					/* translators: 1: plugin name. */
					'plugin_needs_higher_version'     => __( 'Plugin not activated. A higher version of %s is needed for this theme. Please update the plugin.', 'wootomation' ),
					/* translators: 1: dashboard link. */
					'complete'                        => __( 'All plugins installed and activated successfully. %1$s', 'wootomation' ),
					'dismiss'                         => __( 'Dismiss this notice', 'wootomation' ),
					'notice_cannot_install_activate'  => __( 'There are one or more required or recommended plugins to install, update or activate.', 'wootomation' ),
					'contact_admin'                   => __( 'Please contact the administrator of this site for help.', 'wootomation' ),

					'nag_type'                        => '', // Determines admin notice type - can only be one of the typical WP notice classes, such as 'updated', 'update-nag', 'notice-warning', 'notice-info' or 'error'. Some of which may not work as expected in older WP versions.
				),
			);

			tgmpa( $plugins, $config );
		}
	}

	$wootomation = new Wootomation();

	// activation
	register_activation_hook( __FILE__, array( $wootomation, 'activate' ) );

	// deactivation
	register_deactivation_hook( __FILE__, array( $wootomation, 'deactivate' ) );

	require_once __DIR__ . '/includes/class-admin.php';

	$wootomation_settings = new Wootomation_Admin();
	$wootomation_settings->init();

}