
function po_updateMovewppopup() {
	jQuery.post(
	   wppopup.ajaxurl,
	   {
			'action':'wppopup_update_order',
			'_ajax_nonce': wppopup.ordernonce,
	      	'data': jQuery('#dragbody').tableDnDSerialize()
	   },
	   function(response){
		if(response != 'fail') {
			wppopup.ordernonce = response;
		} else {
			alert(wppopup.dragerror);
		}
	   }
	);
}

function po_setupReOrder() {

	//alert('here');

	//jQuery('tr.draghandle a.draganchor').click(function() {alert('click'); return false;});

	jQuery('#dragbody').tableDnD({
		onDragClass: 'dragging',
		dragHandle: 'check-drag',
		onDragStart: function( table, row ) {},
		onDrop: function( table, row ) {
			po_updateMovewppopup();
		}
	});

}

function po_confirmDelete() {
	if(confirm(wppopup.deletewppopup)) {
		return true;
	} else {
		return false;
	}
}

function po_MenuReady() {

	po_setupReOrder();

	jQuery('span.delete a').click(po_confirmDelete);

}

jQuery(document).ready(po_MenuReady);