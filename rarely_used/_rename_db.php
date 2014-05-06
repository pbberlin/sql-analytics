<?php



	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");


	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();

	if( ! $_HOST  ) {
		echo "please select host name<br>";
		echo render_layout_footer();
		exit;
	}


	if( $_HOST <> "b16.lvl.bln") {
		echo "only on b16<br>";
		echo render_layout_footer();
		exit;
	}


	render_elapsed_time_since("start");

	echo "<h1>change db name</h1>";



	$_db_src  = "monitoring-db-monitoring";
	$_db_dest = "d2";


	$_db_src  = "d1";
	$_db_dest = "content-db-crawler";

	flush_http();

	$_arr_t = execute_query("
		select table_name from information_schema.tables where table_schema = '$_db_src';
	");

	foreach($_arr_t as $_key_unused => $_arr_lp){
		$_lp_tn = $_arr_lp['table_name'];

		$_sql = "RENAME TABLE `{$_db_src}`.`{$_lp_tn}` TO `{$_db_dest}`.`{$_lp_tn}` ";
		vd($_sql );
		$_arr_r = execute_query($_sql);
		vd($_arr_r);
	
	}
	flush_http();


	echo render_layout_footer();





?>