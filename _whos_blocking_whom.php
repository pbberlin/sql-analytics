<?php
# checks, if one query locks another query.


	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");

	#$_REQUEST['db'] = 'amazon';

	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();


	echo "<H1>Wer blockiert wen auf $_HOST</H1><br>";

	render_elapsed_time_since("start",true);


	$_str_sql = "

	/* finding those processes/transactions - which jam the server */
	SELECT 
	/*	, GROUP_CONCAT( DISTINCT CONCAT_WS( '+', left(req_prc.HOST,5), req_prc.user  , req_prc.id )) 'req-host-user-pid' */
	  CONCAT_WS( '+', left(req_prc.HOST,5), req_prc.user  , req_prc.id ) 'req-host-user-pid'
	, ( ROUND( NOW() - req_trx.trx_wait_started ,0) ) max_rsecs
	, LEFT( @cmd1 :=  REPLACE(req_prc.info,'\n',''),21) rquery_start
	, req_prc.info  rquery_full
	, SIGN( @pos1 :=  LOCATE('from',@cmd1))  'x1'       
	, SUBSTR(@cmd1,@pos1+4,20) rquery_from
	, REPLACE(req_lck.lock_index ,'`','') 'r-lcked_idx'
	, REPLACE( REPLACE( req_trx.trx_state ,'LOCK WAIT', 'lwait' ),'RUNNING','rnng') rstate
	, CONCAT_WS( '+', round(req_trx.trx_rows_locked/1000) ,round(req_trx.trx_lock_memory_bytes/1000)    ) 'rKRows+kB'
	, CONCAT_WS( '+',req_lck.lock_mode  , left(req_lck.lock_type,3)) 'r md+typ'
	, CONCAT_WS( '+',req_lck.lock_data  , req_lck.lock_rec ) 'r lockdata+rec'

/*	
	, CONCAT(  '</tr><tr>'  )  'x1a'
*/


	, CONCAT_WS( '+', left(blk_prc.HOST,5), blk_prc.user  , blk_prc.id ) 'blocking-host-user-pid'
	, ROUND( NOW() - blk_trx.trx_wait_started ,0) bsecs
	, LEFT( @cmd1 :=  REPLACE(blk_prc.info,'\n',''),21) bquery_start
	, req_prc.info  bquery_full
	, SIGN( @pos1 :=  LOCATE('from',@cmd1))  'x2'       
	, SUBSTR(@cmd1,@pos1+4,20) bquery_from
	, REPLACE(blk_lck.lock_index ,'`','') 'b-lcked_idx'
	, REPLACE( REPLACE( blk_trx.trx_state ,'LOCK WAIT', 'lwait' ),'RUNNING','rnng') bstate
	, CONCAT_WS( '+', round(blk_trx.trx_rows_locked/1000) ,round(blk_trx.trx_lock_memory_bytes/1000)    ) 'bKRows+kB'
	, CONCAT_WS( '+',blk_lck.lock_mode  , left(blk_lck.lock_type,3)) 'b md+typ'
	, CONCAT_WS( '+',blk_lck.lock_data 	, blk_lck.lock_rec ) 'b lockdata+rec'


/*  
	, CONCAT(  '</tr><tr>'  )  'x2a' 
*/

	/*  
	w.blocking_lock_id

	, trx_query,  trx_mysql_thread_id ,  trx_started, trx_wait_started , trx_requested_lock_id , trx_tables_locked , trx_lock_structs , trx_operation_state , trx_tables_in_use , trx_rows_modified 
	, trx_concurrency_tickets ,  trx_unique_checks 

	, blk_lck.lock_table  same_as_query_from
	, blk_lck.lock_page

	*/

	,COUNT(*) anz

/*  
	, CONCAT(  '</tr><tr><td>Anzahl:', COUNT(*),'<br><br></td></tr><tr>'  )  'x2a'
*/


	
	FROM 				information_schema.innodb_lock_waits w 


	LEFT  JOIN 		information_schema.innodb_locks      req_lck    ON w.requested_lock_id  =  req_lck.lock_id  
	LEFT  JOIN 		information_schema.innodb_trx        req_trx    ON w.requesting_trx_id  =  req_trx.trx_id 
	LEFT  JOIN 		information_schema.processlist       req_prc    ON req_trx.trx_mysql_thread_id = req_prc.ID 


	LEFT  JOIN 		information_schema.innodb_locks      blk_lck    ON w.blocking_lock_id = blk_lck.lock_id  
	LEFT  JOIN 		information_schema.innodb_trx        blk_trx    ON w.blocking_trx_id  =  blk_trx.trx_id 
	LEFT  JOIN 		information_schema.processlist       blk_prc    ON blk_trx.trx_mysql_thread_id = blk_prc.ID 

	/* WHERE blk_trx.trx_id IN ( '597322C6' ) 

	*/
	GROUP BY w.blocking_trx_id
	ORDER BY max_rsecs desc, 'blocking-host-user-pid'
	LIMIT 65;


	";

	#http://www.dailymotion.com/video/x5c0yq_louis-de-funes-gendarme-en-exam_fun

	#vd($_str_sql);

	$_arr_b = execute_query( 	$_str_sql  );

	if( sizeof($_arr_b) > 0  or true){

		$_arr_cols_with_popup = array("rquery_start"=>"rquery_full","bquery_start"=>"bquery_full",);
		$_str_table = array_to_table($_arr_b,  array("cols_with_popup" => $_arr_cols_with_popup ) );


		#$_str_table = str_replace("<td  class='class_not_first'  >|\n|</td>","  </tr></tr><td>&nbsp;</td> ",$_str_table);
		#$_str_table = str_replace("<th  class='class_not_first'  >rquery_start</th>","<th  class='class_not_first'  style='width:722px;'>rquery_start</th>",$_str_table);


		echo $_str_table;

		vd($_arr_b);

	} else {

		echo "<br>No locks at this moment";

	}


	
		
	echo render_layout_footer();



?>