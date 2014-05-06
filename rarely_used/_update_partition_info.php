<?php

	/*
		CREATE TABLE IF NOT EXISTS `offerMappingPartitionedByOfferId` (
		`offerId` int(11) NOT NULL,
		`partition_number` int(1) NOT NULL,
		`partitions_total` int(1) NOT NULL,
		`timestamp` int(10) NOT NULL DEFAULT '22',
		PRIMARY KEY (`partitions_total`,`partition_number`,`timestamp`)
		) ENGINE=InnoDB COMMENT='gives starting offerId for various partition sizes';
	*/


	if( ! $_REQUEST['doit'] ){
		echo "Script schreibt Partitionsgrenzen und braucht dafuer mehrere Minuten.<br>
			Append Parameter doit=1 to proceed.";
		exit;
	}


	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");

	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();
	render_elapsed_time_since("start",true);


	$_number_partitions = 10;
	#$_int_now =  microtime(true);
	$_int_now   = strtotime("now");
	$_int_today = strtotime( date("Y-m-d") );



	$_q_get_cardinality = "
		SELECT  table_name AS `Table`
				,index_name AS `Index`
				,Cardinality
				,GROUP_CONCAT(column_name ORDER BY seq_in_index) AS `Columns`
		FROM information_schema.statistics
		WHERE       1=1
				AND   table_name = 'offerMapping' 
				AND	index_name = 'PRIMARY'
		GROUP BY 1,2,3;
	";
	$_arr_approx_size = execute_query_get_first($_q_get_cardinality);
	$_int_approx_size = $_arr_approx_size['Cardinality'];
	$_approx_batch = floor($_int_approx_size/ $_number_partitions);
	vd( "$_int_approx_size = $_approx_batch * $_number_partitions", "Tabellenzeilen grob" );

	$_dbg_saved = $_GET['dbg'];
	$_GET['dbg'] = 7;
	for($i=0; $i< floor($_number_partitions);$i++){
		
		$_lp_count = $_approx_batch * $i ;
		$_arr_offer_id = execute_query_get_first( " 
			SELECT offerId FROM crawler.offerMapping 
			ORDER BY offerId ASC    
			LIMIT $_lp_count, 1 
		" );
		$_int_offer_id = $_arr_offer_id['offerId'];
		execute_query( "
			INSERT INTO crawler.offerMappingPartitionedByOfferId (offerId,partition_number,partitions_total,timestamp) 
				values($_int_offer_id,$i,$_number_partitions,$_int_now) ");
	}
	$_GET['dbg'] = $_dbg_saved;
	execute_query( " commit; ");


	echo render_layout_footer();


?>