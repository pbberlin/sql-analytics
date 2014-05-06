<?php

	# functions required during initialization



# LOGs the time to app.log, as long as DEBUG-level < 5 or DEBUG contains time
#		$_haltepuntk_descr is the description string; 
#					for example __FILE__
#			or    the query of a mysql-request
#			or    <none> if no output is wished

#		functions can be found from inside the function; the file can NOT

#		$_supersede_debug => output to http_response regardless of DEBUG level

#	ideas for the future: set the start time to an internal static value
#		set LAST-time of function call equally as an internal static value
#		then compute differentials and total times
function render_elapsed_time_since(  $_haltepuntk_descr='' , $_supersede_debug=false){
	
		static $_time_start;	# first call
		static $_time_last;	# first call
		global $_DOC_ROOT;

		if( ! $_haltepuntk_descr ) $_haltepuntk_descr = "__FUNCTION__ should called with a argument --FILE-- ";
	
		# first call is just for init

		$_time_now =  microtime(true);
	
		if(!$_time_start)  $_time_start = $_time_now;
		if(!$_time_last)   $_time_last  = $_time_now;

		if( 
		
				(
							@$_GET['dbg'] > '5'  
					OR 	!( stripos(@$_GET['dbg1'],'time')===FALSE)
					OR 	$_supersede_debug
				)	

				AND

				$_haltepuntk_descr <> "<none>"		# explicit suppress processing
		){
		
			$_elapsed_total = $_time_now - $_time_start;
			$_elapsed_total = round($_elapsed_total, 4);

			$_elapsed_diff = $_time_now - $_time_last;
			$_elapsed_diff = round($_elapsed_diff, 4);



			$_diff1 = ($_time_now - $_time_last);
			if( $_diff1< 0.012 ){
				# too small to consider				
				$_diff_formatted = number_format ( $_diff1, 7 );
				$_last_formatted = number_format ( $_time_last, 7 );
				$_last_formatted = "";
				echo "\n<br>exec time for $_haltepuntk_descr is tiny: $_diff_formatted<br>";
				return $_time_now;
			}

			
			$_DOC_ROOT_2 = str_ireplace('/',"\\",$_DOC_ROOT);
			if( 			starts_with( $_haltepuntk_descr, $_DOC_ROOT )
					OR		starts_with( $_haltepuntk_descr, $_DOC_ROOT_2 )
			){
				$_haltepuntk_descr = substr($_haltepuntk_descr,strlen($_DOC_ROOT));
			}			
			$_haltepuntk_d_f = vd1($_haltepuntk_descr);
			$_haltepuntk_d_f = str_ireplace("\n\t\t\t","\n",$_haltepuntk_d_f);
			$_haltepuntk_d_f = str_ireplace("\n\t\t\t","\n",$_haltepuntk_d_f);
			$_haltepuntk_d_f = str_ireplace("\n\t\t","\n",$_haltepuntk_d_f);
			$_haltepuntk_d_f = "<div style='width:500px;overflow:hidden;'>$_haltepuntk_d_f</div>";
			echo "<style>
					table.debug-ausgabe {
						padding: 0;
						margin: 0;
						border: 1px solid #aaa;
						border: none;
					}
					table.debug-ausgabe  tr{
						padding: 0;
						margin: 0;
						border: none;
					}
					table.debug-ausgabe td {
						border: none;
						padding: 4px;
						line-height: 120%;
						border: 1px solid #aaa;
					}
				</style>";
			$_d1=   "<table class='debug-ausgabe' border=1   >
						<!--
						<tr>
							<td colspan=3 >performance info</td>
						</tr>
							<td width='220px' style='vertical-align:top'> $_display_string</td>

						-->
						<tr>
							<td width='100px' style='vertical-align:top'><b>step: {$_elapsed_diff}s</b><br>total: {$_elapsed_total}s</td> \n
							<td style='width=500px;vertical-align:top'  >$_haltepuntk_d_f</td> \n
						</tr>
					</table>
			";	

			if( function_exists(render_inline_block) ){

				$cw1 = 40;$cw2 = 100;
				$br  = render_br();

				$r1c1 = render_inline_block("step:",$cw1);
				$r1c2 = render_inline_block("{$_elapsed_diff}&nbsp;s",$cw2, "font-weight:bold");


				$r2c1 = render_inline_block("total:",$cw1);
				$r2c2 = render_inline_block("{$_elapsed_total}&nbsp;s",$cw2, "font-weight:normal");

				echo "$br";

				echo "$r1c1 $r1c2 ";
				echo "$r2c1 $r2c2 $br";

			} else {
				echo $_d1;
			}

				

			if( @$_GET['dbg'] > '10' ){

				$_arr_backtrace = debug_backtrace();
				$_trace_data_this_func = array_shift( $_arr_backtrace );
				if( sizeof($_arr_backtrace) < 1 ) $_display_string .= "global level ";		
				$_display_string = "";
				foreach( $_arr_backtrace  as $_key => $_arr_sub ){
					$_display_string = $_arr_sub['function'] . " - ".  $_display_string ;
				}

				if( sizeof($_arr_backtrace) ) vd($_arr_backtrace);		
			} 

			#echo "time last renewed<br>";
			$_time_last = $_time_now;


		} 

		return $_time_now;


}


	# does NOT make any vertical space
	function clear_float(){
			$_str = "<div 
				style='
					display:block;
					clear:both;
				'  
			></div>\n";
			return $_str;
	};
	
	
	
	function vspacer($_percent_height='100%'){

			$_height = $_percent_height;
			if( 
					substr($_height, -2) == "px" 
				OR substr($_height, -1) == "%"
			){
				#fine
			}else {
				$_height .= "px";
			}
			$_str = "<div style='display:block;line-height:$_percent_height; '>&nbsp;</div>\n";
			return $_str;
	};

	

	





	# via URL-get Parameter dbg a debugging level can be activated permanently
	#	it is then saved in a session variable dbg
	#	dbg=off destroys session
	function debug_control(){
		
		# activate dbg for the FIRST time - activate session
		if( $_dbg = $_GET['dbg'] ){
			session_start();
			$_SESSION['dbg'] = $_GET['dbg'];
			echo "error reporting eingeschaltet über url get param mit Level -$_dbg-<br>	";
		}
		
		# reactivate/continue previous session
		# set error loggings
		if( $_COOKIE['PHPSESSID']  ){
			session_start();	
			$_dbg = $_GET['dbg'] = $_SESSION['dbg'];
			if( $_dbg ){
				echo "error reporting eingeschaltet aus session param mit Level -$_dbg-<br>	";	
			}
		}
		
		global $argv;
		# $argv[0] ist immer scriptname

		# debug special 
		if( starts_with( $argv[1],'dbgs') ){
			$_dbg_label = substr($argv[1],strlen('dbgs'));
			$_GET['dbgs'] = $_dbg_label;
			echo "error reporting speziell |$_dbg_label| eingeschaltet über kommandozeile.<br>\n";
		# debug normal
		} else if( starts_with( $argv[1],'dbg') ){
			$_lvl = substr($argv[1],strlen('dbg'));
			$_dbg = $_GET['dbg'] = $_lvl;
			echo "error reporting allgemein eingeschaltet über kommandozeile mit Level -$_dbg-<br>\n";
		}
		
		
		if( $_GET['dbg']  > 8 ){
			error_reporting(E_ALL);
			ini_set("display_errors", 1);		# Display Errors at will
			echo "report and display all warnings+notices. <br>";
		} 
		
			
		# switch it off forever
		if( $_GET['dbg']== 'off' ){
				session_unset();
				$_SESSION=array();
				session_destroy();	  		
				echo "error reporting ausgeschaltet<br>";
				$_url_params = $_SERVER['QUERY_STRING'];
				$_url_params = str_ireplace("dbg=off","",$_url_params);
				$_forward_url = get_script_name() . "?$_url_params&_stop_session_completed=true";
				header("Location: $_forward_url");	
				echo "dbg session soll beendet werden <a href='$_forward_url' >Weiter</a><br>";		
		}

		
		
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
	
	function str_contains($_haystack, $_needle){

		$_haystack	 = strtolower($_haystack);
		$_needle     = strtolower($_needle);


		if(  strpos( $_haystack , $_needle)  === false  ){	
			$_res = false;		
		} else {
			$_res = true;
		}
		return $_res;
	}


	# everything beneath /backend
	function is_internal_application(){
	
		if( is_called_from_command_line() ) return true;
	
		if( 			isset($_REQUEST['is_explicit_internal_application'])  
				AND 	$_REQUEST['is_explicit_internal_application'] 
		){
			return true;	
		}
	
		$_uri = get_script_name();		
		$_arr_compare_dirs['admin']     	                   = "some_val1"; 	
		$_arr_compare_dirs['backend']                       = "some_val2"; 	
		$_arr_compare_dirs['glob_scripts_admin_helpers']    = "some_val3"; 	
		foreach( $_arr_compare_dirs as $_first_dir => $_val_unused ){
			if( starts_with($_uri, "/".$_first_dir."/" ) ){
				return true;
			}
		}
		return false;
	}


	# this is hacky, we may need to make a config setting someday
	function is_linux_server(){
		if(substr(PHP_OS, 0, 3) == "WIN"){
			return false;
		}
		return true;
	}






	function get_script_name(){

		$_scriptname = $_SERVER['SCRIPT_NAME'];		# bspw. http://www.yoursite.com/example/index.php --> /example/index.php   see http://php.about.com/od/learnphp/qt/_SERVER_PHP.htm
	
		if( is_called_from_command_line() ){
			# returns only the file - without any path			
			echo "fehler: script name is not reliable from command line <br>\n";	
		}
		return $_scriptname;		
		
	}


	# somehow we need to find out
	function is_called_from_command_line(){
		if( 
					$_SERVER['HTTP_HOST'] 
				OR	$_SERVER['http_host'] 		
		){
			return false;
		} else {
			return true;	
		}
		
	}
	

	# major problem is the php command line call
	
	# convention:
	#		the doc-root directory should contain the hostname
	#		so that we can fall back on this DIR if the script is called by command line
	
	function get_host_name(){
		if( is_called_from_command_line()  ){
			#	we determine the hostname from the doc root directory
			global $_DOC_ROOT;
			$_hostname = basename($_DOC_ROOT);
			return $_hostname;
		} else {
			$_hostname = $_SERVER['HTTP_HOST'];
			$_hostname = strtolower($_hostname);	
			return $_hostname;	
		}
	
	}


	// based on CONVENTION that doc-root dir contains
	//	devel or test
	function is_dev_or_test(){
	
		global $_DOC_NTS_ROOT; 
		
		$_rightest_dir = basename($_DOC_NTS_ROOT);

		$_arr_recogizable_strings = array(
			'devel.'=>1, 'test.'=>2
		);
		foreach( $_arr_recogizable_strings as $_str_significant => $_val_unused ){
			if( starts_with($_rightest_dir, $_str_significant ) ){
				return true;
			}
		}

		return false;
		
	}





	function flush_http( $_msg="" ){

		if( $_msg) vd($_msg);
		echo "<div style=' display:block; width: 2px; height: 2px; overflow:hidden; ' >";
		echo str_repeat("x &nbsp; ",2000);
		echo "</div>";
		flush();

	}



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

# collect messages
# $s = get 
# $s = reset
# $s = "some logging msg"
function cm($s,$ns = 'default'){

	static $_arr_msg;
	if( ! isset($_arr_msg) )  $_arr_msg['default'] = array();

	foreach($_arr_msg[$ns] as $_key_unused => $v){
		$_delim = "<br>\n";
		if( strpos($v,"</")  OR  strpos($v,"<br") ) $_delim = "\n";
		$_ret .= $v . $_delim;
	}

	if( $s == "reset" ){
		$_arr_msg[$ns] = array();
	}
	else if( $s == "get" ){
		# nothing
	} else {
		# append
		$_arr_msg[$ns][] = $s;
	}

	return $_ret;
	

}

?>