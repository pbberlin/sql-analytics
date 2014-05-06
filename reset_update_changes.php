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

	$_arr_check = execute_query( "
			SELECT table_name 
			FROM information_schema.tables   
			WHERE 1=1
				AND table_name = 'offerMapping'
				AND table_schema = 'crawler'
				AND table_type = 'BASE TABLE'
	" );
	if( sizeof($_arr_check) <> 1 ){
		echo "crawler.offerMapping must exist<br>";
		echo render_layout_footer();
		exit;
	}


	render_elapsed_time_since("start");

	$_some_different_string =  microtime(true);


	echo "<H1>Reset the changes of Performance Testing on crawler.offerMapping</H1>";


	$_sql_1 =  "
			SELECT 
				  t2.offerId
				, t2.firstSeen
				, TIMESTAMPADD(SECOND, - t1.inc ,t2.firstSeen)  fs_corrected
			FROM offerMapping_repo t1 INNER JOIN offerMapping t2 USING (offerId)
			LIMIT 10;
	";

	$_sql_2 =  "
			SELECT COUNT(*) CHANGES_TOTAL
			FROM offerMapping_repo t1 INNER JOIN offerMapping t2 USING (offerId);
	";


	$_sql_3 =  "
			UPDATE
				offerMapping_repo t1 INNER JOIN offerMapping t2 USING (offerId)
			SET
				t2.firstSeen = TIMESTAMPADD(SECOND, - t1.inc ,t2.firstSeen);
	";

	$_sql_4 =  "
			TRUNCATE TABLE offerMapping_repo;

	";


	$_arr_sample = execute_query( $_sql_1 );

	if( sizeof($_arr_sample) < 1 ){
		echo "Nothing to do.<br>";
		echo render_layout_footer();
		exit;		
	} else {

		echo array_to_table($_arr_sample);

		echo array_to_table( execute_query( $_sql_2 ) );

		execute_query( $_sql_3 ) ;
		execute_query( $_sql_4 ) ;

		echo "Updated and truncated<br>";


	}

	echo render_layout_footer();






?>