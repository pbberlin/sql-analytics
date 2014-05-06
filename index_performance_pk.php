<?php



	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");

	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();

	if( ! $_HOST ) {
		echo "please select host name<br>";
		echo render_layout_footer();
		exit;
	}


	render_elapsed_time_since("start");

	$_next_oid = 100;

	$_factor = get_param("factor",11);

	for($i=0;$i<55;$i++ ){

		$_last_oid = $_next_oid;

		if( $_y == 2013 ) continue;
		$_next_oid = get_x($_last_oid);

		$_next_oid +=  1000*1000*$_factor;

		if( ! $_next_oid ) break;

	}







	echo render_layout_footer();

	function get_x( $_offfer_id_cmp = 1000 , $_dir = true ){

		$_table_version = get_param("v","");


		if( $_dir ){
			$_op1 = " > ";
			$_op2 = " asc ";
		} else {
			$_op1 = " < ";
			$_op2 = " desc ";
		}


		$_start =  microtime(true);
		$_sql_1 = "
			select offerId oid 
			from crawler.offerMapping{$_table_version}  
			where offerId  $_op1 $_offfer_id_cmp 
			order by offerId $_op2
			limit 2100000,2;
		";

		# 				/* AND not (firstSeen is null) */
		$_arr1 = execute_query_get_first($_sql_1);
		$_end  =  microtime(true);
		$_dauer = $_end - $_start;
		$_dauer = round($_dauer,4);
		
		$_str = $_arr1['oid'];
		$_str = substr($_str,0,-6) . "." . substr( substr($_str,-6),0,3) . "." . substr($_str,-3);

		#echo array_to_table(  $_arr1  , array("heading" => "<br>order $_op2, processing-time: $_dauer secs", "cutoff" => 40) );
		echo "$_str - $_dauer secs <br>";
		vd($_sql_1);

		return $_arr1['oid'];


	}


?>