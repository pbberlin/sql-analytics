<?php

	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");
	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require

	if( ! $_HOST ) {
		echo "please select host name<br>";
		echo render_layout_footer();
		exit;
	}




	require_once("distributed_processing_lib.php");


	$_arr_options = init_queue();
	if( ! isset($_arr_options) OR  ! sizeof($_arr_options) ) {
		echo cm("not initialized");
		echo render_layout_footer();
		exit();
	}
	extract( expand_options_array($_arr_options) );

	#vd("count_batches = $_count_batches");

	for( $i = 0; $i< $_count_batches; $i++){
		process_one_batch($i);
		echo "$i Batch <br>\n";
	}




?>