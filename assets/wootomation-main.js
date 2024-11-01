jQuery('document').ready(function($){

	/**
	* Force Retrain AI
	* Ajax call to retrain AI
	*/
	var trainAI = function() {
		var $button = jQuery('#train_data');

		var init = function() {
			bindUIEvents();
		};

		var bindUIEvents = function(){
			if( $button.length ){
				$button.on('click', function(e){
					e.preventDefault();
					$(this).html('Starting...');
			      	startTraining();
			      	// getProducts();
				});
			}
		};

		function startTraining(){
			jQuery.ajax({
	        	type : "post",
	        	dataType : "json",
	        	url : ajaxurl,
	        	data : {
	        		action: "train_data",
	        	},
	        	beforeSend: function() {
	        	},
	        	success: function(response) {
	            	if(response.type == "success") {
						$('#training-updated').html('Training started... <a href="/wp-admin/admin.php?page=wc-status&tab=action-scheduler&status=pending&s=wootomation&action=-1&paged=1&action2=-1">Check progress</a>');
						$('#train_data').html('Started');
	            	}
		            else {
		            }
	        	}
		    })
		}

		return {
			init: init
		};
	}();

	/**
	* Show field
	*/
	var showField = function() {
		var $inputOutOfStock = $('.wootomation_input_exclude_out_of_stock').find('input');
		var $backorders = $('.wootomation_input_include_backorders');

		function init() {
			if( $inputOutOfStock.prop('checked') ){
				$backorders.removeClass('hide');
			}
			bindUIEvents();
		}

		function bindUIEvents(){
			$inputOutOfStock.on('change', showBackorders);
		}

		function showBackorders(){
			$backorders.toggleClass('hide');
		}

		return {
			init: init
		};
	}();

	trainAI.init();
	showField.init();
});
