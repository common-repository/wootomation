<?php
/**
 * Sales
 *
 * Gets sales.
 *
 * @package Wootomation
 * @version 1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sales class.
 */
class Wootomation_Sales {

	/**
	 * DB lookup to get all sales and products
	 * DEPRECATED
	 */
	public static function get_sales() {
		global $wpdb;
		$product_sales = array();

		$date_from = "2000-01-01";
		$date_to = date("Y-m-d");

		$results = $wpdb->get_results( "SELECT ID FROM $wpdb->posts
            WHERE post_type = 'shop_order'
            AND post_date BETWEEN '{$date_from}  00:00:00' AND '{$date_to} 23:59:59'
        ");

		foreach( $results as $result ) {

			$order_id = $result->ID;
			$order = wc_get_order( $order_id );
			$order_data = $order->get_data();
			$items = $order->get_items();

			foreach( $items as $item ){

				$product_id = $item->get_product_id();
				$product_name = $item->get_name();

				$terms = get_the_terms($product_id, 'product_type');
				$product_type = (!empty($terms)) ? sanitize_title(current($terms)->name) : 'simple';

		      	$orders = array(
		        	'User ID' => $order->get_customer_id(),
		        	'Full Name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
		        	'Product ID' => $product_id,
		        	'Product Title' => $product_name,
		        	'Product Type' => $product_type,
		      	);

		      	array_push( $product_sales, $orders );
			}
		}

		$sales = array();
		foreach( $product_sales as $sale ){
			$key = $sale["Full Name"];
			$value = $sale["Product ID"];
			$sales[ $key ][] = $value;
		}

		return $sales;
	}

	public static function get_count_shop_orders(){
		global $wpdb;


		$results = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts
            WHERE post_type = 'shop_order'
		");

		// // Force limit no of orders to analyse
		// if( $results > 100 ){
		// 	$results = 100;
		// }

        return $results;
	}

	public static function get_count_products(){
		global $wpdb;

		$results = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts
            WHERE post_type = 'product'
        ");

        return $results;
	}

	public static function get_products(){
		global $wpdb;

		$results = $wpdb->get_results( "SELECT ID FROM $wpdb->posts
            WHERE post_type = 'product'
        ", ARRAY_A);

        return $results;
	}

	public static function get_cart_items(){

		global $woocommerce;

		$items = $woocommerce->cart->get_cart();
		$products_in_cart = array();

		foreach($items as $item => $values) {
            $_product = wc_get_product( $values['data']->get_id());
            $product_id = $values['data']->get_id();
			array_push($products_in_cart, $product_id);
        }

        return $products_in_cart;
	}

	/**
	 * Sort out data by most frequent
	 */
	public static function get_most_frequent($data){

		if( empty( $data ) ) return false;

		// combine all arrays
		// $data = call_user_func_array('array_merge', $data);

		// count frequency
		$data_freq = array_count_values($data);
		// sort in decreasing order
		arsort($data_freq);
		// $sorted_data contains the keys of sorted array
		$sorted_data = array_keys($data_freq);

		return $sorted_data;
	}
}