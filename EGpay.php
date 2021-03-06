<?php
/*
Plugin Name: EvertPay
Plugin URI: http://evertpay.com
Description: Este es un plugin de pago
Author: Rejos-style
Version: 1.0
*/
/*
 * Esta accion hook registra nuestro clase php como una pasarela de pagos
 */
add_filter( 'woocommerce_payment_gateways', 'evert_add_gateway_class' );
function evert_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Evert_Payment'; 
	return $gateways;
}

/*
 * La misma clase, nota que esta dentro del plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'evert_init_gateway_class' );
function evert_init_gateway_class() {

	class WC_Evert_Payment extends WC_Payment_Gateway {

 		//constructor
 		public function __construct() {
			
			
			$this->id = 'evert'; // Id de la pasarela
			$this->icon = 'https://icons-for-free.com/iconfiles/png/512/credit+card+debit+card+master+card+icon-1320184902602310693.png'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // en caso de que necesites un formulario de targeta perzonalizado
			$this->method_title = 'Evert Payment';
			$this->method_description = 'Description of Evert payment'; // sera mostrados en las opciones de la pagina

			// la pasarela soporta subscripciones, reembolso, metodos de pagos guardados.
			
			$this->supports = array(
				'products'
			);

			// metodos con todo los campos de opciones
			$this->init_form_fields();

			// Carga las configuraciones
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->testmode = 'yes' === $this->get_option( 'testmode' );
			$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
			$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );

			// esta accion hook guarda las configuraciones
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// necesitamos personalizar Java Scripy para obtener un token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			
			// Puedes registrar a weebhook aqui
			// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

		

 		}

		
 		public function init_form_fields(){

			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Evert Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Credit Card',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via our super-cool payment gateway.',
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'test_publishable_key' => array(
					'title'       => 'Test Publishable Key',
					'type'        => 'text'
				),
				'test_private_key' => array(
					'title'       => 'Test Private Key',
					'type'        => 'password',
				),
				'publishable_key' => array(
					'title'       => 'Live Publishable Key',
					'type'        => 'text'
				),
				'private_key' => array(
					'title'       => 'Live Private Key',
					'type'        => 'password'
				)
			);
	
	 	}

		/**
		 * Lo necesitaras isi quieres que tu formulario perzonalizado de targetas */
		public function payment_fields() {

		// ok, muestro alguna descripcion antes del formulario
	if ( $this->description ) {
		// instrucciones para el modo de prueba
		if ( $this->testmode ) {
			$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
			$this->description  = trim( $this->description );
		}
		// muestra la descripcion en etiquetas
		echo wpautop( wp_kses_post( $this->description ) );
	}
 
	
	echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
 
	
	do_action( 'woocommerce_credit_card_form_start', $this->id );
 
	
	echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
		<input id="Evert_ccNo" type="text" autocomplete="off">
		</div>
		<div class="form-row form-row-first">
			<label>Expiry Date <span class="required">*</span></label>
			<input id="Evert_expdate" type="text" autocomplete="off" placeholder="MM / YY">
		</div>
		<div class="form-row form-row-last">
			<label>Card Code (CVC) <span class="required">*</span></label>
			<input id="Evert_cvv" type="password" autocomplete="off" placeholder="CVC">
		</div>
		<div class="clear"></div>';
 
	do_action( 'woocommerce_credit_card_form_end', $this->id );
 
	echo '<div class="clear"></div></fieldset>';
				 
		}

		
	 	public function payment_scripts() {

		
	
	 	}

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {

			// we need JavaScript to process a token only on cart/checkout pages, right?
	if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
		return;
	}

	// if our payment gateway is disabled, we do not have to enqueue JS too
	if ( 'no' === $this->enabled ) {
		return;
	}

	// no reason to enqueue JavaScript if API keys are not set
	if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
		return;
	}

	// do not work with card detailes without SSL unless your website is in a test mode
	if ( ! $this->testmode && ! is_ssl() ) {
		return;
	}

	// let's suppose it is our payment processor JavaScript that allows to obtain a token
	wp_enqueue_script( 'Evert_js', 'http://localhost:8080/');

	// and this is our custom JS in your plugin directory that works with token.js
	wp_register_script( 'woocommerce_Evert', plugins_url( 'evert.js', __FILE__ ), array( 'jquery', 'Evert_js' ) );

	// in most payment processors you have to use PUBLIC KEY to obtain a token
	wp_localize_script( 'woocommerce_Evert', 'Evert_params', array(
		'publishableKey' => $this->publishable_key
	) );

	wp_enqueue_script( 'woocommerce_Evert' );

		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {

			global $woocommerce;
 
			// we need it to get any order detailes
			$order = wc_get_order( $order_id );
		 
		 
			/*
			  * Array with parameters for API interaction
			 */
			$args = array(
		 
				
		 
			);
		 
			/*
			 * Your API interaction could be built with wp_remote_post()
			  */
			 $response = wp_remote_post( '{payment processor endpoint}', $args );
		 
		 
			 if( !is_wp_error( $response ) ) {
		 
				 $body = json_decode( $response['body'], true );
		 
				 // it could be different depending on your payment processor
				 if ( $body['response']['responseCode'] == 'APPROVED' ) {
		 
					// we received the payment
					$order->payment_complete();
					$order->reduce_order_stock();
		 
					// some notes to customer (replace true with false to make it private)
					$order->add_order_note( 'Hey, your order is paid! Thank you!', true );
		 
					// Empty cart
					$woocommerce->cart->empty_cart();
		 
					// Redirect to the thank you page
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
		 
				 } else {
					wc_add_notice(  'Please try again.', 'error' );
					return;
				}
		 
			} else {
				wc_add_notice(  'Connection error.', 'error' );
				return;
			}
					
	 	}

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {

			$order = wc_get_order( $_GET['id'] );
			$order->payment_complete();
			$order->reduce_order_stock();
		
			update_option('webhook_debug', $_GET);
					
	 	}
 	}
}

?>