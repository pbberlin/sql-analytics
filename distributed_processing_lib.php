<?php

#  todo check for column names - and read their exact definitions
#  make DESCENDING/MIN/<   and ASCENDING/MAX/>  variable
#  make table "current_batch" available outside of the session (for java)

/*


Synchronisation mehrerer Prozesse auf mehreren Maschinen für die Abarbeitung von großen MySQL Tabellen ($_base_table, offerFingerprint, amazonItemDe )
kann idealtypisch verschieden gestaltet werden.

- über datenbank-seitige Locks
- über datenbank-seitige Steuerspalten
- über datenbank-seitige Temporäre Tabellen
- über eine extra Queue - In-Memory Key-Value Store - oder über einen MQ Server

Ziele für die Ausgestaltung wären
- Robustheit
- Skalierbarkeit
- 


A.)	Unabhängig von den u.g. Verfahren können die Tabellen intern 
nach dem häufigsten Selektionskriterium natürlich sortiert werden.

Bspw. PRIMARY KEY 'shopId,category,id' , UNIQUE INDEX id

U.a. Java Amazon CSV importer, Java shopQueue bearbeiten nach shopId und category.



1.) Die Synchronisation über Transaktionen funktioniert nicht:

Prozeß1
	BEGIN;
	SELECT * FROM $_base_table WHERE offerID = 1393184231;  
	SELECT * FROM $_base_table WHERE offerID = 1393184231 FOR UPDATE;  
	SELECT * FROM $_base_table WHERE offerID = 1393184231 LOCK IN SHARE MODE;

Prozeß2
	BEGIN;
	one of the above statements
		either completely goes through - or completely blocks



2) Synchronisation über einen Zwischenspeicher mit zentraler Befüllung

	Ein einziger "producer"-Prozeß selektiert alle potentiellen Primary Keys und schreibt sie 
			in ein RAM basiertes KeyValue-Store (denkbar wäre auch eine MessageQueue)

	Die Abfrage erfolgt nach ShopID or nach nach der Spalte "CSVfile" name - bspw. Kategorie "apparel1"




3a.) Die Synchronisation über "flags" in den Tabellen-Zeilen wird hier gut beschrieben:
	http://stackoverflow.com/questions/562780/best-practices-for-multithreaded-processing-of-database-records
	
	Die Frage ist: Bestehen hier negative Erfahrungen?




3b.) Synchronisation über 
		



	batch_size: O(n)
	Anzahl Prozesse: O(n)

	Queue Table with strictly monotonic key: O(n)
	Queue Table with just monotonic key: > O(n)


*/

	function error_handler_cleanup( $code, $message, $file, $line, $context ){
		execute_query("SET GLOBAL general_log=0");
		echo __FUNCTION__ . " resetting logging due to error <br>\n";
		return false; # => go back to the default error handler
	}
	#set_error_handler( 'error_handler_cleanup' , E_ERROR);		# not E_WARNING, not E_NOTICE, not E_ALL, unnessary on E_PARSE


	function init_queue( $_arr_options = array() ){


		static $_ARR_INIT;
		if( isset($_ARR_INIT)  AND  sizeof($_ARR_INIT) ) return $_ARR_INIT;

		require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


		$_arr_defaults = array(
			 "schema"      =>  "crawler"
			,"base_table"  =>  "offerMapping"
			,"prim_key"    =>  "offerId"
			,"cursor_key"  =>  "firstSeen"
			,"strictly_monotonic"  =>  false


			# test - largest working set
			,"cursor_key_low"   =>  "2008-06-01"
			,"cursor_key_high"  =>  "2013-03-16"

			# test - tiny working set
#			,"cursor_key_low"   =>  "2011-01-01"
#			,"cursor_key_high"  =>  "2011-01-30"


			,"max_number_processes" => 8

			,"current_batch_into_temp_table" => true

		);
		extract( expand_options_array($_arr_defaults) );

		$_arr_intersect = array_intersect_key( $_arr_defaults, $_arr_options);
		if(  sizeof($_arr_intersect)  ==  sizeof($_arr_defaults) ){
		} else {
			$_arr_options = $_arr_defaults;
			cm("using defaults");
		}

		
		extract( expand_options_array($_arr_options) );


		cm("<h1>Verteiltes Bearbeiten einer großen Tabelle nach indizierter Spalte</h1>");


		$_queue_table =  "{$_base_table}_by_{$_cursor_key}_queue";
		$_registry_table =  "{$_base_table}_by_{$_cursor_key}_registry";
		$_global_table =  "global_table";
		$_table_current_batch = "temp_table_current_batch";

		$_batch_size = get_param("batch_size",5000);
		$_MAX_BATCH_SIZE  = 250000;
		if( $_batch_size > $_MAX_BATCH_SIZE ){
			$_batch_size = $_MAX_BATCH_SIZE;
			cm("Max batch_size is {$_MAX_BATCH_SIZE}.");
		} 

		$_MIN_BATCH_SIZE  =  1000;
		if( $_batch_size < $_MIN_BATCH_SIZE ){
			$_batch_size = $_MIN_BATCH_SIZE;
			cm("Min batch_size is {$_MIN_BATCH_SIZE}.");
		} 

		$_MAX_QUEUE_SIZE = (100 * $_MAX_BATCH_SIZE);
		$_MAX_QUEUE_SIZE = MIN(500000,$_MAX_QUEUE_SIZE );


		# how many batched - per thread
		$_MAX_COUNT_BATCHES = 50;
		$_count_batches = get_param("count_batches", 12 );
		if( $_count_batches > $_MAX_COUNT_BATCHES ){
			$_count_batches = $_MAX_COUNT_BATCHES;
			cm("$_count_batches reduced to maximum $_MAX_COUNT_BATCHES.");
		}


		$_limit_diplay_1   = 4;
		$_limit_diplay_2 = $_limit_diplay_1-2;


		cm("batch_size = $_batch_size");

		execute_query("SELECT NOW()");			# use database before accessing $_MYSQL_THREAD_ID
		$_mypid = $_MYSQL_THREAD_ID;
		if( $_mypid < 1 ) $_mypid = 2;
		#vd("mypid = $_mypid ");



		#$_time_start = time();
		#$_time_start = microtime(true);
		#$_arr_options["time_start"] = $_time_start;




		# check preconditions
		$_sql_pre_01 = "
				SELECT table_name 
				FROM `information_schema`.`tables`   
				WHERE 1=1
					AND table_name   = '{$_base_table}'
					AND table_schema = '{$_schema}'
					AND table_type = 'BASE TABLE'
		";
		$_arr_check = execute_query($_sql_pre_01);
		if( sizeof($_arr_check) <> 1 ){
			cm("{$_schema}.{$_base_table} must exist");
			return -1;
		}


		execute_query("use $_schema");


		# this would CLEAN UP the entire queue
		# only nessessary if too large - or after database schema changes
		# opening up all included records for renewed processing
		if( get_param("reset_table_structures") ) execute_query(" DROP TABLE IF EXISTS $_queue_table ");



		# ensure, that queue table exists
		$_sql_02 = "
			CREATE TABLE IF NOT EXISTS 
			$_queue_table ( 
					{$_prim_key}   int(13) unsigned NOT NULL
				,  {$_cursor_key} TIMESTAMP                                 NULL 
				,  inserted       TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'for cleanup'

				, PRIMARY KEY ({$_prim_key})
				, INDEX USING BTREE ({$_cursor_key})
			) 
				ENGINE = MEMORY, COMMENT = 'poor man-s message queue'
		";
		#		memory was BEST - tested from THESE
		#		ENGINE = MEMORY
		#		ENGINE = INNODB
		#		ENGINE = MYISAM





		# ensure, that a 'process registry' table exists
		if( get_param("reset_table_structures") ) execute_query( "DROP TABLE IF EXISTS $_registry_table" );
		$_sql_04 = "
			CREATE TABLE IF NOT EXISTS 
			$_registry_table ( 
					process_id     		VARCHAR(24) NOT NULL COMMENT 'we use mysql_thread_id'
				,  time_start 	         VARCHAR(24) NOT NULL COMMENT 'millisecs'
				,  last_update  	      TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'last time the process reported'
				,  size_queue   	      INT
				,  size_cursor   	      INT
				,  duration_cursor   	FLOAT( 8,4) NULL COMMENT 'how long took the cursor retrieval operation'
				,  billion_per_day      FLOAT(12,1) NULL COMMENT 'how many billions of records per day'
				,  duration_processing  VARCHAR(15) DEFAULT '' COMMENT 'how long took the processing of batch'
				,  msg                  VARCHAR(255) DEFAULT '' COMMENT 'last val - or error'
				, PRIMARY KEY (process_id,time_start)
			) 
				ENGINE = MEMORY, COMMENT = 'poor man-s zookeeper'
		";




		# ensure, that a 'global last id/value' table exists
		if( get_param("reset_table_structures") ) execute_query( "DROP TABLE IF EXISTS $_global_table" );
		$_sql_05 = "
			CREATE TABLE IF NOT EXISTS 
			$_global_table ( 
					process_id 		VARCHAR(24) NOT NULL COMMENT 'we use mysql_thread_id'
				,  last_update 	TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'last time the process reported'
				,	col_key 			VARCHAR(124) NOT NULL COMMENT 'bspw. offerMapping.last'
				,  col_val 			VARCHAR(124) NOT NULL COMMENT 'bspw. 111'
				, PRIMARY KEY (col_key)
			) 
				ENGINE = MEMORY, COMMENT = 'mysql global variables'
		";



		execute_query($_sql_02);
		execute_query($_sql_04);
		execute_query($_sql_05);

		#execute_query("COMMIT");



		$_time_ms = floor(microtime(true));
		if( $_time_ms % 10  ){
			cm("doing health checks...");
			$_arr_queue_size = execute_query_get_first("select count(*) anz from $_queue_table");
			$_queue_size = $_arr_queue_size['anz'];
			cm("$_queue_size items in queue (max $_MAX_QUEUE_SIZE)");
			if( $_queue_size > $_MAX_QUEUE_SIZE  ){

				# delete OLD records from crashed processing threads
				execute_query( "DELETE FROM $_queue_table WHERE inserted < TIMESTAMPADD(HOUR,-4,NOW()) ");

				# now check again
				$_arr_queue_size = execute_query_get_first("select count(*) anz from $_queue_table");
				$_queue_size = $_arr_queue_size['anz'];
				if( $_queue_size > $_MAX_QUEUE_SIZE  ){
					cm("too many items in queue ($_queue_size). Max $_MAX_QUEUE_SIZE. Use reset_table_structures=1 to reset queue.");
					return -1;
				}

			}

			execute_query(" DELETE FROM  $_registry_table WHERE last_update < TIMESTAMPADD(MINUTE,-40,NOW()) ") ;

		}

		#echo cm("reset");
		$_arr_options["queue_table"] = $_queue_table;
		$_arr_options["registry_table"] = $_registry_table;
		$_arr_options["global_table"] = $_global_table;
		$_arr_options["table_current_batch"] = $_table_current_batch;
		$_arr_options["batch_size"] = $_batch_size;
		$_arr_options["queue_size"] = $_queue_size;
		$_arr_options["count_batches"] = $_count_batches;
		$_arr_options["limit_diplay_1"] = $_limit_diplay_1;
		$_arr_options["limit_diplay_2"] = $_limit_diplay_2;
		$_arr_options["mypid"] = $_mypid;




		$_ARR_INIT = $_arr_options;
		return $_arr_options;


	}



	function populate_chunk($_time_start){

		require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


		$_arr_options = init_queue();
		if( ! isset($_arr_options) OR  ! sizeof($_arr_options) ){
			echo "not initialized";
			echo render_layout_footer();
			exit();
		}
		extract( expand_options_array($_arr_options) );


		$_sql_reg = "
			INSERT INTO $_registry_table 
							(process_id,time_start,size_queue,size_cursor) 
			VALUES 		($_mypid,$_time_start,$_queue_size+$_batch_size,$_batch_size)
		";
		#vd($_sql_reg);
		execute_query($_sql_reg);



		$_transaction_start =  render_elapsed_time_since("transaction start");
		$_sql_21 = " BEGIN ";
		execute_query($_sql_21);
		cm("<h2>Transaction started ... </h2>");
		cm("<div style='margin-left:44px;'><!--trans_start-->");


		# We want to retrieve the next batch of records.
		# These are the records we intend to process.
		# And these are the records we want to insert into $_queue_table 
		# And these we want to block other processes from processing


		# this does not work for us - as we are then required to lock ALL tables inside transaction
		# but we only want to lock the first batch_size records of base_table
		# not entire base table
		#execute_query(" LOCK TABLES $_queue_table  WRITE, $_base_table AS t1 READ");  # READ or WRITE - we lock immediately after begin transation - to prevent deadlocks


		# this would lock the ENTIRE table, because of FOR UPDATE
		$_sql_22 = "
				SELECT   {$_prim_key}, $_cursor_key 
				FROM     $_base_table
				ORDER BY $_cursor_key DESC
				LIMIT    $_batch_size;
				FOR UPDATE;
		";


		# Instead:


		$_sql_version_A = "";
		$_sql_version_B = "";


		$_arr_last_limit = execute_query_get_first("
			SELECT left(col_val,10) col_val
			FROM $_global_table 
			WHERE col_key = '{$_queue_table}.min'
		");
		

		$_where_clause_3 = "";
		if( $_arr_last_limit["col_val"] ) {

			# restart if we reached the low end
			#vd("$_arr_last_limit[col_val] <= $_cursor_key_low");
			if( $_arr_last_limit["col_val"] <= $_cursor_key_low ){
				$_arr_last_limit["col_val"] = $_cursor_key_high;
			}

			$_where_clause_3 = " AND 	t1.{$_cursor_key} <= '{$_arr_last_limit["col_val"]}'  ";

		}

		$_where_clause_4 = " AND 	t1.{$_cursor_key} <= '{$_cursor_key_high }' ";
		$_where_clause_5 = " AND 	t1.{$_cursor_key} >= '{$_cursor_key_low }'  ";

		$_index_hint = " FORCE INDEX (firstSeen) ";
		#$_index_hint = "";




		if(  $_strictly_monotonic  ){

			# if our $_cursor_key would be STRICTLY monontonic rising
			# then we would to THIS instead:
			$_sql_version_B_prepare = "
					SELECT   {$_cursor_key}
					FROM     $_queue_table 
					ORDER BY {$_cursor_key} ASC
					LIMIT    1
			";
			$_arr_max = execute_query_get_first($_sql_version_B_prepare);
			$_max_first_seen = $_arr_max['firstSeen'];
			if( $_max_first_seen ) $_where_clause_4 = " AND	t1.{$_cursor_key} < '{$_max_first_seen}'  ";

			

			$_sql_version_B = "    
					SELECT   t1.{$_prim_key}, t1.{$_cursor_key}
					FROM     $_base_table t1 $_index_hint
					WHERE    		1=1
								$_where_clause_3
								$_where_clause_4 
								$_where_clause_5
					ORDER BY t1.{$_cursor_key} DESC
					LIMIT    $_batch_size
			";

		} else {

			# This is ($_base_table \ $_queue_table).limit(batch_size)
			# This is the time and lock critical statement.
			# The RDBMS might fully lock $_base_table_queue - preventing 
			# other processes from getting another cursor batch
			# Since $_queue_table is always small, this is no big deal.
			# The RDBMS will also lock $_base_table - partly or fully.
			# There will be a BLOCK on ALL other processes to access $_base_table
			# entirely or partially.
			# Either way, this should be quickly over, as long as cursor_key is INDEXED
			# only disadvantage could be the non-indexing of the where condition
			$_sql_version_A = "
					SELECT   t1.{$_prim_key}, t1.{$_cursor_key}
					FROM     $_base_table t1 $_index_hint LEFT JOIN  $_queue_table t2 USING ({$_prim_key})
					WHERE    		1=1
								AND  	t2.{$_cursor_key} IS NULL
								$_where_clause_3
								$_where_clause_4 
								$_where_clause_5

					ORDER BY t1.{$_cursor_key} DESC
					LIMIT    $_batch_size
			";
			#cm($_sql_version_A);
			#$_arr_23 = execute_query($_sql_version_A);
			#echo array_to_table($_arr_23, array("heading"=>"Next $_batch_size unlocked records, mit O(n)"));


		}
			




		# We intend to insert ($_base_table  $_queue_table).limit(batch_size) into $_queue_table 
		# We can not read AND insert into $_queue_table with the SAME statement (because of locks),
		# We put the ($_base_table  $_queue_table).limit(batch_size) into a temp table called $_table_current_batch
		$_sql_24 = " DROP TABLE IF EXISTS $_table_current_batch ";
		execute_query($_sql_24);

		$_sql_24a = "SET SESSION binlog_format = 'ROW'";		# prevents temp table from being replicated
		execute_query($_sql_24a);

		# the keyword "TEMPORARY" keeps the table local to the mysql session
		$_clause_primary = "";
		#$_clause_primary = " , PRIMARY KEY ({$_prim_key})  ";		# slashes performance up to halve
		$_sql_25 = "
			CREATE TEMPORARY TABLE IF NOT EXISTS $_table_current_batch ( 
				  {$_prim_key}   INT(13) UNSIGNED NOT NULL
				, {$_cursor_key} TIMESTAMP NULL
				$_clause_primary
			) 
			ENGINE = MEMORY
		";
		#		ENGINE = MEMORY
		#		ENGINE = INNODB
		#		ENGINE = MYISAM

		# memory oder myisam without primary key
		#   are 2 times faster than innodb
		#   IF a primary key is needed - then it is best combined with MEMORY engine - 


		$_sql_26 = "
			INSERT INTO $_table_current_batch ({$_prim_key},{$_cursor_key})
			(
				$_sql_version_A $_sql_version_B
			)
		";


			
		execute_query($_sql_25);
		#echo array_to_table(execute_query($_sql_27), array("heading"=>"$_table_current_batch before") );

		if( $_current_batch_into_temp_table ){
			execute_query($_sql_26);
		}


		$_sql_27 = "SELECT * FROM $_table_current_batch LIMIT $_limit_diplay_1 ";
		#$_arr_cur_batch = execute_query($_sql_27);
		#cm( array_to_table( $_arr_cur_batch, array("heading"=> "$_table_current_batch after", "cutoff" => $_limit_diplay_2) ) );



		# we do not need the queue table under strictly monotonic cursor keys
		if( ! $_strictly_monotonic ){

			# Finally we put the actual batch into $_queue_table
			# This is another operation, that causes locks.
			# But this time it's only on the small $_queue_table
			$_sql_28 = "
				INSERT INTO $_queue_table ({$_prim_key},{$_cursor_key})
				(
					SELECT   {$_prim_key}, {$_cursor_key}
					FROM		$_table_current_batch
				)
			";


			execute_query($_sql_28);
			cm(  array_to_table(execute_query("select * from $_queue_table ORDER BY inserted DESC LIMIT {$_limit_diplay_1} "), array("heading"=> "$_queue_table after" , "cutoff" => $_limit_diplay_2) ) );

		}
			

		# We restrict the domain of cursor key to the configured limits
		# in case $_table_current_batch is empty
		# SELECT GREATEST(0,2) => 2
		# SELECT LEAST(  0,2)  => 0



		if( $_current_batch_into_temp_table ){
			$_sql_27b = "
				SELECT MIN({$_prim_key})	min_pk
						,GREATEST(    MIN({$_cursor_key}), '{$_cursor_key_low}'  ) min_cc
						,MAX({$_prim_key})   max_pk
						,LEAST(       MAX({$_cursor_key}), '{$_cursor_key_high}' ) max_cc
				FROM $_table_current_batch 
			";
			$_arr_min_max_1 = execute_query_get_first($_sql_27b);


		} else {
			$_sql_27a = "
				SELECT MIN({$_prim_key})	min_pk
						,GREATEST(    MIN({$_cursor_key}), '{$_cursor_key_low}'  ) min_cc
						,MAX({$_prim_key})   max_pk
						,LEAST(       MAX({$_cursor_key}), '{$_cursor_key_high}' ) max_cc
				FROM (
					$_sql_version_A $_sql_version_B
				) tt
			";
			$_arr_min_max_1 = execute_query_get_first($_sql_27a);

		}




		if( $_arr_min_max_1["min_cc"] ){
			execute_query( "
				INSERT INTO $_global_table (process_id,col_key,col_val ) VALUES ($_mypid,'{$_queue_table}.min','$_arr_min_max_1[min_cc]')
				ON DUPLICATE KEY 
					UPDATE col_val= CONCAT_WS('','$_arr_min_max_1[min_cc]', '' )
			");

		}

		if( $_arr_min_max_1["max_cc"] ){
			execute_query( "
				INSERT INTO $_global_table (process_id,col_key,col_val ) VALUES ($_mypid,'{$_queue_table}.max','$_arr_min_max_1[max_cc]')
				ON DUPLICATE KEY 
					UPDATE col_val= CONCAT_WS('','$_arr_min_max_1[max_cc]', '' )
			");
		} else {
			vd("");
		}


		# releasing all locks now
		#execute_query(" UNLOCK TABLES ");
		execute_query(" COMMIT ");

		
		execute_query("SELECT SLEEP(0.01)");	# another sleep below


		cm( "</div><!--trans_end--> ");
		cm( "<h2>... Transaction ended </h2>" );
		$_transaction_end = render_elapsed_time_since("transaction end");
		$_transaction_time = $_transaction_end - $_transaction_start;
		$_transaction_time = round($_transaction_time,5);
		cm("Trans took $_transaction_time s");


		$_msg  =               $_arr_min_max_1["min_cc"] . '--' . $_arr_min_max_1["min_pk"];
		$_msg .= " to<br>\n" . $_arr_min_max_1["max_cc"] . '--' . $_arr_min_max_1["max_pk"];
		$_sql_reg = "
			INSERT INTO $_registry_table 
							(process_id,time_start
								,size_queue,duration_cursor,size_cursor,billion_per_day,duration_processing,msg) 
			VALUES 		($_mypid,$_time_start
								,$_queue_size+$_batch_size,round($_transaction_time,4),$_batch_size, round( ($_batch_size/$_transaction_time) *3600 * 24  / (1000*1000*1000),1) , 'cursor finished', '{$_msg}')
			ON DUPLICATE KEY 
				UPDATE 
					 duration_cursor=$_transaction_time
					,size_queue=$_queue_size+$_batch_size
					,duration_cursor=round($_transaction_time,4)
					,size_cursor=$_batch_size
					,billion_per_day=round( ($_batch_size/$_transaction_time) *3600 * 24  / (1000*1000*1000),1)
					,duration_processing='cursor finished'
					,msg = '{$_msg}'
		";
		#vd($_sql_reg);
		execute_query($_sql_reg);

		#echo cm("reset");


	}




	function process_one_batch($_batch_id){

		require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require

		$_arr_options = init_queue();
		if( ! isset($_arr_options) OR  ! sizeof($_arr_options) ) {
			echo "not initialized";
			echo render_layout_footer();
			exit();
		}
		extract( expand_options_array($_arr_options) );

		$_time_start = microtime(true);
		populate_chunk($_time_start);



		$_add_seconds = 1;
		$_processing_start = render_elapsed_time_since("processing start");



		cm("<br><h1>Simulating 'processing' of a batch of data ...</h1>");
		$_sign = " ";
		$_sql_upate_inc = "
			UPDATE $_table_current_batch INNER JOIN $_base_table USING ({$_prim_key})
			SET 	$_base_table.{$_cursor_key} = TIMESTAMPADD(SECOND, {$_sign}{$_add_seconds},$_base_table.{$_cursor_key});
		";
		$_sign = "-";
		$_sql_upate_dec = "
			UPDATE $_table_current_batch INNER JOIN $_base_table USING ({$_prim_key})
			SET 	$_base_table.{$_cursor_key} = TIMESTAMPADD(SECOND, {$_sign}{$_add_seconds},$_base_table.{$_cursor_key});
		";

		$_updates_in_transaction = get_param("updates_in_transaction",false);
		if($_updates_in_transaction) execute_query("BEGIN ");
		if( ! get_param("no_update") ) $_arr_upd_inc = execute_query($_sql_upate_inc);
		if( ! get_param("no_update") ) $_arr_upd_dec = execute_query($_sql_upate_dec);
		if($_updates_in_transaction) execute_query("COMMIT");
		
		usleep(500000);
		usleep(500000);
		usleep(500000);



		# remove from queue
		$_sql_block_delete = "
			DELETE $_queue_table
			FROM   $_queue_table  INNER JOIN $_table_current_batch USING ({$_prim_key})
		";
		execute_query( $_sql_block_delete );



		$_processing_end = render_elapsed_time_since("processing end");
		$_processing_time = $_processing_end - $_processing_start;
		$_processing_time = round($_processing_time,4);
		#vd("Processing took $_processing_time s");


		execute_query( "
			INSERT INTO $_registry_table (process_id,time_start,duration_processing, msg ) VALUES ($_mypid,$_time_start,$_processing_time, CONCAT_WS('','finished1 ',msg ) )
				ON DUPLICATE KEY 
					UPDATE 
						 duration_processing=$_processing_time
						,msg = CONCAT_WS('',msg, ' -finished2' )
		");



		cm("<br> ... processing finished in $_processing_time secs<br>");

		#echo cm("reset");

	}


	function get_runtime_info(){

		require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require

		$_arr_options = init_queue();
		if( ! isset($_arr_options) OR  ! sizeof($_arr_options) ) {
			echo "not initialized";
			echo render_layout_footer();
			exit();
		}
		extract( expand_options_array($_arr_options) );

		$_sql_1 = "
			SELECT 
					  round( AVG( duration_processing),2) AVG_duration_processing
					, round( AVG( duration_cursor),2)     AVG_duration_cursor
					, round( AVG( duration_processing/duration_cursor),1)  'Optimal number of parallel processes'
			FROM
					( 	SELECT * 
						FROM $_registry_table 
						ORDER BY last_update DESC 
						LIMIT 0,20  /* last 30 enries */
					) t2

		";
		$_arr_ratios = execute_query($_sql_1);
		#echo array_to_table(  $_arr_ratios , array("heading" => "ratio chunk-retrieval to processing-time", "cutoff" => 40) );
		$_optimal_number_processes = $_arr_ratios[0]['Optimal number of parallel processes'];
		$_optimal_number_processes = floor($_optimal_number_processes);


		if( $_optimal_number_processes < 1 ) $_optimal_number_processes = 1;
		if( $_optimal_number_processes >=  $_max_number_processes ) $_optimal_number_processes = $_max_number_processes;





		$_now  = time();
		$_dozen_minutes_ago = - (20  * 60); 
		$_few_minutes_ago   = - (1.2 * 60); 

		#$_dozen_minutes_ago *= 40;
		#$_few_minutes_ago   *= 50;

		$_sq_inner = "
						SELECT process_id
							,count(*) number_of_chunks
							,MAX( UNIX_TIMESTAMP(last_update ) - $_now) most_recent_update
							,MIN( UNIX_TIMESTAMP(last_update ) - $_now) oldest_update
							,( MIN(   CAST(time_start AS  DECIMAL(16,0))  - $_now) ) time_start
							,MAX( duration_cursor )     duration_cursor
							,MAX( duration_processing ) duration_processing
						FROM  $_registry_table 
						GROUP BY process_id 
						HAVING count(*)  < $_count_batches 
						ORDER BY process_id 
		";
		#vd($_sq_inner);
		#echo array_to_table(  execute_query($_sq_inner), array("cutoff" => 40) );
		$_get_number_active_threads = "
			SELECT *
			FROM
					( 	
						$_sq_inner
					) t2

			WHERE  		1=1
					AND 	time_start   		 > $_dozen_minutes_ago /* started recendly */
					AND 	most_recent_update > $_few_minutes_ago   /* still active */
					OR 		duration_processing = 'cursor finished'
					OR 		duration_processing IS NULL
					OR 		duration_cursor     IS NULL
			ORDER BY time_start
		";


		#vd($_get_number_active_threads);
		$_arr_incomplete_processes = execute_query($_get_number_active_threads);
		$_number_processes_running = sizeof( $_arr_incomplete_processes );
		$_table_running_processes = array_to_table(  $_arr_incomplete_processes, array("heading" => "<br>processes running (with count_batches < max $_count_batches) ", "cutoff" => 40) );

		if( sizeof($_arr_incomplete_processes) ){
			#echo $_table_running_processes;
		}
		$_delta = $_optimal_number_processes - $_number_processes_running;
		#vd("Running1: $_number_processes_running - Recommended: $_optimal_number_processes - Delta: $_delta ");


		$_table_global = array_to_table( execute_query("SELECT * FROM $_global_table"), array("heading" => "<br>last batch min max ")  );


		return array(
			 optimal_number_processes => $_optimal_number_processes
			,number_processes_running => $_number_processes_running
			,table_running_processes  => $_table_running_processes
			,table_global             => $_table_global
		);


	}

?>