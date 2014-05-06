<?php

	/*
			see docu of _simulate_load_random_offerid_with_update.php

			this script about prepared statements

			prepared statements increase single select speed by 33 percent - 
				thus nice - but not too cool

	*/

	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");

	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();
	render_elapsed_time_since("start");

	$_some_different_string =  microtime(true);


	$_number_singular_requests = 992;	# number of records singularily retrieved
	$_batch_size = 1;
	if( $_REQUEST['batch_size'] ) $_batch_size = $_REQUEST['batch_size'];
	$_number_partitions = 10;		
	$_j_max = 3;		


	$_sql_no_cache = "SQL_NO_CACHE";
	if( $_REQUEST['dbg_cache'] ) $_sql_no_cache = "";



	$_q_get_offer_id_boundaries = "
		SELECT 	  'xx'
					, min(offerId) offer_ids_start
					, max(offerId) offer_ids_stop
					, max(offerId) - min(offerId) offer_ids_range
		FROM 		crawler.offerMappingPartitionedByOfferId
		WHERE 			1=1
					AND 	partitions_total = {$_number_partitions}
					AND	timestamp = (SELECT max(timestamp) FROM crawler.offerMappingPartitionedByOfferId WHERE partitions_total = {$_number_partitions} )
					AND	partition_number >= 4
			
		GROUP BY partitions_total
		ORDER BY partition_number
	";
	$_arr_offer_id_boundaries = execute_query_get_first($_q_get_offer_id_boundaries);
	#vd($_arr_offer_id_boundaries);
	extract ( expand_options_array($_arr_offer_id_boundaries, '' ) );
	#vd( " $_offer_ids_start, $_offer_ids_stop, $_offer_ids_range " );


	# create RANDOM set of offerIds ACROSS entire table
	for($i=1; $i<=$_number_singular_requests;$i++){
		$_lp_rand = rand($_offer_ids_start,$_offer_ids_stop);
		#vd($_lp_rand,"zufallszahl $i<br>");
		$_arr_random_offer_ids[] = $_lp_rand;
	}

	#vd( $_arr_random_offer_ids );




	# create prepared statement for later re-use
	$_q_prep = "
				SELECT 	$_sql_no_cache
							offerId, bokey, categoryBokey, shopId, isDirty, firstSeen, offerPackageId 
				FROM 		crawler.offerMapping  
				USE INDEX (PRIMARY)
				WHERE 	offerId =  ?

	"  ;
	$_prep_stat = $MYSQLI->prepare( $_q_prep );

	



	# links to jump labels
	for($j=1; $j<=$_j_max;$j++){
		
		echo "<a href='#a{$j}'>Outer Loop Nr.  $j</a> &nbsp; ";
		if( ($j % 4) == 0 ) echo "<br>";
	}
	echo "<br>";




	for($j=1; $j<=$_j_max;$j++){


		echo "<a name='a{$j}'></a>";
		echo "<H1>Outer Loop $j</H1>";


		if( $_batch_size>1 ){

			for( $i=1 ; $i <=  ceil($_number_singular_requests / $_batch_size) ; $i++ ){


				$_arr_slice = array_slice( $_arr_random_offer_ids, $_batch_size * ($i-1) ,   $_batch_size );
				$_str_random_offer_ids = implode($_arr_slice, ", ");
				echo " $i ";


				$_query_workload_batched = " 
					SELECT 	$_sql_no_cache
								offerId, bokey, categoryBokey, shopId, isDirty, firstSeen, offerPackageId, '{$_some_different_string}' 
					FROM 		crawler.offerMapping  
					USE INDEX (PRIMARY)
					WHERE 	offerId IN ( {$_str_random_offer_ids} )
					;
				";
				$_arr_dummy = execute_query( $_query_workload_batched );


			}

		
				


		} else {


			foreach($_arr_random_offer_ids as $_lp_counter_inner => $_lp_offer_id){

				#echo "<p>inner loop $_lp_counter_inner - offer id $_lp_offer_id</p>";


				if( $_REQUEST['dbg_prepared_statement'] ){
					# Bind parameters - s - string, b - boolean, i - int, etc 
					$_prep_stat->bind_param("i", $_lp_offer_id );
					$_prep_stat->execute();
					$_prep_stat->bind_result($res1,$res2,$res3,$res4,$res5,$res6,$res7);	# new variable
					$_prep_stat->fetch();
					#echo "$res1,$res2,$res3,$res4,$res5,$res6,$res7 <br>";

				}else {

					$_query_workload_1 = " 
						SELECT 	$_sql_no_cache
									offerId, bokey, categoryBokey, shopId, isDirty, firstSeen, offerPackageId, '{$_some_different_string}' 
						FROM 		crawler.offerMapping  
						USE INDEX (PRIMARY)
						WHERE 	offerId = '{$_lp_offer_id}'
						;
					";
					#vd(  str_replace( "\t","   ", $_query_workload_1) );
					$_arr_lp_res = execute_query( $_query_workload_1 );
					#echo array_to_table($_arr_lp_res);

				}

				#render_elapsed_time_since("\t inner Durchlauf $_cntr",true);



				render_elapsed_time_since("inner loop $_lp_counter_inner - offer id $_lp_offer_id",false);
				#sleep(1);

					
			}


		}




			
		render_elapsed_time_since("Outer Loop $j",true);


	}



	echo render_layout_footer();



?>