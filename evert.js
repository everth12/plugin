var successCallback = function(data) {

	var checkout_form = $( 'form.woocommerce-checkout' );

	//añade un token para ocultar el campo de entrada
	
	// console.log(data) para encontrar el token
	checkout_form.find('#misha_token').val(data.token);
	//desactiva el evento de la funcion de la solicitud del token
	
	checkout_form.off( 'checkout_place_order', tokenRequest );

	// Envia el formulario ahora
	checkout_form.submit();

};

var errorCallback = function(data) {
    console.log(data);
};

var tokenRequest = function() {

	// aqui sera la funcion de la pasarela de pago que procesa toda la informacion de la targeta del formulario,
	// talvez se necesitara tu llave de api publica
	// si lanza successCallback() exitoso y errorCallback en fallo
	return false;
		
};

jQuery(function($){

	var checkout_form = $( 'form.woocommerce-checkout' );
	checkout_form.on( 'checkout_place_order', tokenRequest );

});