<?php
# OLD version of sending mysql performance data to graphite - here still in combination WITH iostat hard disk parameters. new version is percona_mysql_stats_wrapper.php


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

	echo "<h1>Messung beginnt - erste Ergebnisse in wenigen Sekunden...</h1>";
	flush_http();


	for($i=0;$i<get_param('iterations',4);$i++){


		$_cmd = " iostat 2 1 -cdtxzm -p sdc  ";
		$_cmd = " iostat 2 1 -cdtxzm   ";
		$_cmd = " iostat 2 1 -cdtxz   ";
		$_str_cmd_output = remote_command_via_ssh( $_cmd );


		# we are padding the various delimiter strings with "delim_" - and split along THIS string
		$_array_delims_all = array("avg-cpu:","Device:" );
		$_array_delims_cleanse = array("avg-cpu:");
		foreach( $_array_delims_all as $_key => $_val ){
			$_array_delims_all_prep[] = "delim_".$_val;
		}
		$_str_cmd_output = str_ireplace( $_array_delims_all,$_array_delims_all_prep, $_str_cmd_output);
		$_str_cmd_output = str_ireplace( $_array_delims_cleanse,"", $_str_cmd_output);

		$_arr_cmd_output = split_by( $_str_cmd_output, '(delim_)+' );


		# split the blocks into rows
		$_arr_cmd_output_1 = array();
		foreach($_arr_cmd_output as $_unused => $_lp_str_cmd){
			$_arr_lp = split_by( $_lp_str_cmd );
			$_arr_cmd_output_1[] = $_arr_lp;
		}


		vd( $_arr_cmd_output_1[0] );
		#vd( $_arr_cmd_output_1[1] );
		#vd( $_arr_cmd_output_1[2] );


		$_arr_iostat_1 = parse_table_as_value_field( $_arr_cmd_output_1[1] );
		$_arr_iostat_2 = parse_table_as_value_field( $_arr_cmd_output_1[2] , 1 );


		$_arr_iostat_1 = add_descriptions( $_arr_iostat_1 );
		$_arr_iostat_2 = add_descriptions( $_arr_iostat_2 );


		if( get_param("dbg_fields") )vd($_arr_iostat_1,arr_iostat_1);
		if( get_param("dbg_fields") )vd($_arr_iostat_2,arr_iostat_2); 


		$_arr_innodb_stat = render_innodb_status(2);
		if( get_param("dbg_fields") )vd($_arr_innodb_stat,arr_innodb_stat) ;


		$address = 'r900.ipx';		#http://r900.ipx/graphite
		$port = 2003;
 		$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
 		$sockconnect = socket_connect($sock, $address, $port);
		vd("connected to $address $port - $sockconnect");

		$_graphite_timestamp = mktime();
		
		write_graphite_msg("db-ops",$sock,$_graphite_timestamp,$_arr_innodb_stat);
		write_graphite_msg("cpu",   $sock,$_graphite_timestamp,$_arr_iostat_1);
		write_graphite_msg("io-ops",$sock,$_graphite_timestamp,$_arr_iostat_2);

 		socket_close($sock);


		flush_http();
		sleep(  get_param("interval",600) );

	
	}


	echo render_layout_footer();


	function write_graphite_msg($_group_prefix="generic",$sock,$_graphite_timestamp,$_arr){

		require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require

		foreach($_arr as $_unused => $_arr_lp){
			$_graphite_key = $_arr_lp[1];
			$_graphite_val = $_arr_lp[0];

			#vd($msg);
			$msg = "{$_graphite_prefix}{$_HOST_ABBREVIATED}.{$_group_prefix}.{$_graphite_key} {$_graphite_val} {$_graphite_timestamp}\r\n";
			socket_write($sock, $msg, strlen($msg));
		}
	}



	function add_descriptions($_arr){

		$_arr_explain_iostat = array(
			"user" => "Show the percentage of CPU utilization that occurred while executing at the user level (application)."
			,"nice" => "Show the percentage of CPU utilization that occurred while executing at the user level with nice priority."
			,"system" => "Show the percentage of CPU utilization that occurred while executing at the system level (kernel)."
			,"iowait" => "Show the percentage of time that the CPU or CPUs were idle during which the system had an outstanding disk I/O request."
			,"steal" => "Show the percentage of time spent in involuntary wait by the virtual CPU or CPUs while the hypervisor was servicing another virtual processor."
			,"idle" => "Show the percentage of time that the CPU or CPUs were idle and the system did not have an outstanding disk I/O request."
			,"tps" => 		"Indicate the number of transfers per second that were issued to the device."
			,"Blk_read-s" => 		"Indicate the amount of data read from the device expressed in a number of blocks per second."
			,"Blk_wrtn-s" => 		"Indicate the amount of data written to the device expressed in a number of blocks per second."
			,"Blk_read" => 		"The total number of blocks read."
			,"Blk_wrtn" => 		"The total number of blocks written."
			,"kB_read-s" => 		"Indicate the amount of data read from the device expressed in kilobytes per second."
			,"kB_wrtn-s" => 		"Indicate the amount of data written to the device expressed in kilobytes per second."
			,"kB_read" => 		"The total number of kilobytes read."
			,"kB_wrtn" => 		"The total number of kilobytes written."
			,"MB_read-s" => 		"Indicate the amount of data read from the device expressed in megabytes per second."
			,"MB_wrtn-s" => 		"Indicate the amount of data written to the device expressed in megabytes per second."
			,"MB_read" => 		"The total number of megabytes read."
			,"MB_wrtn" => 		"The total number of megabytes written."
			,"rrqm-s" => 		"The number of read requests merged per second that were queued to the device."
			,"wrqm-s" => 		"The number of write requests merged per second that were queued to the device."
			,"r-s" => 		"The number of read requests that were issued to the device per second."
			,"w-s" => 		"The number of write requests that were issued to the device per second."
			,"rsec-s" => 		"The number of sectors read from the device per second."
			,"wsec-s" => 		"The number of sectors written to the device per second."
			,"rkB-s" => 		"The number of kilobytes read from the device per second."
			,"wkB-s" => 		"The number of kilobytes written to the device per second."
			,"rMB-s" => 		"The number of megabytes read from the device per second."
			,"wMB-s" => 		"The number of megabytes written to the device per second."
			,"avgrq-sz" => 		"The average size (in sectors) of the requests that were issued to the device."
			,"avgqu-sz" => 		"The average queue length of the requests that were issued to the device."
			,"await" => 		"The average time (in milliseconds) for I-O requests issued to the device to be served. This includes the time spent by the requests in queue and the time spent servicing them."
			,"svctm" => 		"The average service time (in milliseconds) for I-O requests that were issued to the device."
			,"util" => 		"Percentage of CPU time during which I-O requests were issued to the device (bandwidth utilization for the device). Device saturation occurs when this value is close to 100%."
		);



		require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require
		$_arr_ret = array();

		foreach($_arr as $_unused => $_arr_lp){
			$_val = $_arr_lp[0];

			$_key_1 = $_arr_lp[1];
			$_arr_tokens = split( "-", $_key_1 );
			$_arr_tokens = array_slice($_arr_tokens,1,sizeof($_arr_tokens));
			$_key_2 = implode("-", $_arr_tokens);

			#vd( "$_key_1 - $_key_2 - " .  $_arr_explain_iostat[$_key_1] . " - " .   $_arr_explain_iostat[$_key_2]   );  


			if( strlen($_arr_explain_iostat[$_key_1]) > strlen($_arr_explain_iostat[$_key_2]) ){
				$_arr_lp[2] = $_arr_explain_iostat[$_key_1];
			} else {
				$_arr_lp[2] = $_arr_explain_iostat[$_key_2];
			}

			
			$_arr_ret[] = $_arr_lp;
		}

		return $_arr_ret;

	}






	function split_by( $_arg_str, $_regex_delim='(\n|\r)+' ){
		#$_arr_lines = preg_split('/'.$_regex_delim.'/i', $_arg_str, 1000 , PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$_arr_lines = preg_split('/'.$_regex_delim.'/i', $_arg_str, 1000 , PREG_SPLIT_NO_EMPTY );
		$_arr_lines = trim_empty_array_values( $_arr_lines  );
		return $_arr_lines;
	}


	function parse_string_as_value_field( $_arg_arr ){
		$_arr_ret = array();
		foreach($_arg_arr as $_unused => $_lp_str){
			#$_arr_lp = preg_split('/($[0-9,\.]+)(\s)+/i', $_lp_str, 3 , PREG_SPLIT_NO_EMPTY );
			$_arr_lp = preg_split('/(\s)+/i', $_lp_str, 2 , PREG_SPLIT_NO_EMPTY );
			$_arr_lp[0] = str_replace(",",".",$_arr_lp[0]);
			$_arr_lp[1] = replace_non_ascii($_arr_lp[1],'-',true);
			$_arr_ret[] = $_arr_lp;

		}
		return $_arr_ret;
	}


	function parse_table_as_value_field( $_arg_arr, $_prefix_col=0 ){
		$_arr_ret = array();
		$_arr_headers =  $_arg_arr[0];
		$_arr_headers =  preg_split('/(\s)+/i', $_arr_headers, 1000 , PREG_SPLIT_NO_EMPTY );
		foreach( $_arr_headers as $_key => $_val ){
			$_arr_headers[$_key] = replace_non_ascii($_val ,'-',true);
		}


		$_arr_values  =  array_slice($_arg_arr, 1, sizeof($_arg_arr) - 1);
		foreach($_arr_values as $_unused => $_lp_str){

			$_arr_lp = preg_split('/(\s)+/i', $_lp_str, 1000 , PREG_SPLIT_NO_EMPTY );


			$_lp_key_prefix = "";
			if( $_prefix_col ){
				$_lp_key_prefix = $_arr_lp[ $_prefix_col-1 ] . '-' ;
			}

			foreach($_arr_lp as $_key => $_val){
				if( $_key < $_prefix_col ) continue;
				$_val = str_replace(",",".",$_val);
				$_arr_ret[] = array($_val,$_lp_key_prefix . $_arr_headers[$_key]);
			}

		}




		return $_arr_ret;
	}



	function trim_empty_array_values( $_arr_arg ){
		$_arr_ret = array();
		foreach( $_arr_arg as $_unused => $_lp_line ){
			if( strlen(trim($_lp_line)) ){
				#$_arr_ret[] = ($_lp_line);
				$_arr_ret[] = trim($_lp_line);
			}
		}
		return $_arr_ret ;
	}



	function render_innodb_status( $_period = 0){

		$_q_status = " SHOW ENGINE INNODB STATUS ";

		if( $_period AND $_period < 20 ){
			$_arr_status_1 = execute_query_get_first( $_q_status );
			sleep($_period);
		}

		$_arr_status_1 = execute_query_get_first( $_q_status );

		$_str_status_1 = $_arr_status_1['Status']; 
		$_str_status_2 =  explode("\n",$_str_status_1);

		#vd( $_str_status_2 );

		$_arr_match1 = preg_grep( '~inserts/s~', $_str_status_2 );
		$_arr_match1 = split_by( reset($_arr_match1),',');

		$_arr_match2 = preg_grep( '~queries in queue~', $_str_status_2 );
		$_arr_match2 = split_by( reset($_arr_match2),',');


 		$_arr_match3 = preg_grep( '~Buffer pool hit rate~', $_str_status_2 );
		$_arr_match3a = array();
		foreach( $_arr_match3 as $_unused => $_lp_line ){
			$_arr_lp = split_by( $_lp_line,',');
			$_str_of_interest = $_arr_lp[0];
			$_str_of_interest = str_ireplace("Buffer pool hit rate","", $_str_of_interest);
			$_arr_match3a[] = $_str_of_interest;
		}
		$_arr_match3b = array();
		foreach( $_arr_match3a as $_cntr => $_lp_line ){
			$_arr_lp = split_by( $_lp_line,'\/');
			$_arr_match3b['buffer_hit_ratio_'.$_cntr] = round( $_arr_lp[0] / $_arr_lp[1] * 100);
		}
		if( sizeof(  $_arr_match3b ) ){
			$_avg = array_sum( $_arr_match3b )  / sizeof(  $_arr_match3b ) ;
			$_avg = round( $_avg , 1 );

		} else {
			$_avg = 1.02;
		}


		$_arr_match1 = parse_string_as_value_field($_arr_match1);
		$_arr_match2 = parse_string_as_value_field($_arr_match2);
		$_arr_match3 = parse_string_as_value_field( array("$_avg % buffer hit rate") );

		return  array_merge( $_arr_match1,$_arr_match2,$_arr_match3 )  ;

	}



	function remote_command_via_ssh($_arg_cmd='iostat -x 2 1 ', $_wait_in_secs = 3){

		#$_arr_1 = execute_query("system iostat 1 2");		# this is impossible from remote

		require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require
		$_user = $_arr_hosts_detail[$_HOST]['user'];
		$_pw   = $_arr_hosts_detail[$_HOST]['password'];
		$_pw_dis = substr($_pw,0,1). str_repeat("x", strlen($_pw)-2 ).substr($_pw,-1);

		vd("about to log in -$_HOST- -$_HOST_ABBREVIATED- -$_user-  -$_pw_dis- ");


		$_unused_callbacks = array('disconnect' => 'my_ssh_disconnect');
		#$_ssh_connection = ssh2_connect($_HOST, 22, $methods, $_unused_callbacks);
		$_ssh_connection = ssh2_connect($_HOST_ABBREVIATED, 22);

		$_path_pub  = "/home/$_user/.ssh/id_rsa.pub";
		$_path_priv = "/home/$_user/.ssh/id_rsa.priv";

		$_key_pub  = file_get_contents($_path_pub);
		$_key_priv = file_get_contents($_path_priv);


		if( ssh2_auth_pubkey_file( $_ssh_connection , $_user
				, $_path_pub
				, $_path_priv
				, ""
			)
		){
			#echo "Public Key Authentication Successful\n";
		} else {
			die('Public Key Authentication Failed');
		}

		#ssh2_auth_password($_ssh_connection, $_user , $_pw );
		$_stream = ssh2_exec($_ssh_connection, $_arg_cmd);

		stream_set_blocking($_stream, false);

		sleep( $_wait_in_secs );

		$_str_ret = stream_get_contents( $_stream ) . " \n";
		fclose($_stream);

		@ssh2_exec($_ssh_connection, 'echo "EXITING" && exit;'); 


		return $_str_ret;


	}



?>