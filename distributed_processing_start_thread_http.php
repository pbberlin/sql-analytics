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


	$_arr_options = get_runtime_info();
	if( ! isset($_arr_options) OR  ! sizeof($_arr_options) ) {
		echo "not initialized";
		echo render_layout_footer();
		exit();
	}
	extract( expand_options_array($_arr_options) );
	cm("reset");

	$_delta = $_optimal_number_processes - $_number_processes_running;

	cm("Running: $_number_processes_running - Recommended: $_optimal_number_processes - Delta: $_delta ");

	$_url_params = getLinkAnhang();
	$_me_script     = get_script_name();
	$_me_script_dir = dirname($_me_script);
	$_me_script_dir = str_ireplace("sql_analytics","sql_analytics_direct",$_me_script_dir );
	$_uri = "{$_me_script_dir}/distributed_processing_start_thread_srv.php?{$_url_params}";
	$_horst = getHostName();
	$_url = "http://{$_horst}{$_uri}";


	if( $_delta > 0 ){
		cm("Starting another process: <a href='$_url'  target='test' >manual test </a> ... ");
		exec_curl_multi($_url,1);
		cm(" ... finished<br><br>\n");
	} else {
		cm("Max number of processes ($_optimal_number_processes) running <br><br>\n");
	}

	echo cm("reset");




?>