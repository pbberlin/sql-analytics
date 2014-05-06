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


	render_elapsed_time_since("start");


	$_arr_cmp =  array(
		 "s1" => 1
		,"s2" => 1
		,"s3" => 1
		,"s4" => 1
		,"s5" => 1
		,"s6" => 1
		,"s7" => 1
		,"s8" => 1
		,"s8" => 1
		,"s9" => 1
		,"r9" => 1
		,"b1" => 1
	);

	foreach($_arr_hostnames as $_k => $_v){
		$_k2 = substr($_k,0,2);
		if( $_arr_cmp[$_k2] ) continue;
		$_xx[$_k] = $_v;
	}
	#$_xx = $_arr_hostnames;

	$_dis_dropdown = render_select("hostnames[]", $_xx, array(accesskey=> 'h', id=>'fld_hostnames', "event_handler"=>"", attributes=>'multiple="multiple" size="18" ', style=>'width:220px;font-size:9px;font-family:verdana;') ) ;
	#echo $_dis_dropdown ;


		global $DB_CONNECTION;
		global $PUB_DB_HOST;

	$_cntr = 0;
	foreach($_xx as $_k => $_v){
		$_cntr++;

		$DB_CONNECTION = null;
		$PUB_DB_HOST = $_k;
		#connectPublicDB();
		$DB_CONNECTION = mysql_connect( $_k , $DB_USER , $DB_PASSWORD , false , MYSQL_CLIENT_COMPRESS );

		$_arr_1 = execute_query("
			select 
				concat_ws( '.',table_schema,table_name) 'Name Tabelle'
				, engine 'Storage<br>Engine'
				, round( table_rows / 1000000, 0) 'Mio Zeilen'
				/* , avg_row_length */
				, round( data_length / (1024*1024) ,0 ) MB
			from information_schema.tables 
			where table_type = 'BASE TABLE'  AND TABLE_SCHEMA NOT IN ('MYSQL','INFORMATION_SCHEMA')  
			order by MB desc

		");

		echo array_to_table($_arr_1, array('heading'=> "Host $_k - biggest tables", 'cutoff' => 10)  );



		$_arr_2[$_k] = execute_query("
			select 
				  sum(  round( table_rows / 1000000, 0)  )  'Mio Zeilen total' 
				, sum(  round( data_length / (1024*1024) ,0 ) ) 'MB total'
			from information_schema.tables 
			where table_type = 'BASE TABLE'  AND TABLE_SCHEMA NOT IN ('MYSQL','INFORMATION_SCHEMA')  

		");

		echo array_to_table($_arr_2[$_k], array('heading'=> "Host $_k - storage summary", 'cutoff' => 10)  );




		$_arr_3[$_k] = execute_query("
			SHOW ENGINE INNODB STATUS
		");

		$_arr_3[$_k] = $_arr_3[$_k][0]['Status'];
		$_arr_tmp = explode( "\n" , $_arr_3[$_k]) ;
		foreach($_arr_tmp as $_key_unused => $_val){
			if( $_val ){
				if( stristr($_val,"inserts") AND stristr($_val,"updates") AND stristr($_val,"reads") ){
					$_arr_3[$_k] = $_val;
				}
			}
		}
		if( ! is_array($_arr_3[$_k]) ){
			$_arr_3[$_k] = explode( "," , $_arr_3[$_k]) ;
			foreach( $_arr_3[$_k] as $_k2 => $_v2 ){
				$_v2 = trim($_v2);
				$_pos = stripos($_v2, " ");
				if( $_pos > 0 ) $_v2 = substr($_v2,0,$_pos);
				if( floatval($_v2) > 0 ){
					$_arr_3[$_k][$_k2] = floatval($_v2);
					$_arr_3[$_k][$_k2] = intval($_v2);
				} else {
					$_arr_3[$_k][$_k2] = 0;
				}
			}
		}
		echo array_to_table( $_arr_3[$_k], array('heading'=> "Host $_k - inserts/updates/deletes/reads per sec", 'cutoff' => 10)  );


		#if( $_cntr > 2 ) break;
	}



	$_arr_size_total = array(
		 'Mio Zeilen total' => 2
		,'MB total' => 4
	);
	foreach($_arr_2 as $_k => $_lp_arr){
		#vd($_lp_arr);
		foreach($_lp_arr[0] as $_k1 => $_v1 ){
			#vd("$_k1 => $_v1");
			$_arr_size_total[$_k1] += $_v1;
		}
	}
	vd($_arr_size_total);



	foreach($_arr_3 as $_k => $_lp_arr){
		#vd($_lp_arr);
		foreach($_lp_arr as $_k1 => $_val1 ){
			$_arr_ops_total[$_k1] += $_val1;
		}
	}
	#vd($_arr_ops_total);
	$_arr_ops_total1['inserts'] = $_arr_ops_total[0];
	$_arr_ops_total1['updates'] = $_arr_ops_total[1];
	$_arr_ops_total1['deletes'] = $_arr_ops_total[2];
	$_arr_ops_total1['reads'] = $_arr_ops_total[3];
	vd($_arr_ops_total1);
		


	echo render_layout_footer();



?>