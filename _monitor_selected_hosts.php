<?php
# Monitor a selected number of mysql hosts by function


	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");

	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();

	foreach( $_arr_hostnames as $_key => $_lp_arr ){
		#vd(" ssh idealo@$_lp_arr ");
	}

	echo "
		<style type='text/css'>
			#frm_select_hostname_01{ display:none; }
		</style>
	";

	echo "<h1 style='margin-top:0px;padding-top:0px;'>Überwachung mehrerer MySQL Maschinen parallel <span style='font-weight:normal;font-size:10px'>dynamically adding slaves</span></h1>";




	$_param_arr_hosts_default = array(
		 "amazon-db"
		,"content-db"
		,"ebay-db"
		,"offer-db"
	);
	$_param_hosts = get_param("hostnames") ; 
	if( is_array($_param_hosts) AND sizeof( $_param_hosts ) ){
		$_param_arr_hosts = $_param_hosts;
	} else {
		$_param_arr_hosts = $_param_arr_hosts_default;
		$_REQUEST['hostnames'] = $_param_arr_hosts_default;
		#vd( get_param("hostnames") );
		
	}



	$_arr_hosts_by_name = array();
	foreach($_param_arr_hosts as $_key_unused => $_lp_host){



		$_lp_numeric_host = resolve_descriptive_hostname($_lp_host);

		if( $_lp_numeric_host ){
			$_arr_hosts_by_name[$_lp_host] = $_lp_numeric_host ;

			if( !	preg_match( "/^[brs][x0-9]{1,1}./i", $_lp_host) ){
				#vd("$_lp_host is descriptive");

				$_lp_is_descriptive_hostname = true;

				if( get_param("with_slaves") ){
					$_lp_descriptive_slave1 = $_lp_host . "-s1";
					$_lp_numeric_slave1 = resolve_descriptive_hostname($_lp_descriptive_slave1);
					#vd("master is $_lp_numeric_host - checking slave $_lp_descriptive_slave1 as $_lp_numeric_slave1");
					if( $_lp_numeric_slave1  AND !in_array( $_lp_numeric_slave1 , $_arr_hosts_by_name  ) ){
						$_arr_hosts_by_name[$_lp_descriptive_slave1] = $_lp_numeric_slave1 ;
					}

					$_lp_descriptive_slave2 = $_lp_host . "-s2";
					$_lp_numeric_slave2 = resolve_descriptive_hostname($_lp_descriptive_slave2);
					#vd("master is $_lp_numeric_host - checking slave $_lp_descriptive_slave2 as $_lp_numeric_slave2");
					if( $_lp_numeric_slave2  AND !in_array( $_lp_numeric_slave2 , $_arr_hosts_by_name  ) ){
						$_arr_hosts_by_name[$_lp_descriptive_slave2] = $_lp_numeric_slave2 ;
					}

				}

					
			}



		} else {
			# already IS a numeric host
			$_arr_tmp = explode( ".",$_lp_host );
			$_arr_hosts_by_name[$_lp_host] = $_arr_tmp[0] ;
		}
					
	}










	$_hid = getHiddenInputs( array('hostnames','hostnames[]'));
	$_hours_past     = get_param("hours_past",2);
	$_hours_until = get_param("hours_until","0",true);
	$p2 = get_param("filter_query_by","");
	$_c1w = 120;
	$_c2w = 220;

	echo "<form>";

	echo $_hid;

	$_cell1 = "";
	$_cell1 .= render_inline_block("Stunden rückwärts"  , false, "margin-right:10px;min-width:100px;");
	$_cell1 .= render_inline_block("<input  type='text' name='hours_past'  value='$_hours_past' style='width:40px;' />");

	$_cell1 .= render_br();


	$_cell1 .= render_inline_block("Stunden bis"        , false, "margin-right:10px;min-width:100px;");
	$_cell1 .= render_inline_block("<input  type='text' name='hours_until'  value='$_hours_until' style='width:40px;' />");


	$_cell1 .= render_br();

	$_period = get_param("period");
	$_cell1 .= render_inline_block("Interval"  , false, "margin-right:10px;min-width:100px;");
	$_cell1 .= render_inline_block("<input  type='text' name='period'  value='$_period' style='width:60px;' /><span style='font-size:10px;'><br>bspw. 10h oder 20min</span>");

	$_cell2 = "";
	$_dis_dropdown = render_select("hostnames[]", $_arr_hostnames, array(accesskey=> 'h', id=>'fld_hostnames', "event_handler"=>"", attributes=>'multiple="multiple" size="18" ', style=>'width:220px;font-size:9px;font-family:verdana;') ) ;
	$_dis_hosts = "";
	$_delim = "<br>";
	foreach( $_arr_hosts_by_name as $_k => $_v ){
		$_dis_hosts .= "{$_k}&nbsp;($_v){$_delim}";
	}
	if( substr($_dis_hosts, - strlen($_delim) ) == $_delim  ){
		$_dis_hosts= substr($_dis_hosts,0, - strlen($_delim) );
	}
	$_dis_hosts = str_replace("-","_",$_dis_hosts);


	#$_dis_dropdown .= "<br>";
	$_dis_dropdown .= render_inline_block("Ausgewählt:<br>$_dis_hosts",250,"font-size:12px; margin:0; padding:0; vertical-align:top;");
	
	$_dis_check = 	render_checkbox("with_slaves",array("accesskey"=>"v"));


	$_cell2 .= render_inline_block("HostNames <span style='font-size:9px'><br>(multiple select)<br><br>mit Sla<u>v</u>es<br>$_dis_check </span>"  , false, "vertical-align:top;line-height:11px;margin-right:10px;");
	$_cell2 .= render_inline_block($_dis_dropdown);




	echo render_inline_block($_cell1,false,"vertical-align:top;margin-right:10px;");
	echo render_inline_block($_cell2,false,"vertical-align:top;margin-right:30px;");

	#echo render_inline_block(" "  , $_c1w);
	echo render_inline_block("<input type='submit' value='Submit'  accesskey='s' title='Shortcut Shift+Alt+s' style='width:{$_c2w}px; ' />");

	echo "</form><br>";

	flush_http();



















	add_color("reset_fading_factor");
	$_str_target = "";
	foreach($_arr_hosts_by_name as $_hostname_descriptive => $_lp_host){
		

		$_s1 = add_time_shift("sumSeries(server.mysql.{$_lp_host}.com_{insert,insert_select,update,update_select,delete,delete_select,replace,replace_select})");
		#$_s1 = add_time_shift("server.mysql.{$_lp_host}.com_{insert,insert_select,update,update_select,delete,delete_select,replace,replace_select}");

		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);

		$_s1 = add_alias($_s1,"$_hostname_descriptive");

		#$_str_target .= "&target=alias(    sumSeries(server.mysql.{$_lp_host}.com_{insert,insert_select,update,update_select,delete,delete_select,replace,replace_select})  ,\"{$_hostname_descriptive}\")";
		$_str_target .= "&target={$_s1}";;


	}
	$_arr_diagramms[]  = concat_img_src($_str_target,"Insert, Update, Delete, Replace Statements");


	


	add_color("reset_fading_factor");
	$_str_target = "";
	foreach($_arr_hosts_by_name as $_hostname_descriptive => $_lp_host){
		$_s1 = add_time_shift("sumSeries(server.mysql.{$_lp_host}.com_{select,xxxx})");
		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);
		$_s1 = add_alias($_s1,"$_hostname_descriptive");
		$_str_target .= "&target={$_s1}";;
	}
	$_arr_diagramms[]  = concat_img_src($_str_target,"Select Statements");






	add_color("reset_fading_factor");
	$_str_target = "";
	foreach($_arr_hosts_by_name as $_hostname_descriptive => $_lp_host){


/*
		$_s1 = add_time_shift("server.mysql.{$_lp_host}.key_read_requests");
		$_s2 = add_time_shift("server.mysql.{$_lp_host}.key_write_requests");
		$_s3 = add_time_shift("server.mysql.{$_lp_host}.key_reads");
		$_s4 = add_time_shift("server.mysql.{$_lp_host}.key_writes");


		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);
		$_s2 = add_color($_s2,$_arr_hosts_by_name,$_lp_host);
		$_s3 = add_color($_s3,$_arr_hosts_by_name,$_lp_host);
		$_s4 = add_color($_s4,$_arr_hosts_by_name,$_lp_host);

		$_s1 = add_alias($_s1,"$_hostname_descriptive Key Read Requests");
		$_s2 = add_alias($_s2,"$_hostname_descriptive Key Write Requests");
		$_s3 = add_alias($_s3,"$_hostname_descriptive Key Reads");
		$_s4 = add_alias($_s4,"$_hostname_descriptive Key Writes");

		$_str_target .= "&target={$_s1}";
		$_str_target .= "&target={$_s2}";
		$_str_target .= "&target={$_s3}";
		$_str_target .= "&target={$_s4}";
*/


/*
		$_s1 = add_time_shift("sumSeries(server.mysql.{$_lp_host}.key_read_requests,server.mysql.{$_lp_host}.key_write_requests)");
		$_s1 = add_time_shift("sumSeries(server.mysql.{$_lp_host}.key_reads,server.mysql.{$_lp_host}.key_writes)");


		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);
		$_s2 = add_color($_s2,$_arr_hosts_by_name,$_lp_host);

		$_s1 = add_alias($_s1,"$_hostname_descriptive Key Requests (r/w)");
		$_s2 = add_alias($_s2,"$_hostname_descriptive Key Read Write");

		$_str_target .= "&target={$_s1}";
		$_str_target .= "&target={$_s2}";
*/

		$_s1 = add_time_shift("server.mysql.{$_lp_host}.key_read_requests");
		$_s2 = add_time_shift("server.mysql.{$_lp_host}.key_write_requests");
		$_s3 = add_time_shift("server.mysql.{$_lp_host}.key_reads");
		$_s4 = add_time_shift("server.mysql.{$_lp_host}.key_writes");


		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);
		$_s2 = add_color($_s2,$_arr_hosts_by_name,$_lp_host);
		$_s3 = add_color($_s3,$_arr_hosts_by_name,$_lp_host);
		$_s4 = add_color($_s4,$_arr_hosts_by_name,$_lp_host);

		$_s1 = add_alias($_s1,"$_hostname_descriptive Key Read Requests");
		$_s2 = add_alias($_s2,"$_hostname_descriptive Key Write Requests");
		$_s3 = add_alias($_s3,"$_hostname_descriptive Key Reads");
		$_s4 = add_alias($_s4,"$_hostname_descriptive Key Writes");

		$_str_target .= "&target={$_s1}";
		$_str_target .= "&target={$_s2}";
		$_str_target .= "&target={$_s3}";
		$_str_target .= "&target={$_s4}";


	}
	$_arr_diagramms[]  = concat_img_src($_str_target,"Index based Data Access");




	add_color("reset_fading_factor");
	$_str_target = "";
	foreach($_arr_hosts_by_name as $_hostname_descriptive => $_lp_host){
		$_s1 = add_time_shift("sumSeries(server.mysql.{$_lp_host}.handler_read_rnd,server.mysql.{$_lp_host}.handler_read_rnd_next)");
		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);
		$_s1 = add_alias($_s1,"$_hostname_descriptive Random Reads");
		$_str_target .= "&target={$_s1}";
	}
	$_arr_diagramms[]  = concat_img_src($_str_target,"Data Access without Index (slow)");







	add_color("reset_fading_factor");
	$_str_target = "";
	foreach($_arr_hosts_by_name as $_hostname_descriptive => $_lp_host){

		$_s1 = add_time_shift("server.mysql.{$_lp_host}.bytes_sent");
		$_s2 = add_time_shift("server.mysql.{$_lp_host}.bytes_received");

		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);
		$_s2 = add_color($_s2,$_arr_hosts_by_name,$_lp_host);

		$_s1 = add_alias($_s1,"$_hostname_descriptive Bytes Out");
		$_s2 = add_alias($_s2,"$_hostname_descriptive Bytes In");

		$_str_target .= "&target={$_s1}";
		$_str_target .= "&target={$_s2}";
	}
	$_arr_diagramms[]  = concat_img_src($_str_target,"Outbound Traffic - Inbound Traffic");





	add_color("reset_fading_factor");
	$_str_target = "";
	foreach($_arr_hosts_by_name as $_hostname_descriptive => $_lp_host){

		$_s1 = add_time_shift("sumSeries(server.mysql.{$_lp_host}.connections_clients_aborted,server.mysql.{$_lp_host}.connections_connects_aborted)");
		$_s2 = add_time_shift("server.mysql.{$_lp_host}.connections_max");
		$_s3 = add_time_shift("server.mysql.{$_lp_host}.connections_max_used");


		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);
		$_s2 = add_color($_s2,$_arr_hosts_by_name,$_lp_host);
		$_s3 = add_color($_s3,$_arr_hosts_by_name,$_lp_host);

		$_s1 = add_alias($_s1,"$_hostname_descriptive Connects + Clients abort");
		$_s2 = add_alias($_s2,"$_hostname_descriptive Connections max");
		$_s3 = add_alias($_s2,"$_hostname_descriptive Connections max used");

		$_str_target .= "&target={$_s1}";
		$_str_target .= "&target={$_s2}";
		$_str_target .= "&target={$_s3}";


	}
	$_arr_diagramms[] = concat_img_src($_str_target,"Connections");





	add_color("reset_fading_factor");
	$_str_target = "";
	foreach($_arr_hosts_by_name as $_hostname_descriptive => $_lp_host){
		$_s2 = "offset(server.mysql.{$_lp_host}.slave_lag,2000)";
		$_s2 = "offset(server.mysql.{$_lp_host}.slave_lag,0)";
		$_s2 = add_color($_s2,$_arr_hosts_by_name,$_lp_host);
		$_s2 = add_alias($_s2,"$_hostname_descriptive Slave Lag");
		$_str_target .= "&target={$_s2}";
	}
	$_arr_diagramms[]  = concat_img_src($_str_target,"Slave Behind");








	add_color("reset_fading_factor");
	$_str_target = "";
	foreach($_arr_hosts_by_name as $_hostname_descriptive => $_lp_host){
		$_s1 = add_time_shift("server.mysql.{$_lp_host}.slow_queries");
		$_s1 = add_color($_s1,$_arr_hosts_by_name,$_lp_host);
		$_s1 = add_alias($_s1,"$_hostname_descriptive Slow Queries");
		$_str_target .= "&target={$_s1}";
	}
	$_arr_diagramms[]  = concat_img_src($_str_target,"Slow Queries");





	$_arr_exclude = array(2,3,4,5);
	$_arr_exclude = array();
	foreach($_arr_diagramms  as $_key_metrics_group => $_lp_src){
		if( in_array( $_key_metrics_group, $_arr_exclude)  ) continue; 
		echo "<img  src='$_lp_src' style='width:{$_w01}px;height:{$_h01}px;margin-right:20px;'  />";
	}





	flush_http();


	echo render_layout_footer();


	# translates "offer-db.ipx" into "s155" without suffix
	# numerical hostnames like "s155" return false
	function resolve_descriptive_hostname($_lp_host){

		# commands dig + host are also possible
		$_arr_dns_info = dns_get_record($_lp_host, DNS_ANY, $_arr_authns, $_arr_addtl);
		#echo array_to_table( $_arr_dns_info );
		#echo array_to_table( $_arr_authns );
		#echo array_to_table( $_arr_addtl );

		$_arr_tmp = explode( ".",$_arr_dns_info[0]['target'] );

		if( $_arr_tmp[0] ){
			return $_arr_tmp[0];
		} else {
			return false;
		}
		

	}


	function concat_img_src($_str_target,$_title){

		require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require

		static $_rnd;
		if( ! isset($_rnd) ){
			$_rnd =  mt_rand(10000, 99999);
		}


		$_w01 = 650;
		$_h01 = 300;
		$_hours_past = get_param("hours_past",2);
		$_hours_until = get_param("hours_until","0",true);

		$_title = replace_all_whitespace_with($_title,'%20');

		/*
			&format=svg

			&colorList=#002244,#FF&600 ,green,yellow,orange,red,purple,#DECAFF
		*/
		$_min_requ_colors = substr_count($_str_target,"target=");
		if( true or $_min_requ_colors < 2*5 ){
			$_color_list = "&colorList=#002244,#FF6600";


			$_arr_colors = array(
				 "#002244"
				,"#FF6600"
				,"#00BB00"
			);
			$_str_colors = implode(",",$_arr_colors);

			foreach($_arr_colors as $_unused => $_lp_col){
				$_lp_col = darken_color($_lp_col,-40);
				$_str_colors .= ",{$_lp_col}";
			}
			foreach($_arr_colors as $_unused => $_lp_col){
				$_lp_col = darken_color($_lp_col, 40);
				$_str_colors .= ",{$_lp_col}";
			}


			
			$_color_list = "&colorList={$_str_colors}";
			#vd($_color_list);

		} else {
			$_color_list = "";
		}
		

		/*
			&logBase=10
		*/


		$_len = strlen(  replace_all_whitespace_with($_str_target));
		if( FALSE AND $_len > 1100 ){
			echo "Graphite URL for chart <b>". str_replace("%20"," ",$_title)."</b> is $_len bytes long: ";
			#echo substr(replace_all_whitespace_with($_str_target),0,100) ;
			echo  " ...  ";

			$_arr_t = explode( "&" , trim($_str_target) );
			#array_shift($_arr_t);  # # chop off leading empty entry
			$_size_before = sizeof($_arr_t) - 1;
			$_len_one = strlen($_arr_t[1]);
			$_max = floor(1200/$_len_one) ;
			$_arr_t = array_slice($_arr_t ,0,$_max);
			$_str_target = implode("&",$_arr_t);
			$_size_after = sizeof($_arr_t) - 1;

			echo " Gekürzt von $_size_before to $_size_after<br>\n";

		}


		$_img_src_xx = "
			http://{$_GRAPHITE_HOST}/render?
			&width={$_w01}&height={$_h01}
			&from=-{$_hours_past}hours&until=-{$_hours_until}hours
			&lineMode=connected
			$_str_target
			&hideLegend=false
			&fontSize=8.5
			&_uniq={$_rnd}
			&title={$_title}
			$_color_list
			&lineWidth=3
			&bgcolor=#FFFFFF
			&fgcolor=#002244
			&margin=14
			&tz=CET
			&yMin=0
		";

		$_img_src_xx = replace_all_whitespace_with($_img_src_xx);
		$_img_src_xx = str_replace("#","%23",$_img_src_xx);

		$_arr_args = explode("&",$_img_src_xx );
		$_dummy = array_shift($_arr_args);					# chop off leading empty entry
		
		$_resp= make_post_request($_arr_args);

		return $_resp;
			

		vd($_img_src_xx);
		$_img_src_xx = "http://{$_GRAPHITE_HOST}/render?" . $_img_src_xx;
		return $_img_src_xx ;

	}


	function darken_color($_color,$_percent=20){


		if(!preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $_color, $_parts)) vd("Not a value color: $_color");				

		$_pre_parts = $_parts;

		$_already_very_bright = false;
		for( $i = 1; $i <= 3; $i++ ){
			$_pre_parts[$i] = hexdec($_pre_parts[$i]);
			if( 			$_percent   	 <0  			# lighter
					AND 	$_pre_parts[$i] >240 			#  already light
			){
				$_already_very_bright = true;
				#vd("$_color already bright");
				break;
			}
		}

		$out = ""; 
		for( $i = 1; $i <= 3; $i++ ){
			#echo "$_parts[$i] ...";
			$_parts[$i] = hexdec($_parts[$i]);
			#echo " - $_parts[$i] <br>\n";
			$_factor = (100-$_percent)/100 ;

			if( FALSE AND $_already_very_bright ){
				$_parts[$i] = round( $_parts[$i] + (255-$_parts[$i])*(-$_percent/100) ); # asymptotically approach #fff - never reach it
			} else {
				$_parts[$i] = round( $_parts[$i] * $_factor ) ; // 80/100 = 80%, i.e. 20% darker
				if( $_parts[$i]  > 255 ) $_parts[$i] = 255;
				if( $_parts[$i]  < 1   ) $_parts[$i] = 1;
			}
			
			$out .= str_pad(dechex($_parts[$i]), 2, '0', STR_PAD_LEFT);
		}

		return "#".$out;


	}

	function add_color($_s1,$_arr_hosts=array(), $_host=""){
		
		$_arr_colors = array(
			 "002244"
			,"FF6600"
			,"AA1111"
			,"11AA11"
			,"1111AA"
			,"AA11AA"
			,"AAAA11"
			,"11AAAA"
		);

		$_arr_colors = array(
			 "881111"
			,"118811"
			,"111188"
			,"881188"
			,"888811"
			,"118888"
		);

		$_arr_colors = array(
			 "11CC11"
			,"CC1111"
			,"1111CC"
			,"CC11CC"
			,"CCCC11"
			,"11CCCC"
		);

		$_arr_hosts = array_values($_arr_hosts);
		$_arr_hosts = array_flip($_arr_hosts);


		static $_arr_fading_factor;
		if( ! isset($_arr_fading_factor) OR  $_arr_fading_factor == "reset_fading_factor"){
			#vd("init_fading_factor");
			$_arr_fading_factor = $_arr_hosts;
			foreach($_arr_fading_factor as $_key2 => $_val2){
				$_arr_fading_factor[$_key2] = -1;
			}
		}

		if( $_s1 == "reset_fading_factor" ){
			$_arr_fading_factor = "reset_fading_factor";
			#vd("reset_fading_factor");
			return;
		}



		$_index = $_arr_hosts[$_host];
		$_color = $_arr_colors[$_index];
		#echo "$_host  - $_index - $_color ";
		$_color = darken_color($_color, $_arr_fading_factor[$_host] * 35 );
		$_arr_fading_factor[$_host]++;
		#echo " $_color <br>\n";

		$_s1 = "color({$_s1},\"{$_color}\")";
		return $_s1;
	}


	function add_alias($_s1,$_title){
		$_title = str_replace(" ","%20",$_title);
		#$_s1 = "removeAbovePercentile({$_s1},75)";  # not working
		$_s1 = "alias({$_s1},\"{$_title}\")";
		return $_s1;
	}


	function add_time_shift($_s1){
		$_period = get_param("period","1h");
		$_ret = "diffSeries({$_s1},timeShift({$_s1},\"{$_period}\"))";
		#$_ret = "derivative({$_s1})";		# not working
		return $_ret;
	}



	function make_post_request($_arr_args){

		#$_created_dir = mkdir( dirname(__FILE__) . "/img_dyn", 0777);
		#if( ! $_created_dir ){ vd("could not create director img_dyn"); }


		global $_GRAPHITE_HOST;
		$url = "http://{$_GRAPHITE_HOST}/render/?";
		$ch = curl_init($url);
		$_arr_args = implode("&",$_arr_args);
		curl_setopt($ch  , CURLOPT_POST, 1);
		curl_setopt($ch  , CURLOPT_POSTFIELDS, $_arr_args);
		curl_setopt($ch  , CURLOPT_RETURNTRANSFER, true);
		$_resp = curl_exec($ch);


		static $_file_num;
		if( ! isset($_file_num) ) $_file_num = 0;
		$_file_num++;
		if( $_file_num > 99) $_file_num =1;
		$_fn = str_pad($_file_num, 3, "0", STR_PAD_LEFT);

		$fp = fopen( dirname(__FILE__) . "/img_dyn/{$_fn}.png", 'w');
		fwrite($fp, $_resp);
		fclose($fp);

		curl_close($ch);

		#vd($_resp,"<br>resp");
		$_src = "/" . basename( dirname(__FILE__) )  .  "/img_dyn/{$_fn}.png";
		#vd($_src,"src");

		return  $_src;

	}

?>