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



	for($i=0;$i<5;$i++ ){
		$_y = 2013 - $i;
		if( $_y == 2013 ) continue;
		get_x($_y . "-01-01 14:00",true);
	}
	get_x($_y . "-01-01 14:00",true);
	get_x($_y . "-01-01 14:00",true);
	get_x($_y . "-01-01 14:00",true);



	for($i=0;$i<5;$i++ ){
		$_y = 2013 - $i;
		if( $_y < 2009 ) continue;
		get_x($_y . "-01-01 14:00",false);
	}




	echo render_layout_footer();

	function get_x( $_since = '2013-01-09' , $_dir = true ){

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
			select offerId,firstSeen 
			from crawler.offerMapping{$_table_version}  
			where firstSeen $_op1 '{$_since}' 
			order by firstSeen $_op2
			limit 2100000,2;
		";

		# 				/* AND not (firstSeen is null) */
		$_arr1 = execute_query($_sql_1);
		$_end  =  microtime(true);
		$_dauer = $_end - $_start;
		$_dauer = round($_dauer,4);
		echo array_to_table(  $_arr1  , array("heading" => "<br>order $_op2, processing-time: $_dauer secs", "cutoff" => 40) );
		vd($_sql_1);



	}


?>