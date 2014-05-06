<?php

# bp-2013-02-27

# wrapper around percona_ss_get_mysql_stats_grahite.php:ss_get_mysql_stats() from percona monitoring
# percona_ss_get_mysql_stats_grahite.php  is an adapted version from ss_get_mysql_stats.php 

# 1.) cache_dir is set to $cache_dir  = '/tmp/monitoring_files'; 
#	full write access to this dir is ensured
# 2.) ss_get_mysql_stats() should return $output instead of retunrn $result

# the script iterates over all mysql database hosts, as configured in http://dbadmin.ipx/crawler-admin ...
#	config.inc.php - $cfg['Servers']

# the mysql database hosts should be pulled from puppet in future


# script filters hosts by hostname s[x0-9] and calls ss_get_mysql_stats_grahite.php 
# for each of these

# because some mysql services cause errors, we have to implement a custom error handler
# which throws a custom exception. This way we can catch such errors, prevent the script
# from terminating, and instead continue with the NEXT host in loop


# ss_get_mysql_stats_grahite connects to remote mysql service and reads mysqld variables
# there is surprisingly little performance impact - 30 hosts in one second

# the resulting key-values are then submitted to graphite via socket
# there is a "mass export" feature to graphite, but we do not use it

# the transmission to graphite is slowed with usleep(xxx),
# 	'cause otherwise some key-values will be ignored by graphite/wisper/carbon


# for development and testing the script can be called via apache/mod_php and gives graphical output
# for production, the script is put into crontab like THIS
# */10 * * * *     wget --quiet -O /dev/null http://localhost/sql_analytics/percona_mysql_stats_wrapper.php
# */10 10-22 * * * wget --quiet -q --spider  http://localhost/sql_analytics/percona_mysql_stats_wrapper.php
 


	$_GRAPHITE_SERVER = 'r900.ipx';		#http://r900.ipx/graphite


	ini_set('include_path',ini_get('include_path').':./lib/phpseclib');

	$cfg['Servers'] = array();
	if( file_exists('../crawler-admin/config.inc.php') ){
		require_once('../crawler-admin/config.inc.php');
	} else {
		require_once('crawler-admin.inc.php'); # use local copy
	}

	foreach( $cfg['Servers'] as $_key_unused => $_lp_arr){
		$_arr_hostnames[     $_lp_arr['host']  ] = $_lp_arr['host'];
		$_arr_hosts_detail[  $_lp_arr['host']  ] = $_lp_arr;
	}





	# filtering mysql hosts into array
	$_arr_hosts_by_ip = array();
	foreach( $_arr_hostnames as $_key_unused => $_lp_host ){

		$_lp_host = trim( strtolower($_lp_host));
		$_arr_lp_host = explode(".",$_lp_host);
		$_lp_host_key = $_arr_lp_host[0];

		#matching r900, b17, sx33, s122, s333 ...
		if( 	preg_match( "/^[brs][x0-9]{1,1}./i", $_lp_host) ){
			$_arr_hosts_by_ip[$_lp_host_key]  = $_lp_host ;
		} else {
			#
		}
	}

	ksort($_arr_hosts_by_ip);

	#vd($_arr_hosts_by_ip);

	vd("Push mysql specific metrics to graphite");

	echo "requiring script (some strange chars occur) ... ";
	require_once(  dirname(__FILE__) . "/percona_ss_get_mysql_stats_grahite.php" );
	echo " ... require_end <br>\n";

	global $cache_dir;
	$cache_dir  = '/tmp/monitoring_files';


	$port = 2003;
	$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	$sockconnect = socket_connect($sock, $_GRAPHITE_SERVER, $port);
	vd("connected to $_GRAPHITE_SERVER $port - $sockconnect");
	flush_http();


	function error_handler_02( $code, $message, $file, $line, $context ){
		#echo __FUNCTION__ . " $code, $message, $file, $line, <br>\n";
		if( starts_with( $message , "mysql_connect() [<a href='function.mysql-connect'>function.mysql-connect</a>]: Lost connection to MySQL server at 'reading initial communication packet', system error: 11") ){
			echo "skipping due to mysql connection problem...<br>\n";
			throw new Exception ( "ude: error in mysql connection" );
			return true; # => skip default error handler
		} else {
			return false; # => go back to the default error handler
		}
	}
	set_error_handler( 'error_handler_02' , E_ALL);		# not E_WARNING, not E_NOTICE, not E_ALL, unnessary on E_PARSE

	
	$_cntr = 0;
	foreach( $_arr_hosts_by_ip as $_lp_abbr_host_key => $_lp_host ){
	

		#if( $_lp_abbr_host_key != 's143' ) continue;

		$_cntr++;

		$options = array( 
		  "host"  => $_lp_host
		, "items" => "g0,g1"
		, "user"  => "idealo"
		, "pass"  => "s3cr3t"
		) ;
		
		global $chk_options;
		$chk_options = array (
			'innodb'  => true,    # Do you want to check InnoDB statistics?
			'master'  => true,    # Do you want to check binary logging?
			'slave'   => true,    # Do you want to check slave status?
			'procs'   => true,    # Do you want to check SHOW PROCESSLIST?
			'get_qrt' => true,    # Get query response times from Percona Server?
		);


		validate_options($options);
		vd("checking $_lp_abbr_host_key");


		try {
			$result = ss_get_mysql_stats($options);
		} catch( Exception $e ){
			echo "&nbsp; &nbsp; &nbsp; exception occurred: ".$e->getMessage() . "<br>\n";
			echo "&nbsp; &nbsp; &nbsp; Continue with next horst...<br>\n";
			continue;
		}

		

		$_res1 = array();
		foreach( $result as $k1 => $v1 ){
			$_arr_lp1 = explode(":",$v1);
			#vd(" $k1 - $_arr_lp1[0]  $_arr_lp1[1] " );
			if( 
							intval( $_arr_lp1[1] ) == -1 
					OR		intval( $_arr_lp1[1] ) === 0 

			){
				# skip
			} else {
				$_res1[ $_arr_lp1[0] ] = $_arr_lp1[1];
			} 
		}

		$_res2 = expand_keys($_res1) ;


		global $_hierarchify_operator;
		$_hierarchify_operator = "_";

		#$_res2 = hierarchify_array($_res2 );


		#ksort($_res2);
		$_count_keys = sizeof($_res2);
		vd("  sending $_count_keys keys to graphite... ");

		$_graphite_timestamp = mktime();
		write_graphite_msg_2($_lp_abbr_host_key,$sock,$_graphite_timestamp,$_res2);


		flush_http();
	
		#if( $_cntr > 4 ) break;		# limt for development

	}

	vd("done");
	flush_http();


	socket_close($sock);

	




	# re-translate the output from percona script into descriptive keys
	function expand_keys($_res){

		# this array is from the percona script
		# here we need it for reverse translation of the short keys to the descriptive keys
		$_keys_description = array(
			'Key_read_requests'           =>  'g0',
			'Key_reads'                   =>  'g1',
			'Key_write_requests'          =>  'g2',
			'Key_writes'                  =>  'g3',
			'history_list'                =>  'g4',
			'innodb_transactions'         =>  'g5',
			'read_views'                  =>  'g6',
			'current_transactions'        =>  'g7',
			'locked_transactions'         =>  'g8',
			'active_transactions'         =>  'g9',
			'pool_size'                   =>  'ga',
			'free_pages'                  =>  'gb',
			'database_pages'              =>  'gc',
			'modified_pages'              =>  'gd',
			'pages_read'                  =>  'ge',
			'pages_created'               =>  'gf',
			'pages_written'               =>  'gg',
			'file_fsyncs'                 =>  'gh',
			'file_reads'                  =>  'gi',
			'file_writes'                 =>  'gj',
			'log_writes'                  =>  'gk',
			'pending_aio_log_ios'         =>  'gl',
			'pending_aio_sync_ios'        =>  'gm',
			'pending_buf_pool_flushes'    =>  'gn',
			'pending_chkp_writes'         =>  'go',
			'pending_ibuf_aio_reads'      =>  'gp',
			'pending_log_flushes'         =>  'gq',
			'pending_log_writes'          =>  'gr',
			'pending_normal_aio_reads'    =>  'gs',
			'pending_normal_aio_writes'   =>  'gt',
			'ibuf_inserts'                =>  'gu',
			'ibuf_merged'                 =>  'gv',
			'ibuf_merges'                 =>  'gw',
			'spin_waits'                  =>  'gx',
			'spin_rounds'                 =>  'gy',
			'os_waits'                    =>  'gz',
			'rows_inserted'               =>  'h0',
			'rows_updated'                =>  'h1',
			'rows_deleted'                =>  'h2',
			'rows_read'                   =>  'h3',
			'Table_locks_waited'          =>  'h4',
			'Table_locks_immediate'       =>  'h5',
			'Slow_queries'                =>  'h6',
			'Open_files'                  =>  'h7',
			'Open_tables'                 =>  'h8',
			'Opened_tables'               =>  'h9',
			'innodb_open_files'           =>  'ha',
			'open_files_limit'            =>  'hb',
			'table_cache'                 =>  'hc',
			'Aborted_clients'             =>  'hd',
			'Aborted_connects'            =>  'he',
			'Max_used_connections'        =>  'hf',
			'Slow_launch_threads'         =>  'hg',
			'Threads_cached'              =>  'hh',
			'Threads_connected'           =>  'hi',
			'Threads_created'             =>  'hj',
			'Threads_running'             =>  'hk',
			'max_connections'             =>  'hl',
			'thread_cache_size'           =>  'hm',
			'Connections'                 =>  'hn',
			'slave_running'               =>  'ho',
			'slave_stopped'               =>  'hp',
			'Slave_retried_transactions'  =>  'hq',
			'slave_lag'                   =>  'hr',
			'Slave_open_temp_tables'      =>  'hs',
			'Qcache_free_blocks'          =>  'ht',
			'Qcache_free_memory'          =>  'hu',
			'Qcache_hits'                 =>  'hv',
			'Qcache_inserts'              =>  'hw',
			'Qcache_lowmem_prunes'        =>  'hx',
			'Qcache_not_cached'           =>  'hy',
			'Qcache_queries_in_cache'     =>  'hz',
			'Qcache_total_blocks'         =>  'i0',
			'query_cache_size'            =>  'i1',
			'Questions'                   =>  'i2',
			'Com_update'                  =>  'i3',
			'Com_insert'                  =>  'i4',
			'Com_select'                  =>  'i5',
			'Com_delete'                  =>  'i6',
			'Com_replace'                 =>  'i7',
			'Com_load'                    =>  'i8',
			'Com_update_multi'            =>  'i9',
			'Com_insert_select'           =>  'ia',
			'Com_delete_multi'            =>  'ib',
			'Com_replace_select'          =>  'ic',
			'Select_full_join'            =>  'id',
			'Select_full_range_join'      =>  'ie',
			'Select_range'                =>  'if',
			'Select_range_check'          =>  'ig',
			'Select_scan'                 =>  'ih',
			'Sort_merge_passes'           =>  'ii',
			'Sort_range'                  =>  'ij',
			'Sort_rows'                   =>  'ik',
			'Sort_scan'                   =>  'il',
			'Created_tmp_tables'          =>  'im',
			'Created_tmp_disk_tables'     =>  'in',
			'Created_tmp_files'           =>  'io',
			'Bytes_sent'                  =>  'ip',
			'Bytes_received'              =>  'iq',
			'innodb_log_buffer_size'      =>  'ir',
			'unflushed_log'               =>  'is',
			'log_bytes_flushed'           =>  'it',
			'log_bytes_written'           =>  'iu',
			'relay_log_space'             =>  'iv',
			'binlog_cache_size'           =>  'iw',
			'Binlog_cache_disk_use'       =>  'ix',
			'Binlog_cache_use'            =>  'iy',
			'binary_log_space'            =>  'iz',
			'innodb_locked_tables'        =>  'j0',
			'innodb_lock_structs'         =>  'j1',
			'State_closing_tables'        =>  'j2',
			'State_copying_to_tmp_table'  =>  'j3',
			'State_end'                   =>  'j4',
			'State_freeing_items'         =>  'j5',
			'State_init'                  =>  'j6',
			'State_locked'                =>  'j7',
			'State_login'                 =>  'j8',
			'State_preparing'             =>  'j9',
			'State_reading_from_net'      =>  'ja',
			'State_sending_data'          =>  'jb',
			'State_sorting_result'        =>  'jc',
			'State_statistics'            =>  'jd',
			'State_updating'              =>  'je',
			'State_writing_to_net'        =>  'jf',
			'State_none'                  =>  'jg',
			'State_other'                 =>  'jh',
			'Handler_commit'              =>  'ji',
			'Handler_delete'              =>  'jj',
			'Handler_discover'            =>  'jk',
			'Handler_prepare'             =>  'jl',
			'Handler_read_first'          =>  'jm',
			'Handler_read_key'            =>  'jn',
			'Handler_read_next'           =>  'jo',
			'Handler_read_prev'           =>  'jp',
			'Handler_read_rnd'            =>  'jq',
			'Handler_read_rnd_next'       =>  'jr',
			'Handler_rollback'            =>  'js',
			'Handler_savepoint'           =>  'jt',
			'Handler_savepoint_rollback'  =>  'ju',
			'Handler_update'              =>  'jv',
			'Handler_write'               =>  'jw',
			'innodb_tables_in_use'        =>  'jx',
			'innodb_lock_wait_secs'       =>  'jy',
			'hash_index_cells_total'      =>  'jz',
			'hash_index_cells_used'       =>  'k0',
			'total_mem_alloc'             =>  'k1',
			'additional_pool_alloc'       =>  'k2',
			'uncheckpointed_bytes'        =>  'k3',
			'ibuf_used_cells'             =>  'k4',
			'ibuf_free_cells'             =>  'k5',
			'ibuf_cell_count'             =>  'k6',
			'adaptive_hash_memory'        =>  'k7',
			'page_hash_memory'            =>  'k8',
			'dictionary_cache_memory'     =>  'k9',
			'file_system_memory'          =>  'ka',
			'lock_system_memory'          =>  'kb',
			'recovery_system_memory'      =>  'kc',
			'thread_hash_memory'          =>  'kd',
			'innodb_sem_waits'            =>  'ke',
			'innodb_sem_wait_time_ms'     =>  'kf',
			'Key_buf_bytes_unflushed'     =>  'kg',
			'Key_buf_bytes_used'          =>  'kh',
			'key_buffer_size'             =>  'ki',
			'Innodb_row_lock_time'        =>  'kj',
			'Innodb_row_lock_waits'       =>  'kk',
			'Query_time_count_00'         =>  'kl',
			'Query_time_count_01'         =>  'km',
			'Query_time_count_02'         =>  'kn',
			'Query_time_count_03'         =>  'ko',
			'Query_time_count_04'         =>  'kp',
			'Query_time_count_05'         =>  'kq',
			'Query_time_count_06'         =>  'kr',
			'Query_time_count_07'         =>  'ks',
			'Query_time_count_08'         =>  'kt',
			'Query_time_count_09'         =>  'ku',
			'Query_time_count_10'         =>  'kv',
			'Query_time_count_11'         =>  'kw',
			'Query_time_count_12'         =>  'kx',
			'Query_time_count_13'         =>  'ky',
			'Query_time_total_00'         =>  'kz',
			'Query_time_total_01'         =>  'la',
			'Query_time_total_02'         =>  'lb',
			'Query_time_total_03'         =>  'lc',
			'Query_time_total_04'         =>  'ld',
			'Query_time_total_05'         =>  'le',
			'Query_time_total_06'         =>  'lf',
			'Query_time_total_07'         =>  'lg',
			'Query_time_total_08'         =>  'lh',
			'Query_time_total_09'         =>  'li',
			'Query_time_total_10'         =>  'lj',
			'Query_time_total_11'         =>  'lk',
			'Query_time_total_12'         =>  'll',
			'Query_time_total_13'         =>  'lm',
		);



		$_keys_description  = array_flip( $_keys_description );


		# now we do some custom adaptation

		$_keys_description['h9'] ='open_tables_opened';

		$_keys_description['hd'] ='connections_connects_aborted';
		$_keys_description['he'] ='connections_clients_aborted';

		$_keys_description['hf'] ='connections_max_used';
		$_keys_description['hl'] ='connections_max';
		$_keys_description['hn'] ='connections_connections';


		$_keys_description['hg'] ='threads_slow_launch';
		$_keys_description['hm'] ='threads_cache_size';


		$_keys_description['kg'] ='myisam_Keybuffer_bytes_unflushed';
		$_keys_description['kh'] ='myisam_Keybuffer_bytes_used';
		$_keys_description['ki'] ='myisam_Keybuffer_size';

	

		$_arr_ret = array();
		foreach($_res as $_key => $_val){
			$_long_key = $_keys_description[$_key];
			if( isset($_long_key)  ){
				$_long_key = strtolower($_long_key);
				$_arr_ret[ $_long_key ] = $_val;
			} else {
				$_arr_ret[ $_key ] = $_val;
			}

		}

		return $_arr_ret;
	

	}

	# comfort wrapper to write a single key-value to graphite
	# opening and closing the socket must be done outside of function 
	function write_graphite_msg_2($_host,$sock,$_graphite_timestamp,$_arr){

		$_graphite_prefix = "server.mysql";


		foreach($_arr as $_key_desc => $_val){
			$_graphite_key = $_key_desc;
			$_graphite_val = $_val;

			#vd($msg);
			$msg = "{$_graphite_prefix}.{$_host}.{$_group_prefix}.{$_graphite_key} {$_graphite_val} {$_graphite_timestamp}\r\n";
			socket_write($sock, $msg, strlen($msg));
			usleep(750);			# did not suffice, some key values were still dropped
			usleep(200000);		# 0.2 secs - all keys arrive at graphite
			usleep(200000);		# 0.2 secs - all keys arrive at graphite
		}
	}



	# common helpers to make script dependency free
	function vd( $_arr , $_prefix="") {
		echo vd1($_arr,$_prefix);
	}

	function vd1( $_arr, $_prefix="" ) {

		$_prefix1 = '';	# dummy


		if( $_prefix ) $_prefix1= "<span style='text-align:left;line-height:10px;background-color:#fff;
			display:inline-block;margin-bottom:-15px;'><b>{$_prefix}:</b></span>";
		if( $_prefix ) $_prefix1= "{$_prefix}: ";

		if( is_array($_arr) AND sizeof($_arr)== 0 ){
			$s = "EMPTY ARRAY";
		} else {
			$s = print_r($_arr,1);
			$s = htmlspecialchars($s);
		}

		return "<pre style='text-align:left;background-color:#fff;color:#000;width:90%'
			>{$_prefix1}$s</pre>\n";
	}


	function flush_http( $_msg="" ){

		if( $_msg) vd($_msg);
		echo "<div style=' display:block; width: 2px; height: 2px; overflow:hidden; ' >";
		echo str_repeat("x &nbsp; ",2000);
		echo "</div>";
		flush();

	}


	# case insensitive startswith function
	#	implemented here, cause we need it soon
	
	#	IF haystack STARTSWITH needle
	#	IF koyaanisquatsi STARTSWITH ko
	function starts_with($Haystack, $Needle){
	
		$Needle	 = strtolower($Needle);
		$Haystack = strtolower($Haystack);
		
		# Recommended version, using strpos
		if( ! $Needle  ){
			#vd(" ! needle   $Haystack - $Needle");
			#print_error("needle is empty - ",array("with_stack_trace"=>1));
		}   
		return strpos($Haystack, $Needle) === 0;
		
		
		// Another way, using substr
		return substr($Haystack, 0, strlen($Needle)) == $Needle;
	}





?>
