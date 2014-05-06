<?php

/*

*/



	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");

	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require



	require_once("distributed_processing_lib.php");








	$_arr_options = get_runtime_info();
	if( ! isset($_arr_options) OR  ! sizeof($_arr_options) ) {
		echo cm("not initialized");
		echo render_layout_footer();
		exit();
	}
	extract( expand_options_array($_arr_options) );

	echo $_table_running_processes;
	echo $_table_global;


	$_sql_1 = "
		SELECT 
				  round( AVG( duration_processing),2) AVG_duration_processing
				, round( AVG( duration_cursor),2)     AVG_duration_cursor
				, round( AVG( duration_processing/duration_cursor),1)  'Optimal number<br>of parallel processes'
				, round( AVG( billion_per_day),1)  'AVG Billion per day'
		FROM
				( 	SELECT * 
					FROM offerMapping_by_firstSeen_registry 
					ORDER BY last_update DESC 
					LIMIT 0,20  /* last 20 enries */
				) t2

	";
	$_arr_ratios = execute_query($_sql_1);
	echo array_to_table(  $_arr_ratios , array("heading" => "<br>ratio chunk-retrieval to processing-time", "cutoff" => 40) );

	$_optimal_number_processes = $_arr_ratios[0]['Optimal number of parallel processes'];
	$_optimal_number_processes = floor($_optimal_number_processes);
	if( $_optimal_number_processes <1 ) $_optimal_number_processes = 1;






	$_sql_3 = "
		SELECT 
			process_id 
			,FROM_UNIXTIME(time_start) time_start 	
			,last_update 
			, concat_ws( '', size_cursor, ' of ' , size_queue   ) 'cursor<br>of_total'
			,billion_per_day 			'billion<br>per day'
			,duration_cursor       'cd' 
			,duration_processing   'pd'
			,msg



		FROM  offerMapping_by_firstSeen_registry 
		ORDER BY (last_update) DESC 
	";

	$_str_dis = array_to_table(  execute_query($_sql_3) , array("heading" => "<br>single process status", "cutoff" => 40) );

	$_repl = date("Y-m-d");
	$_str_dis = str_replace("$_repl","",$_str_dis );
	echo $_str_dis ;



?>