<?php
# compares SQL schema of host.schema-name



	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");


	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();

	$_c1 = 200;
	$_c2 = 190;
	$_c3 = 35;
	$_c4 = 35;


	
	
	render_elapsed_time_since("start");

	echo "<h1>compare schema</h1>";

	echo "
		<style>
			del {
				font-size:14px;
				font-weight:bold;
				color: #c22;
			}
			ins {
				font-size:14px;
				font-weight:bold;
				color: #2c2;
			}


		</style>

	";

	echo "<form>";


	$_host_1			=  get_param( "hostname");
	$_schema_1     =  get_param( "schema_1");
	$_host_2			=  get_param( "host_2");
	$_schema_2     =  get_param( "schema_2");



	echo render_inline_block("select host ",$_c1);
	$_arr_hn1 = array( "" => "mysql host auswählen" ) + $_arr_hostnames;
	echo render_select("hostname" , $_arr_hn1, array(accesskey=> 'h')) ;
	echo "<br>";

	
	if( $_host_1   ) {

		$_arr_t = execute_query(" show databases");
		foreach($_arr_t as $_key_unused => $_arr_lp){
			$_lp_name = $_arr_lp["Database"];
			#vd($_lp_name);
			$_arr_schemas_1[$_lp_name] = $_lp_name;
		}
		$_arr_schemas_1 = array( "" => "Schema auswählen" ) + $_arr_schemas_1;
		echo render_inline_block("select schema ",$_c1);
		echo render_select("schema_1" , $_arr_schemas_1  );
		echo "<br>";
		
	}

	if( $_host_1  AND $_schema_1 ) {

		echo "<hr>";

		echo render_inline_block("select host 2",$_c1);
		$_arr_hn1 = array( "" => "mysql host auswählen" ) + $_arr_hostnames;
		echo render_select("host_2" , $_arr_hn1, array(accesskey=> 'h')) ;
		echo "<br>";
		
	}
	if( $_host_1  AND $_schema_1  AND $_host_2 ) {

		reconnect($_host_2);

		$_arr_t = execute_query(" show databases");
		foreach($_arr_t as $_key_unused => $_arr_lp){
			$_lp_name = $_arr_lp["Database"];
			$_arr_schemas_2[$_lp_name] = $_lp_name;
		}
		$_arr_schemas_2 = array( "" => "Schema 2 auswählen" ) + $_arr_schemas_2;
		echo render_inline_block("select schema ",$_c1);
		echo render_select("schema_2" , $_arr_schemas_2  );
		echo "<br>";
		
	}
	echo "</form>";

	flush_http();


	if( $_host_1  AND $_schema_1  AND $_host_2   AND $_schema_2   ) {


		reconnect($_host_1,$_schema_1);
		$_arr_t = execute_query(" 			select table_name from information_schema.tables where table_schema = '$_schema_1'; 		");
		foreach($_arr_t as $_key_unused => $_arr_lp){
			$_lp_tn = $_arr_lp['table_name'];
			#$_sql = "RENAME TABLE `{$_schema_1}`.`{$_lp_tn}` TO `{$_db_dest}`.`{$_lp_tn}` ";			
			$_sql = " SHOW CREATE TABLE  `{$_schema_1}`.`{$_lp_tn}`	";
			#vd($_sql );
			$_arr_r = execute_query_get_first($_sql);
			$_arr_xx[$_lp_tn][$_schema_1]  = proc_ct( $_arr_r['Create Table']);
			$_arr_xx[$_lp_tn][$_schema_2]  = "";
		}

		echo "<hr>";

		reconnect($_host_2,$_schema_2);
		$_arr_t = execute_query(" 			select table_name from information_schema.tables where table_schema = '$_schema_2'; 		");
		foreach($_arr_t as $_key_unused => $_arr_lp){
			$_lp_tn = $_arr_lp['table_name'];
			#$_sql = "RENAME TABLE `{$_schema_1}`.`{$_lp_tn}` TO `{$_db_dest}`.`{$_lp_tn}` ";			
			$_sql = " SHOW CREATE TABLE  `{$_schema_2}`.`{$_lp_tn}`	";
			#vd($_sql );
			$_arr_r = execute_query_get_first($_sql);
			$_arr_xx[$_lp_tn][$_schema_2]  = proc_ct( $_arr_r['Create Table']);
			if( ! $_arr_xx[$_lp_tn][$_schema_1] )  $_arr_xx[$_lp_tn][$_schema_1] = "";

		}


		#vd($_arr_xx);
		echo "<br>";
		echo render_inline_block(" ", $_c2);
		echo render_inline_block( "<h1>{$_host_1}.{$_schema_1}</h1>"  ,$_c3. "%");
		echo render_inline_block( "<h1>{$_host_2}.{$_schema_2}</h1>"  ,$_c4. "%");
		echo "<br>";


		foreach($_arr_xx as $_lp_tn => $_lp_arr){

			$_a = $_lp_arr[$_schema_1];
			$_b = $_lp_arr[$_schema_2];

			#vd($_a . $_b);

			/*
			$file = "dmp/".$_lp_tn . "-" .$_schema_1 .'.txt';
			file_put_contents($file, $_a);
			$file = "dmp/".$_lp_tn . "-" .$_schema_2 .'.txt';
			file_put_contents($file, $_b);
			*/

			render_inline_block($_lp_tn,$_c1);

			if( strcmp($_a,$_b) == 0 ){
				$_ls = $_c3 + $_c4;
				echo render_inline_block("$_lp_tn", $_c2);
			
				echo render_inline_block("identisch in beiden Schemata", $_ls. "%",  "text-align: center; margin: 4px 1px");
				#echo render_inline_block($_a,$_ls . "%", "font-size: 9px; line-height:11px; padding: 10px 0;");
			}
			else if( $_a  &&  ! $_b ){
				echo render_inline_block("nur links", $_c2);
				echo render_inline_block( $_a ,$_c3. "%");
				echo render_inline_block( " " ,$_c4. "%");
			}

			else if( ! $_a  &&  $_b ){
				echo render_inline_block("nur rechts", $_c2);
				echo render_inline_block( " " ,$_c3. "%", "margin: 4px 1px");
				echo render_inline_block( $_b ,$_c4. "%", "margin: 4px 1px");
			}

			else if( $_a  <>  $_b ){
				echo render_inline_block("$_lp_tn<br>verschieden", $_c2);
				$_ds1 = render_string_diff($_a, $_b, "margin: 4px 1px");
				$_ds2 = render_string_diff($_b, $_a, "margin: 4px 1px");


				echo render_inline_block( $_a   ,$_c3. "%", "margin: 4px 1px");
				echo render_inline_block( $_ds2 ,$_c4. "%", "margin: 4px 1px");
			} else {
				echo "else block should never been reached";
			}

			echo "<br>";
			echo "<hr>";

		}

		

	}
	echo render_layout_footer();
	exit;		






function proc_ct($_arg){
	$_startpos = 12;
	$_len2 = 40;
	$_len2 = 3340;

	$_ret = $_arg;

	$_ret = replace_all_whitespace_with($_ret," ");


	$_ret = str_replace("','","', '",$_ret);

	$_ret = str_replace("`","",$_ret);

	$_ret = preg_replace( '/AUTO_INCREMENT=[0-9]+ /i', ''  ,$_ret);

	$_ret = preg_replace( '/ROW_FORMAT=COMPACT/i', ''  ,$_ret);

	#$_ret = preg_replace( '/AUTO_INCREMENT=[0-9]+/i', 'AUTO_INCREMENT=xxx'  ,$_ret);


	$_ret = str_replace("PRIMARY KEY","	<br>PRIMARY KEY",$_ret);
	$_ret = str_replace(", KEY",",<br>KEY",$_ret);
	$_ret = str_replace("ENGINE","<br>ENGINE",$_ret);

	$_ret = replace_all_whitespace_with($_ret," ");
	$_ret = trim($_ret);
	 

	$_ret = substr( $_ret ,$_startpos , $_len2 );
	return $_ret;

}


?>