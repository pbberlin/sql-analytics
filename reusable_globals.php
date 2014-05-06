<?php


	global $PUB_DB_HOST ;
	global $DB_USER ;
	global $DB_PASSWORD ;


	global $PUB_DB_NAME ;

	global $CONNECTION_CHARSET ;


	global $DB_CONNECTION;
	global $MYSQLI;

	global $_HOST, $_HOST_ABBREVIATED;
	global $_MYSQL_THREAD_ID;

	$_HOST = get_param("hostname");
	$_HOST_ABBREVIATED = $_HOST;
	if( substr($_HOST_ABBREVIATED,-4) == '.ipx' ) $_HOST_ABBREVIATED = substr($_HOST_ABBREVIATED,0,-4);


	$PUB_DB_HOST = "offer-db.ipx:/var/lib/mysql/mysql.sock";
		$PUB_DB_HOST_MYSQLI = "offer-db.ipx";
	$PUB_DB_HOST = $_HOST;

	$DB_USER = "idealo";
	$DB_PASSWORD = "s3cr3t";

	$PUB_DB_NAME = "crawler";
	$PUB_DB_NAME = "";
	$CONNECTION_CHARSET = "utf8";
	$CONNECTION_CHARSET = "latin1";

	global $_GRAPHITE_HOST;
	$_GRAPHITE_HOST = "r900.ipx";

		
	#date_default_timezone_set('America/Los_Angeles');
	date_default_timezone_set('Europe/Berlin');


	global $_LANGUAGES;
	$_LANGUAGES_EDITABLE = 3;
	$_language_code = "de";


	global $_DOC_ROOT, $_DOC_NTS_ROOT; # for if this file is included not from auto_prepend

	$_DOC_ROOT = ( dirname(__FILE__) );
	if( substr($_DOC_ROOT,-1) == DIRECTORY_SEPARATOR ){
		$_DOC_NTS_ROOT = $_DOC_ROOT;
		$_DOC_ROOT = substr($_DOC_ROOT,0,-1);
	} else {
		$_DOC_NTS_ROOT = $_DOC_ROOT . DIRECTORY_SEPARATOR;
	}
	# vd("$_DOC_ROOT $_DOC_NTS_ROOT" ,"docroot,docntsroot");

	global $_hierarchify_operator;
	$_hierarchify_operator = $_flatten_operator = '__';



	global $_int_today;
	$_int_today = strtotime( date("Y-m-d") );
	$_int_now   = strtotime("now");
	$_int_2038  = 2147483647;


	global $_init;


	global $_arr_hosts_detail;
	global $_arr_hostnames;


	if( isset($_init)  AND  $_init==true  ){
		# init work alread done
	} else {
		$_init = true;

		ini_set('include_path',ini_get('include_path').':./lib/phpseclib');
		#vd( ini_get('include_path') );
		#include('Net/SSH2.php');
		#include('Crypt/RSA.php');

		$cfg['Servers'] = array();
		if( file_exists('../crawler/config.inc.php') ){
			require_once('../crawler/config.inc.php');
		} else {
			require_once('crawler.inc.php');
		}



		foreach( $cfg['Servers'] as $_key_unused => $_lp_arr){
			$_str_tmp = trim( strtolower( $_lp_arr['host'] ));
			if( $_str_tmp == '172.30.208.134' ){
				$_lp_host_key = $_str_tmp;
			}else{
				$_arr_tmp = explode(".",$_str_tmp);
				$_lp_host_key = $_arr_tmp[0];
			}
				
			$_arr_hostnames_functional[     $_lp_host_key  ] = $_lp_arr['host'];
			$_arr_hosts_detail_functional[  $_lp_host_key  ] = $_lp_arr;
		}

		$cfg['Servers'] = array();
		if( file_exists('../crawler-admin/config.inc.php') ){
			require_once('../crawler-admin/config.inc.php');
		} else {
			require_once('crawler-admin.inc.php');
		}

		foreach( $cfg['Servers'] as $_key_unused => $_lp_arr){
			$_str_tmp = trim( strtolower( $_lp_arr['host'] ));

			if( $_str_tmp == '172.30.208.134' ){
				$_lp_host_key = $_str_tmp;
			}else{
				$_arr_tmp = explode(".",$_str_tmp);
				$_lp_host_key = $_arr_tmp[0];
			}
			#vd($_lp_host_key);

			$_arr_hostnames_srv_name[     $_lp_host_key  ] = $_lp_arr['host'];
			$_arr_hosts_detail_srv_name[  $_lp_host_key  ] = $_lp_arr;
		}


		# add puppet hosts
		$_arr_puppet_hosts = get_puppet_hosts_by_role();
		foreach( $_arr_puppet_hosts as $_unused => $_lp_arr){
			$_str_tmp = trim( strtolower( $_lp_arr['hostname'] ));
			
			if( substr($_str_tmp,-4) == ".ipx" ) {
				$_str_tmp_abbr = substr($_str_tmp,0,-4);
			}

			if( ! $_arr_hostnames_srv_name[$_str_tmp_abbr] ){
				#vd("added via puppet: $_str_tmp");
				$_arr_hostnames_srv_name[     $_str_tmp_abbr  ] = $_str_tmp;
				$_arr_hosts_detail_srv_name[  $_str_tmp_abbr  ] = $_lp_arr;

			}
				
		}
		ksort($_arr_hostnames_srv_name);
		ksort($_arr_hosts_detail_srv_name);

		$_arr_hostnames    = $_arr_hostnames_functional    + $_arr_hostnames_srv_name;
		$_arr_hosts_detail = $_arr_hosts_detail_functional + $_arr_hosts_detail_srv_name;
	}

	#vd($_arr_hostnames);
	#vd($_arr_hosts_detail);


	render_elapsed_time_since("start");


?>