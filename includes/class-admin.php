<?php
/**
 * Admin
 *
 * Admin page settings
 *
 * @package Wootomation
 * @version 1.1.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 */
class Wootomation_Admin {

	public function init(){
		add_action('admin_menu', array($this, 'add_admin_page'));
		add_action('admin_init', array($this, 'add_admin_options'));
	}

	public function add_admin_page(){
		add_submenu_page( 'woocommerce', 'Wootomation', 'Wootomation', 'manage_options', 'wootomation', array($this, 'admin_page_main') );
	}

	public function admin_page_main(){?>
		<div class="wrap">
			<div class="row">
				<div class="col-12">
			        <h1><?php _e( 'Wootomation Settings', 'wootomation' ); ?></h1>
			        <?php settings_errors();  ?>
				</div>
		        <div class="col-12 col-md-8">
		        	<table class="form-table" role="presentation">
		        		<tbody>
							<tr>
		        				<th scope=row>Last updated</th>
		        				<td colspan="2">
									<span id="training-updated">
										<?php
										$last_training = get_site_option('wootomation-last-training');
										if( $last_training ){
											if( is_numeric($last_training) ){
												echo date("d-m-Y - h:ia", $last_training);
											} else {
												echo $last_training;
											}
										} else {
											echo 'Never';
										}
										?>
									</span>
								</td>
		        			</tr>
		        			<tr>
		        				<th scope="row">Background Training</th>
		        				<td>
		        					<a href="#" id="train_data" class="btn btn-primary">Force Retrain</a>
		        				</td>
		        			</tr>
		        		</tbody>
		        	</table>

		        	<form method="post" action="options.php">
			        	<?php settings_fields( 'wootomation-settings' ); ?>
			        	<?php //settings_fields( 'wootomation-locations' ); ?>
			        	<?php do_settings_sections( 'woocommerce' ); ?>
			        	<?php submit_button(); ?>
			        </form>
		        </div>
		        <div class="col-12 col-md-4">
		        	<div class="wootomation__sidebar">
		            	<p><?php echo __('Hey! 👋 Thanks for using Wootomation, that\'s wonderful! If it helped you increase your sales, please leave a quick ', 'wootomation') . '<a href="https://wordpress.org/support/plugin/wootomation/reviews/#new-post" target="_blank">⭐⭐⭐⭐⭐</a>' . __(' to help spread the word and motivate me to keep improving it.', 'wootomation')?></p>
		        	</div>
					<div class="wootomation__sidebar">
						<img src="https://ps.w.org/documents-for-woocommerce/assets/banner-772x250.png" alt="Documents for WooCommerce" style="width: 100%">
		            	<p><?php echo __('Looking to attach documents, guides or any other documents to your WooCommerce products?', 'wootomation'); ?>.</p>
		            	<p><?php echo __('Try our free plugin ', 'wootomation') . '<a href="https://wordpress.org/plugins/documents-for-woocommerce/" target="_blank">Documents for WooCommerce</a>'; ?>.</p>
		        	</div>
		        </div>
			</div>
	    </div>
	<?php }

	public function add_admin_options(){
		// Register settings
		register_setting( 'wootomation-settings', 'wootomation_suggestions_per_page' );
		register_setting( 'wootomation-settings', 'wootomation_after_cart_table' );
		register_setting( 'wootomation-settings', 'wootomation_after_cart_table_title' );
		register_setting( 'wootomation-settings', 'wootomation_exclude_out_of_stock' );
		register_setting( 'wootomation-settings', 'wootomation_include_backorders' );

		// Register sections
		add_settings_section( 'wootomation-settings', 'General Settings', array($this, 'wootomation_settings'), 'woocommerce' );

		// Register fields
		add_settings_field( 'wootomation-suggestions_per_page', 'Suggestions to show' , array($this, 'wootomation_suggestions_per_page'), 'woocommerce', 'wootomation-settings' );
		add_settings_field( 'wootomation-after-cart-table', 'Add to cart (after cart table)' , array($this, 'wootomation_after_cart_table'), 'woocommerce', 'wootomation-settings' );
		add_settings_field( 'wootomation-after-cart-table-title', 'Title' , array($this, 'wootomation_after_cart_table_title'), 'woocommerce', 'wootomation-settings' );
		add_settings_field( 'wootomation-exclude-out-of-stock', 'Exclude out of stock' , array($this, 'wootomation_exclude_out_of_stock'), 'woocommerce', 'wootomation-settings', array( "class" => "wootomation_input_exclude_out_of_stock" ) );
		add_settings_field( 'wootomation-include-backorders', 'Include backorders' , array($this, 'wootomation_include_backorders'), 'woocommerce', 'wootomation-settings', array( "class" => "wootomation_input_include_backorders hide" ) );
	}

	public function wootomation_settings(){
		// do nothing
	}

	public function wootomation_locations(){
		echo '<p>Select where to place Wootomation. 80+ locations coming soon...</p>';
	}

	public function wootomation_suggestions_per_page(){
		$option = esc_attr( get_option('wootomation_suggestions_per_page') );
		echo '<input type="number" name="wootomation_suggestions_per_page" value="'.$option.'" placeholder="3" />';
	}

	public function wootomation_after_cart_table(){
		$option = esc_attr( get_option('wootomation_after_cart_table') );
		$html = '<label class="switch">';
			$html .= '<input type="checkbox" name="wootomation_after_cart_table" value="1"' . checked( 1, $option, false ) .  '/>';
			$html .= '<span class="slider round"></span>';
		$html .= '</label>';
		echo $html;
	}

	public function wootomation_after_cart_table_title(){
		$option = esc_attr( get_option('wootomation_after_cart_table_title') );
		echo '<input type="text" name="wootomation_after_cart_table_title" value="'.$option.'" placeholder="People who bought this, also bought…" class="regular-text" />';
	}

	public function wootomation_exclude_out_of_stock(){
		$option = esc_attr( get_option('wootomation_exclude_out_of_stock') );
		$html = '<label class="switch">';
			$html .= '<input type="checkbox" name="wootomation_exclude_out_of_stock" value="1"' . checked( 1, $option, false ) .  '/>';
			$html .= '<span class="slider round"></span>';
		$html .= '</label>';
		echo $html;
	}

	public function wootomation_include_backorders(){
		$option = esc_attr( get_option('wootomation_include_backorders') );
		$html = '<label class="switch">';
			$html .= '<input type="checkbox" name="wootomation_include_backorders" value="1"' . checked( 1, $option, false ) .  '/>';
			$html .= '<span class="slider round"></span>';
		$html .= '</label>';
		echo $html;
	}

}