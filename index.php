<?php

/*
 * Plugin Name:  Bleumi Payments for WC Vendors Marketplace
 * Description:  Support split payments in Bleumi Payments for WC Vendors Marketplace
 * Version:      1.0.4
 * Author:       Bleumi Inc
 * Author URI:   https://bleumi.com/
 * License:      Copyright 2020 Bleumi, MIT License
*/

add_filter('wc_bleumi_complete_payment', 'wc_wcv_bleumi_complete_payment');
function wc_wcv_bleumi_complete_payment($order_id) {
	WCV_Commission::set_order_commission_paid($order_id);
}

add_filter('wc_bleumi_process_payment', 'wc_wcv_bleumi_process_payment', 10, 2);
function wc_wcv_bleumi_process_payment(&$order, &$params) {
	$order_total = $order->get_total();
	$split = array();

	$vendors_due = WCV_Vendors::get_vendor_dues_from_order( $order);
	$self_perc = 100;
	foreach ( $vendors_due as $vendor_id => $commission ) {
		if($vendor_id == 1) {
			continue;
		}
		
		$percentage = round(($commission['total'] / $order_total) * 100, 4);
		$self_perc = $self_perc - $percentage;

		array_push($split, array(
			'destination' => get_user_meta($vendor_id, 'wc_wcv_bleumi_vendor_id', true),
			'percentage' => $percentage,
		));
	}

	array_push($split, array(
		'destination' => 'self',
		'percentage' => $self_perc
	));

	$params['split'] = $split;
}

add_filter('woocommerce_available_payment_gateways', 'wc_wcv_bleumi_disable_unknown_vendor');
function wc_wcv_bleumi_disable_unknown_vendor($gws) {
	if (is_admin()) {
		return $gws;
	}

	foreach(WC()->cart->get_cart() as $item) {
		$product_id = $item['product_id'];
		
		$vendor_id = WCV_Vendors::get_vendor_from_product($product_id);
		if ($vendor_id == 1) {
			continue;
		}
		
		$bleumi_id = get_user_meta($vendor_id, 'wc_wcv_bleumi_vendor_id', true);
		if(empty($bleumi_id) || !preg_match("/wallet:.+/i", $bleumi_id)) {
			unset($gws['bleumi']);
		}
	}

	return $gws;
}

add_filter('edit_user_profile_update', 'wc_wcv_bleumi_save_vendor_id');
function wc_wcv_bleumi_save_vendor_id($user_id) {
	if ( isset( $_POST['wc_wcv_bleumi_vendor_id'] ) ) {
		$bleumi_id = sanitize_text_field($_POST['wc_wcv_bleumi_vendor_id']);
		if(!empty($bleumi_id) && preg_match("/wallet:.+/i", $bleumi_id)) {
			update_user_meta( $user_id, 'wc_wcv_bleumi_vendor_id', $bleumi_id );
		}
	}
}

add_filter('wcvendors_admin_before_bank_details', 'wc_wcv_bleumi_before_bank_details');
function wc_wcv_bleumi_before_bank_details($user) { ?>
	<tr>
		<th>
			<label for="wc_wcv_bleumi_vendor_id">Bleumi Vendor ID
				<span class="description"></span>
			</label>
		</th>
		<td>
			<input type="text" name="wc_wcv_bleumi_vendor_id" id="wc_wcv_bleumi_vendor_id" value="<?php echo esc_attr(get_user_meta( $user->ID, 'wc_wcv_bleumi_vendor_id', true )); ?>" class="regular-text">
		</td>
	</tr>
<?php }
