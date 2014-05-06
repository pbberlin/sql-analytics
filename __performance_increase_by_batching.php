<?php
#	Reads and updates across huge table offerMapping (>90 GB Total). Each run uses a random, evenly distributed sample of offerIds

	/*

		result on SSD based server: 
			first run:  		2500 reads per second	(reading from fast SSD disk, >90 percent not in innodb_buffer)
			subsequent runs:  tenfold increase - reading mostly form innodb_buffer: 25.000 selects per second

			single requests incur avg. 1 ms network latency => initial and subsequent selects are SIGNIFICANTLY slowed down
				load becomes network bound

			batched requests: per 1000 request bundled - a full second round-trip time is saved

			prepared statements increase speed by up to 33 percent

			batched inserts accelerate like batched selects

			batched updates accelerate five times slower, because of random index updates, but still accerlate ten-fold

			wrapping batches into transactions does not increase speed


	*/



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





	$_number_singular_ops = get_param('count_ops',399);	# number of distinct records retrieved / updated alltogether
	#$_number_singular_ops = 9;

	$_batch_size   = get_param( 'batch_size' , 100 );				# number of records per round trip

	$_number_network_round_trips = ceil($_number_singular_ops / $_batch_size);			# number of roundtrips to SQL server

	$_glob_repetitions = get_param("glob_repetitions",3);		# in order to research innodb_buffer optimization - we repeat the above stuff $_glob_repetitions times

	$_sql_no_cache = get_param( 'myisam_result_buffer'  ," SQL_NO_CACHE ");						# circumvent the mysql query cache - though it should be disabled anyway

	$_do_update = get_param('do_update','SELECT');	# default is do select only

	$_do_transaction = get_param('do_transaction',0);


	echo "<H1>Performance Testing on crawler.offerMapping</H1>";
	#echo "<br>$_number_singular_ops ops, $_batch_size ops per batch, $_number_network_round_trips roundtrips, $_glob_repetitions repetitions, transactions -$_do_transaction-</H1>";

	$_href = get_script_name(). "/myisam_result_buffer=&dbg=1&do_update={$_do_update}&batch_size={$_batch_size}&count_ops={$_number_singular_ops}&do_transaction={$_do_transaction}";

	echo "<p><a href='$_href' style='font-weight:normal;font-size:9px;'>$_href</a></p><br>";

	$_hid = getHiddenInputs();
	$_c1w = 120;
	$_c2w = 220;
	$_rm  = 20;

	echo "<form>";
	echo $_hid;
	echo render_inline_block("Anzahl Operationen: " ,false, "min-width:{$_c1w}px" );
	echo render_inline_block("<input  type='text' name='count_ops'  value='{$_number_singular_ops}' style='width:40px;margin-right:{$_rm}px;' />");
	echo render_inline_block("Block-Größe: "  );
	echo render_inline_block("<input  type='text' name='batch_size' value='$_batch_size' style='width:32px;margin-right:{$_rm}px;' />");
	echo render_inline_block("=>  <b>{$_number_network_round_trips}</b> Blöcke &nbsp;  =>  <b>{$_number_network_round_trips}</b> Netzwerk Roundtrips"  );

	echo render_br();

	echo render_inline_block("<u>W</u>iederholungen: " ,false, "min-width:{$_c1w}px" );
	echo render_inline_block("<input  type='text' name='glob_repetitions'  value='{$_glob_repetitions}' style='width:20px;aamargin-right:{$_rm}px;'  accesskey='w' />");
	echo render_inline_block("Erste Wiederholung von Platte, weitere aus 'buffer_pool'" ,80, "font-size:9px;line-height:9px;margin-right:{$_rm}px;" );

	echo render_inline_block("MyISAM Query Cache: "  );
	$_arr_cache = array('SQL_NO_CACHE'=> 'SQL_NO_CACHE','SQL_CACHE'=>'SQL_CACHE');
	$_sel_query_cache = render_select('myisam_result_buffer',$_arr_cache);
	echo render_inline_block($_sel_query_cache);

	echo render_br();

	echo render_inline_block("Select or Update: ",false,"min-width:{$_c1w}px"  );
	$_arr_sel_upd = array('SELECT'=> 'SELECT','UPDATE'=>'UPDATE');
	$_sel_sel_upd = render_select('do_update',$_arr_sel_upd);
	echo render_inline_block($_sel_sel_upd,false, "margin-right:{$_rm}px;");
	#echo render_br();

	echo render_inline_block("bei Update Blöcke in Transaktion einschließen: ",$_c1w,"min-width:{$_c1w}px;line-height:12px;"  );
	$_arr_transactions = array('0'=> 'keine Transaktion','1'=>'MIT Transaktion');
	$_sel_transactions = render_select('do_transaction',$_arr_transactions);
	echo render_inline_block($_sel_transactions);
	echo render_br();


	echo render_inline_block(" "  , $_c1w);
	echo render_inline_block("<input type='submit' value='submit'  accesskey='s' style='width:{$_c2w}px; ' />");

	echo "<br><br></form><br>";



	#render_innodb_status();


	# conceive some random offerId's - spread evenly across offerMapping
	# ==========================================================================
	$_number_partitions = 10;		
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


	# create RANDOM set of offerIds ACROSS entire table
	for($i=1; $i<=$_number_singular_ops;$i++){
		$_lp_rand = rand($_offer_ids_start,$_offer_ids_stop);
		$_arr_random_offer_ids[$_lp_rand] = $_lp_rand;
	}





	echo "<div id='width_limiter' style='width:800px; line-height:100%;' >";





	# Now do the ops - in batches - resulting in xx server roundtrips
	# ==========================================================================
	# links to jump labels
	echo "Jump to ";
	for($j=1; $j<=$_glob_repetitions;$j++){
		echo "<a href='#a{$j}'>Outer Loop Nr.  $j</a> &nbsp; ";
		if( ($j % 4) == 0 ) echo "<br>";
	}
	echo "<br>";


	for($j=1; $j<=$_glob_repetitions;$j++){
		echo "<a name='a{$j}'></a>";
		echo "<H1>Outer Loop Nr. $j</H1>";

			

		for( $i=1 ; $i <=  $_number_network_round_trips ; $i++ ){

			echo render_inline_block("b#{$i} ",false,"font-size:8px;line-height:7px;");
			$_arr_slice = array_slice( $_arr_random_offer_ids, $_batch_size * ($i-1) ,   $_batch_size );

			if( $_do_update == 'UPDATE' ){

				if( $_do_transaction ){
					execute_query( "BEGIN" );
				}


				# impossible to concat updates:
				$_update_statement = "";		
				foreach( $_arr_slice as $_key_unused =>  $_lp_offer_id ){
					$_update_statement .= "
						UPDATE crawler.offerMapping 
						SET firstSeen = from_unixtime( unix_timestamp( firstSeen+1 )) 
						WHERE offerId = $_lp_offer_id  ;
					";
				}


				# instead:
				$_tbl_temp_insert_buffer = uniqid('tbl_temp_update_buffer_');
				$_q_temp_table_drop = "DROP TABLE IF EXISTS   $_tbl_temp_insert_buffer ";
				$_q_temp_table_create = "
					CREATE TEMPORARY
					TABLE $_tbl_temp_insert_buffer ( 
							offerId int(13) unsigned NOT NULL
							, firstSeen timestamp
							, PRIMARY KEY (offerId)
					) 
					ENGINE = MEMORY
				";
				execute_query($_q_temp_table_drop);
				execute_query($_q_temp_table_create);


				$_q_insert_temp_vals = '';
				$_some_const_time = '2013-01-23 10:54:13';
				foreach( $_arr_slice as $_key_unused =>  $_lp_offer_id ){
					#	WHERE offerId = $_lp_offer_id  ;
					$_q_insert_temp_vals .= ($_q_insert_temp_vals ? ', ' : '')." ( $_lp_offer_id, '$_some_const_time' ) ";
				}


				#$_arr_some_ids = array(1423016612 => '2013-01-23 10:54:13',1423016613 =>  '2013-01-23 10:54:13',223520 => '2013-01-23 10:54:17');
				#foreach( $_arr_some_ids as $_lp_offer_id => $_lp_timestamp ){
				#	$_q_insert_temp_vals .= ($_q_insert_temp_vals ? ', ' : '')." ( $_lp_offer_id, '$_lp_timestamp' ) ";
				#}


				if( $_q_insert_temp_vals ){
					execute_query( 
						"INSERT INTO $_tbl_temp_insert_buffer (offerId, firstSeen) VALUES $_q_insert_temp_vals"
					);

					$_q_merge_updates = "
						UPDATE offerMapping, $_tbl_temp_insert_buffer 
						SET 
							/* offerMapping.firstSeen = $_tbl_temp_insert_buffer.firstSeen */
							/* offerMapping.firstSeen = from_unixtime( unix_timestamp( offerMapping.firstSeen+1 ))  */
							offerMapping.firstSeen = TIMESTAMPADD(SECOND,1,offerMapping.firstSeen)
						WHERE $_tbl_temp_insert_buffer.offerId =  offerMapping.offerId
					" ;


					$_q_merge_updates_back = str_replace("firstSeen+1","firstSeen-1",$_q_merge_updates);
					#vd($_q_merge_updates);

					#$_REQUEST['dbg_show_mysql_warnings'] = 1;
					execute_query($_q_merge_updates);
					execute_query($_q_merge_updates_back);
					#unset( $_REQUEST['dbg_show_mysql_warnings'] );

				}

				#$_arr_res = execute_query(" SELECT * FROM $_tbl_temp_insert_buffer ");
				#echo "table name $_tbl_temp_insert_buffer <br> ";
				#echo array_to_table(  array_slice($_arr_res,1,4) );
				#echo "...<br>";
				#echo "...<br>";

				execute_query($_q_temp_table_drop);

				if( $_do_transaction ){
					execute_query( "COMMIT" );
				}




			} else {
				# do_update == 'SELECT'
				$_str_random_offer_ids = implode($_arr_slice, ", ");
				$_query_workload_batched = " 
					SELECT 	$_sql_no_cache
								offerId, bokey, categoryBokey, shopId, isDirty, firstSeen, offerPackageId, '{$_some_different_string}' 
					FROM 		crawler.offerMapping  
					USE INDEX (PRIMARY)
					WHERE 	offerId IN ( {$_str_random_offer_ids} )
					;
				";
				$_arr_records = execute_query( $_query_workload_batched );

			}

		}


		


			
		render_elapsed_time_since("Outer Loop $j",true);


	}




	

		

	echo "</div><!-- width_limiter -->";


	echo render_layout_footer();






?>