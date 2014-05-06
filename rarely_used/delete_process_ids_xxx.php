<?php
	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");

	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


$_s = "
select ID 'xx' from
(
		/*  notttt*/
		SELECT 
		concat_ws( '', 'kill ' ,ID , ';' ) ID
		,USER 
		,HOST 
		,DB 
		,COMMAND
		,TIME 
		,left( REPLACE(STATE,'Waiting for ','wf '),15) state_abbr
		, LEFT( CONCAT_WS( '' 
				, @cmd1 :=  replace(INFO,'\n','')  
				, @pos1 :=  locate('from' ,@cmd1) 
				, @pos2 :=  locate('where',@cmd1) 
		),1) t
		, left(  @cmd1,45)        'SELECT - INSERT - UPDATE ...'
		, substr(@cmd1,@pos1+4,20) 'TABLE...'
		, substr(@cmd1,@pos2+5,20) 'WHERE...'

		FROM INFORMATION_SCHEMA.PROCESSLIST 
		where 1=1
				AND COMMAND <> 'Sleep' 
				AND ( LEFT(INFO,22) <>  '/* show processlist */'   OR  (INFO is NULL) )
				AND USER like '%idealo%'
				AND INFO like '%CREATE TABLE IF NOT EXISTS%'
				AND INFO like '%offerMapping_by_firstSeen_queue%' 
				AND NOT INFO like '%notttt%' 

	
		ORDER BY (time>4) desc, user
		LIMIT 250
) t1  

";

	for( $i = 0 ; $i < 400 ; $i ++ ){

		$_arr1 = execute_query($_s);

		vd($_arr1 , "run $i");
		#vd($_arr1,"ss1");
		#vd($_arr1['xx'],"ss");

		foreach($_arr1 as $_key => $_lp){
			execute_query( $_lp['xx'] );
		};

	}

		
		
?>