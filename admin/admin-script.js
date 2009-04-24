jQuery(document).ready(function($){
    $("#import_format").change(function () {
          if ( 'csv' == $(this).val() )
		$(".tr-import-delimiter").css('display','table-row');
	  else
		$(".tr-import-delimiter").css('display','none');
        })
        .change();

    $("#export_format").change(function () {
          if ( 'csv' == $(this).val() )
		$(".tr-export-delimiter").css('display','table-row');
	  else
		$(".tr-export-delimiter").css('display','none');
        })
        .change();

    $("#options_use_global_css input").click(function () {
	  if( $('#options_use_global_css input:checked').val() ) {
        $('#options_css_filename select').removeAttr("disabled");
	  } else {
        $('#options_css_filename select').attr("disabled", true);
	  }
      return true;
	});

    $("#options_use_tableheadline input").click(function () {
	  if( $('#options_use_tableheadline input:checked').val() && $('#tablesorter_enabled').val() ) {
        $('#options_use_tablesorter input').removeAttr("disabled");
	  } else {
        $('#options_use_tablesorter input').attr("disabled", true);
	  }
      return true;
	});

    $('.postbox h3, .postbox .handlediv').click( function() {
	$($(this).parent().get(0)).toggleClass('closed');
    } );

});