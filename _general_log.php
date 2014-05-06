<?php
# Current Queries - and logging of ALL mysql commands for some seconds into mysql.general_log. Previous logs are discarded.


	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");

	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	function error_handler_cleanup( $code, $message, $file, $line, $context ){
		execute_query("SET GLOBAL general_log=0");
		echo __FUNCTION__ . " resetting logging due to error <br>\n";
		return false; # => go back to the default error handler
	}
	set_error_handler( 'error_handler_cleanup' , E_ERROR);		# not E_WARNING, not E_NOTICE, not E_ALL, unnessary on E_PARSE

	echo render_layout_header();

	if( ! $_HOST ) {
		echo "please select host name<br>";
		echo render_layout_footer();
		exit;
	}

	render_elapsed_time_since("start");


	echo "<h1>Most used tables</h1>";
	$_open_tables = "
		SHOW OPEN TABLES WHERE `Table` LIKE '%%' AND In_use > 0;
	";
	echo array_to_table( execute_query($_open_tables) );



	echo "<h1>Queries Aktuell  <span style='font-size:9px;font-weight:normal;'>non-sleeping</span></h1>";

	$_filter_command = get_param("filter_command","");
	$_where_info_contains = "";
	if( $_filter_command ){
		$_where_info_contains = " AND  INFO LIKE '%{$_filter_command}%'   ";
	}

	$_filter_user = get_param("filter_user","");
	$_where_user_contains = "";
	if( $_filter_user ){
		$_where_user_contains = " AND  USER LIKE '%{$_filter_user}%'   ";
	}



	/*
		A better show full processlist 
		taking it directly form info schema - with filtering and ordering...
		SELECT * FROM INFORMATION_SCHEMA.PROCESSLIST where command <> 'Sleep' limit 20;
	*/
	$_sql_current_queries = "/* show processlist */
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
		, CONCAT_WS( '', '<span title=\"',replace(replace(@cmd1, '\"','' ),\"'\",''),'\">', left(  @cmd1,225), '...', '</span>')        'SELECT - INS - UPD ... mouse-over'
		, substr(@cmd1,@pos1+4,20) 'TABLE...'
		, substr(@cmd1,@pos2+5,20) 'WHERE...'

		FROM INFORMATION_SCHEMA.PROCESSLIST 
		where 1=1
				AND COMMAND <> 'Sleep' 
				AND ( LEFT(INFO,22) <>  '/* show processlist */'   OR  (INFO is NULL) )
				$_where_info_contains 
				$_where_user_contains 
			/* AND id IN (136004,141993) */
			

		ORDER BY (time>4) desc, user, time desc
		LIMIT 250;

	";

	#vd( $_sql_current_queries );

	$_arr_current_queries = execute_query($_sql_current_queries);

	$_all_kills = "";
	foreach($_arr_current_queries as $_xx => $_lp_arr){
		$_all_kills .= $_lp_arr['ID'] . " ";
	}
	#echo "<span style='font-size:8px;'>$_all_kills</span>";
	echo "<textarea style='font-size:8px; display:block; font-family: inherit; width: 50%; height:16px; min-height: 1px;' rows='4'  onclick='this.select()' >$_all_kills</textarea>";

	echo array_to_table( $_arr_current_queries );

	$_hid = getHiddenInputs();
	$_c1w = 120;
	$_c2w = 220;



	echo "<form>";
	echo $_hid;
	echo render_inline_block("Filter Command"  , $_c1w);
	echo render_inline_block("<input  type='text' name='filter_command'  value='$_filter_command' style='width:{$_c2w}px;' />");
	echo render_br();


	echo render_inline_block("Filter User"  , $_c1w);
	echo render_inline_block("<input  type='text' name='filter_user'  value='$_filter_user' style='width:{$_c2w}px;' />");
	echo render_br();

	echo render_inline_block(" "  , $_c1w);
	echo render_inline_block("<input type='submit' name='submit_01' value='submit'  accesskey='s' style='width:{$_c2w}px; height:38px; margin-top:10px;' />");
	echo "</form><br>";



	echo "<br><h1>General Query Log laufen lassen</h1>";
	$p1 = get_param("filter_by_user" ,"<username>");
	$p2 = get_param("filter_query_by","<tablename>");
	$_example = get_script_name()."?hostname=changelog-db&filter_by_user={$p1}&filter_query_by={$p2}&duration=2";
	#echo "<p>Syntax: <a href='$_example'>$_example</a></p><br>";

	$p1 = get_param("filter_by_user","");
	$p2 = get_param("filter_query_by","");

	echo "<form>";
	echo $_hid;
	echo render_inline_block("Filter by user"  , $_c1w);
	echo render_inline_block("<input  type='text' name='filter_by_user'  value='$p1' style='width:{$_c2w}px;' />");
	echo render_inline_block("" , "100%","min-height:1px;height:1px");
	echo render_inline_block("Filter queries with"  , $_c1w);
	echo render_inline_block("<input  type='text' name='filter_query_by' value='$p2' style='width:{$_c2w}px;' />");

	echo render_inline_block("" , "100%","min-height:1px;height:1px");

	echo render_inline_block(" "  , $_c1w);
	echo render_inline_block("<input type='submit' name='submit_02' value='submit'  accesskey='' style='width:{$_c2w}px;  height:38px;' />");

	echo "</form><br>";

	flush_http();


	execute_query(" truncate table mysql.general_log ");
	echo "previous general query log discarded ... <br><br>\n";

	if( get_param("submit_02") ){


		$_sql1_a = " SET GLOBAL log_output='TABLE';      /* mysql.general_log;  mysql.slow_log; */ ";
		$_sql1_b = " SET GLOBAL log_output='FILE';       /* bspw. 'FILE' | 'TABLE' | 'NONE' */ ";

		# if log_output: file
		$_sql2 = " SET GLOBAL general_log_file='/var/log/mysql/mysql-general-idealo-01.log'; ";

		$_sql3_a = " SET GLOBAL general_log=1;  /* general log LIVE einschalten */             ";
		$_sql3_b = " SET GLOBAL general_log=0;  /* general log LIVE ausschalten */             ";



		$_user_clause = "";
		if( $_filter_by_user = get_param("filter_by_user") ){
			$_user_clause = "  AND 	user_host like '{$_filter_by_user}%'  ";
		}

		$_query_clause = "";
		if( $_filter_query_by = get_param("filter_query_by") ){
			$_query_clause = "  AND 	argument like '{$_filter_query_by}%'  ";
		}


		$_len_limit = 3300;

		$_sql3 = " 	
			SELECT event_time,user_host,command_type cmd
				, CASE WHEN length(argument) > $_len_limit THEN concat_ws( '', left(argument, $_len_limit ), '...' )  
					/* WHEN xxx THEN yyy */
					ELSE argument
				END  col_query


			FROM mysql.general_log 
			WHERE 		1=1
							$_user_clause
							$_query_clause
			/* LIMIT 1000; */
		";

		$_sql4  = " SELECT @@log_format; ";


		execute_query($_sql1_a);


		execute_query($_sql3_a);

		$_duration = get_param("duration",2);
		if( $_duration > 40 ){
			$_duration = 40;
			echo "maximum 40 secs logging allowed<br>\n";
		}

		echo "starting to log into mysql.general_log for $_duration secs ... \n";
		flush_http();

		sleep( get_param("duration",2) );


		execute_query($_sql3_b);
		echo " logging beendet. <br>\n";

			

		$_arr_log = execute_query($_sql3);


		# reduce max WORD size in query to 40 chars
		foreach( $_arr_log as $_key1 => $_arr_lp ){
			$s = $_arr_lp['col_query'];
			$s = replace_all_whitespace_with($s,' ');
			$s1 = explode(' ',$s);
			#vd($s1);
			foreach($s1 as $_key2 => $s2){
				if( strlen($s2) > 40 ){
					$sfx = substr($s2,-1);
					$s1[$_key2] = substr($s2,0,40);
					if( $sfx == '"' OR $sfx == "'" ){
						$s1[$_key2] .= '... ' ;
						#vd("appended $sfx to " . $s1[$_key2]);
					} else {
						$s1[$_key2] .=  '... ';
					}
				}
			}
			$_arr_log[$_key1]['col_query'] = implode(' ',$s1);

		}

		$_vol = round(  sizeof($_arr_log)/ $_duration,2);
		echo "$_vol queries per sec<br>\n";

		echo array_to_table($_arr_log);


		execute_query($_sql1_b);
		execute_query($_sql2);


		flush_http();

	}


	echo render_layout_footer();




?>
