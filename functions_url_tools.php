<?php






# following params are should be filtered out in the next request
function get_arr_transient_params(){
	
	# attention - 
	#		parameter h is used for hash (password retrieval), 
	#		parameter n is used for landlord number oder customer number
	#			both should NOT be made transient
	$_arr_transient_params = array(
		  "x"
		, "y"
		, "fld_noscript"
		, "_batch_no"
		, "submit"
		, "submit_*"			# all keys starting with submit
		, "fld_login_form"
		, "username"
		, "password"
		, "_password"
		, "pw1_*"				# password change für ll und tt
		, "pw2_*"
		, "mail_key"
		, "mail_key_cust"
		, "_"
		
		#, "ort_mandated"

	);
	
	return $_arr_transient_params;

}






# same as getHiddenInputs, but as URL GET parameters
#	EMPTY VALUES are skipped
function getLinkAnhang($del = false ){

	$del = standardize_to_array($del);


	$_arr_transient_params_equals	 = get_arr_transient_params();
	if( in_array("keep_transient_params",$del) ) $_arr_transient_params_equals = array();
	$_arr_transient_params_startswith = get_array_subset_trailing_asterisk( $_arr_transient_params_equals );
	

	$link_anhang = "";


	foreach( $_GET as $key => $value ){
		#vd("$key - $value");
		if(  ! in_array($key,$del)   AND ! in_array($key,$_arr_transient_params_equals)  ){

			foreach( $_arr_transient_params_startswith as $_key_unused => $_lp_startswith ){
				if( starts_with($key, $_lp_startswith) ) continue 2;
			}

			if( is_array($value) ){ 
				foreach($value as $_key_inner => $_lp_val){
					if( is_numeric($_key_inner)) $_key_inner = "";
					$link_anhang .= "{$key}[{$_key_inner}]={$_lp_val}&";
				}
			} else {
				if( $value ){		#	EMPTY VALUES are skipped
					$value = urlencode($value);	
					$value = addslashes($value);
					$link_anhang .= "$key=".$value."&";				
				}
			}
		} else {
			#vd("$key -$key- not in hiddenInput/LinkAnhang");
		}
	}		

	foreach( $_POST as $key => $value ){
		if(  ! in_array($key,$del)   AND ! in_array($key,$_arr_transient_params_equals)  ){

			foreach( $_arr_transient_params_startswith as $_key_unused => $_lp_startswith ){
				if( starts_with($key, $_lp_startswith) ) continue 2;
			}

			if( is_array($value) ){ 
				foreach($value as $_key_inner => $_lp_val){
					if( is_numeric($_key_inner)) $_key_inner = "";
					$link_anhang .= "{$key}[{$_key_inner}]={$_lp_val}&";
				}
			} else {
				if( $value ){		#	EMPTY VALUES are skipped

					$value = urlencode($value);	
					$value = addslashes($value);
					$link_anhang .= "$key=".$value."&";				
				}
			}

		} else {
			#vd("$key -$key- not in hiddenInput/LinkAnhang");
		}
	}
	
	return $link_anhang;
}





# same as getLinkAnhang, but as hidden form fields
#	EMPTY VALUES are skipped
function getHiddenInputs($del = array()) {
		
		
	$_arr_transient_params_equals = get_arr_transient_params();
	if( in_array("keep_transient_params",$del) ) $_arr_transient_params_equals = array();
	$_arr_transient_params_startswith = get_array_subset_trailing_asterisk( $_arr_transient_params_equals );

	
	$link_anhang = "";

	foreach( $_GET as $key => $value ){
		if(  ! in_array($key,$del)   AND ! in_array($key,$_arr_transient_params_equals)  ){
			
			foreach( $_arr_transient_params_startswith as $_key_unused => $_lp_startswith ){
				if( starts_with($key, $_lp_startswith) ) continue 2;
			}

			$_attr_id = "";
			#$_attr_id = " id='id_fld_{$key}' ";

			if( is_array($value) ){ 
				foreach($value as $_key_inner => $_lp_val){
					if( is_numeric($_key_inner)) $_key_inner = "";
					$link_anhang .= "<input $_attr_id type='hidden' name='{$key}[{$_key_inner}]' value='$_lp_val' >\n";
				}
			} else {
				if( $value ){		#	EMPTY VALUES are skipped

					$value = urlencode($value);	
					$value = addslashes($value);
					$_attr_id = "";
					$link_anhang .= "<input $_attr_id type='hidden' name='$key' value='$value' >\n";
				}
			}
			
		} else {
			#vd("$key -$key- not in hiddenInput/LinkAnhang");
		}
	}


	foreach( $_POST as $key => $value ){
		if(  ! in_array($key,$del)   AND ! in_array($key,$_arr_transient_params_equals)  ){

			foreach( $_arr_transient_params_startswith as $_key_unused => $_lp_startswith ){
				if( starts_with($key, $_lp_startswith) ){
					#vd("skipping $key $_lp_startswith");
					continue 2;
				}
			}

			#$_attr_id = " id='id_fld_{$key}' ";

			if( is_array($value) ){ 
				foreach($value as $_key_inner => $_lp_val){
					if( is_numeric($_key_inner)) $_key_inner = "";
					$link_anhang .= "<input $_attr_id type='hidden' name='{$key}[{$_key_inner}]' value='$_lp_val' >\n";
				}
			} else {
				if( $value ){		#	EMPTY VALUES are skipped

					$value = urlencode($value);	
					$value = addslashes($value);
					$_attr_id = "";
					$link_anhang .= "<input $_attr_id type='hidden' name='$key' value='$value' >\n";
				}
			}

		} else {
			#vd("$key -$key- not in hiddenInput/LinkAnhang");
		}
	}

	return $link_anhang;
}









function get_array_subset_trailing_asterisk( $_arr_arg ){
	
	$_arr_return = array();
	foreach( $_arr_arg as $_key_unused => $_lp_val ){
		$_lp_last_char = 	substr($_lp_val,-1);
		if( $_lp_last_char  == '*' ){
			$_arr_return[] = substr($_lp_val,0,-1); 
		}
		
	}
	
	return $_arr_return;
	#array_filter( $_arg_arr, "strtolower" );
	
}


function get_set_session($_arg_sess = ''){
	static $_the_sess;
	if( $_arg_sess ) $_the_sess = $_arg_sess;
	return $_the_sess;
}


/*
	assumes a STARTED session
	
	if REQUEST param does not exist, but session param does exist,
		then it is put into REQUEST
		
	if REQUEST param does exist, it is saved into session
	
	existence is purely defined upon "isset()" - so that a session value may 
	be "deleted" by setting the request param to "".
	

*/
function session_exchange_data_nofrills($session,$_p_name = 'endDate'){

		if( isset($_REQUEST[$_p_name] ) )  $_p_val = $_REQUEST[$_p_name];
		
		# load from session
		if( ! isset($_p_val) AND $session->getValue("sess_{$_p_name}") ){
			$_REQUEST[$_p_name] = $session->getValue("sess_{$_p_name}");	
		}

		
		# save to session
		if( isset($_p_val) ){
			$session->setValue("sess_{$_p_name}"    , $_p_val) ;	# for US
		}
	
}





# ALWAYS appends at least ? - so we may recklessly appedn &a=b
function construct_url($_arg_action, $_arr_options = array() ){

	extract ( expand_options_array($_arr_options ,'', 'id') );

	$_query_string = "?".getLinkAnhang();
	$_query_string = "?";

	global $config;
	$_lower_prefix = strtolower($config['pid_prefix']);
	$_number = "{$_lower_prefix}{$_id}";


	if( $_arg_action == 'result_list'	 ){

		$_query_string = "?";		# irritates the map on result list
	
		if( is_numeric($_id) ){
			return "/not_implemented_action_{$_arg_action}_id_{$_id}";		
		} else {
			return "/unterkunft/{$_id}.html{$_query_string}";					
		}
		
	}

	
	if( $_arg_action == 'apartment_detail'	 ){

		if( is_numeric($_id) ){
			return "/unterkunft/{$_number}.html{$_query_string}";		
		} else {
			return "/not_implemented_action_{$_arg_action}_id_{$_id}";		
		}
		
	}

	if( $_arg_action == 'lage'	 ){

		if( is_numeric($_id) ){
			return "/unterkunft/lage/{$_number}.html{$_query_string}";		
		} else {
			return "/not_implemented_action_{$_arg_action}_id_{$_id}";		
		}
		
	}


	if( $_arg_action == 'belegung'	 ){

		if( is_numeric($_id) ){
			return "/unterkunft/belegung/{$_number}.html{$_query_string}";		
		} else {
			return "/not_implemented_action_{$_arg_action}_id_{$_id}";		
		}
		
	}


	if( $_arg_action == 'apartment_detail_login'	 ){

		if( is_numeric($_id) ){
			return "/unterkunft/login/?m=p&a=e&id={$_id}&p=1&l=10";
		} else {
			return "/not_implemented_action_{$_arg_action}_id_{$_id}";		
		}
		
	}


	if( $_arg_action == 'rating_detail'	 ){

		if( is_numeric($_id) ){
			return "/unterkunft/bewertung/{$_number}.html{$_query_string}";		
		} else {
			return "/not_implemented_action_{$_arg_action}_id_{$_id}";		
		}
		
	}

	
}





function get_current_url(){
	$pageURL = 'http';
	if( isset($_SERVER["HTTPS"])  AND  $_SERVER["HTTPS"] == "on" ){ $pageURL .= "s"; }
	$pageURL .= "://";
	if( $_SERVER["SERVER_PORT"] != "80" ){
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
	 	$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}



/**
 * Erzeugt einen Full Qualified Domain Name, wenn das Protokoll http
 * oder https nicht bereits im Namen vorhanden ist.
 */
function alias_fqdn($_destination ){

	if(	!(
						strncmp($_destination, 'http://',  7) == 0 
					||	strncmp($_destination, 'https://', 8) == 0
			) 
	){
		$port = (	isset($_SERVER['SERVER_PORT']) 
					&& $_SERVER['SERVER_PORT'] != '80' ) ? ':'.$_SERVER['SERVER_PORT'] : '';

		$_destination = 'http://'.$_SERVER['SERVER_NAME'].$port.$_destination;
	}
	return $_destination;
}





function alias_redirect($_destination, $status = '302'){

	if( get_param("forwarded")	 ){
		# prevent infinite loop
		return;	
	}
	
	$_destination = alias_fqdn($_destination);

	if( stripos( $_destination, "?" ) ){
		$_destination .= "?";	
	}
	$_destination .= "&forwarded=1";


	session_write_close();
	if( $status == '301' ){
		header('Status: 301 Moved Permanently"');
	}else{
		header('Status: 302 Moved Temporarily');
	}
	header('Location: '.$_destination);
	echo "<html>\n".
		 "<head><title>Redirect</title></head>\n".
		 "<body>Wenn Sie nicht weitergeleitetet werden, bitte ".
		 "<a href=\"".$_destination."\">hier</a> klicken.</body>\n".
		 "</html>\n";
	#exit(0);
}



function ZIMMER_IM_WEB(){}
function ziw_url(){
	if( is_dev_or_test() ) {
		return "http://zimmerdemo.wild-east.de/Schnittstellen/ZiW.php";
	}
	return "http://zimmer.im-web.de/Schnittstellen/ZiW.php";
}
#Filiale 28630
#Interface 13671
function ziw_url_base($_action='search'){
	$_email = "peter.buchmann@web.de";
	if( is_dev_or_test() ) {
		return ziw_url()."?get[{$_action}]=1&para[interface_id]=356&para[support_email]={$_email}";
	}
	return ziw_url()."?get[{$_action}]=1&para[interface_id]=13671&para[support_email]={$_email}";
}


function img_dir_from_id( $_property_id ){
	
	$_lp_pid_padded = str_pad( $_property_id, 4, "0", STR_PAD_LEFT );
	
	return $_lp_pid_padded;
	
}


?>