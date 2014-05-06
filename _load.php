<?php
# Load charts


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




	echo "<h1>Transaktionen - RAM und Platten-IO  &nbsp;  ";
	echo "<a href='./presentation_load/index.html' target='explain' style='font-size:12px;display:inline-block;position:relative;top:-4px;margin-bottom:4px;text-decoration:none;' >Erläuterung</a></h1>";

	$_hid = getHiddenInputs();

	$_hours = get_param("hours", 8);
	$_until = get_param("until", 0);
	$_multihosts = get_param("multihosts", "", true);


	$_c1w = 80;
	$_c2w = 200;



	echo "<form>";
	echo $_hid;


	echo render_inline_block("Stunden zurück"  , $_c1w);
	echo render_inline_block("<input  type='text' name='hours'  value='$_hours' style='width:40px;' />  ");
	echo render_inline_block(" bis <input  type='text' name='until'  value='$_until' style='width:40px;' />  &nbsp; ");
	echo render_inline_block(" &nbsp;  hostlist <input  type='text' name='multihosts'  value='$_multihosts' style='width:240px;' />  &nbsp; ");
	#echo render_br();



	#echo render_inline_block(" "  , $_c1w);
	echo render_inline_block("<input type='submit' name='submit_01' value='submit'  accesskey='s' style='width:{$_c2w}px; height:28px; margin-top:1px;' />");
	echo "</form><br>";



	flush_http();


	$_salt = microtime(true);

	$_multihosts =  trim($_multihosts);
	if( strlen( $_multihosts )>3 ){
		if( substr( $_multihosts,0,1)  <> "{" ) $_multihosts = "{" . $_multihosts;
		if( substr( $_multihosts,-1 )  <> "}" ) $_multihosts = $_multihosts . "}";

		$_HOST_ABBREVIATED = $_multihosts;
	}

	$_arr_mh = explode(",",$_multihosts);

	#vd($_multihosts);
	#vd($_arr_mh);
	$_count_hosts = sizeof( $_arr_mh ) ;
	#vd( $_count_hosts );




	$_height_1     = 180  +  $_count_hosts * 20 ;
	$_height_2     = 340  +  $_count_hosts * 20;
	$_height_small = 180  +  $_count_hosts * 80;








       $_img_url = "
http://s394.ipx/render/?
lineWidth=3
&from=-{$_hours}hours
&until=-{$_until}hours
&hideLegend=false
&_salt={$_salt}
&width=700
&lineMode=connected
&height={$_height_1}
&colorList=cc2222
&target=groupByNode(  movingAverage(servers.{$_HOST_ABBREVIATED}.mysql.Com_{delete,delete_multi,insert,insert_select,replace,replace_select,update,update_multi},15),1,'sumSeries')
&target=alias(color(constantLine(20000),'cccccc'),'~ maximum in memory simple updates with Dell 720')
&title='Schreibende Transaktionen - Insert - Update - Delete'

        ";

        echo "<img 
                src=\"  $_img_url
                \"
        />";




        $_img_url = "
http://s394.ipx/render/?
lineWidth=3
&from=-{$_hours}hours
&until=-{$_until}hours
&hideLegend=false
&_salt={$_salt}
&width=700
&lineMode=connected
&height={$_height_1}
&colorList=cc2222
&target=sumSeriesWithWildcards(servers.{$_HOST_ABBREVIATED}.mysql.Handler_{delete,write,update},3)
&target=alias(color(constantLine(100000),'cccccc'),'~ maximum')
&title='Schreiboperationen in Zeilen - (Handler_delete|insert|update)'

        ";

        echo "<img 
                src=\"  $_img_url
                \"
        />";









       $_img_url = "
http://s394.ipx/render/?
lineWidth=3
&from=-{$_hours}hours
&until=-{$_until}hours
&hideLegend=false
&_salt={$_salt}
&width=700
&lineMode=connected
&height={$_height_1}
&colorList=22aa22
&target=movingAverage(sumSeriesWithWildcards(servers.{$_HOST_ABBREVIATED}.mysql.Com_{xxx,select},3),15)
&target=alias(color(constantLine(80000),'cccccc'),'~ maximum in memory simple selects with Dell 720')
&title='Lesende Transaktionen - Selects'

        ";

        echo "<img 
                src=\"  $_img_url
                \"
        />";


	$_cols_rows_read = str_repeat( "22cc22,aacc22,", $_count_hosts );
	$_cols_rows_read = substr($_cols_rows_read,0,-1);
	#vd($_cols_rows_read);


       $_img_url = "
http://s394.ipx/render/?
lineWidth=3
&from=-{$_hours}hours
&until=-{$_until}hours
&hideLegend=false
&_salt={$_salt}
&width=700
&lineMode=connected
&height={$_height_1}
&colorList={$_cols_rows_read}
&target=alias(color(constantLine(350000),'cccccc'),'~ maximum')
&target=alias(   sumSeriesWithWildcards(servers.{$_HOST_ABBREVIATED}.mysql.Handler_{read_key,read_next}    ,3) ,'Zeilen gelesen')
&target=alias(   sumSeriesWithWildcards(servers.{$_HOST_ABBREVIATED}.mysql.Handler_{read_rnd,read_rnd_next},3) ,'Zeilen gelesen Tablescan')
&title='Leseoperationen in Zeilen (Handler_read... - not rnd'

        ";

#vd($_img_url);


        echo "<img 
                src=\"  $_img_url
                \"
        />";






	echo "<hr>";











       if( $_HOST_ABBREVIATED == "s464" ){             echo "<h2>controlling db verwendet MyISAM - daher keine Werte für RAM Cache</h2>";      }



	# we request the number of sd[a-z]* hard disk devices of the server
	# in order to color them consistently
	$variable = file_get_contents("http://s394.ipx/render/?&format=json&_salt={$_salt}&from=-120minutes&target=servers.$_HOST_ABBREVIATED.iostat.sd?.read_byte_per_second");
	$decoded = json_decode($variable);
	#vd($decoded);
	$_number_sdx_devices = 0;
	$_str_devices = "";
        foreach($decoded as $_xx => $_row){
		#vd($_lp_arr);
		#vd($_row->target);
		$_arr_path = explode(".",$_row->target);
		#vd($_arr_path);
		$_str_devices .= ", " . $_arr_path[3];
		$_number_sdx_devices++;
		
	} 
	$_str_devices = substr($_str_devices, 2);

	for( $_i = 1; $_i <= $_number_sdx_devices; $_i++ ){
		$d = dechex(12 - 2* $_i );  # dynamic color part
		
		#vd("dyn is $d");	
		$_color_devices_read  .= ",11{$d}{$d}11";
		$_color_devices_write .= ",{$d}{$d}1111";
		$_color_devices_read_write .= ",44{$d}{$d}44,{$d}{$d}4444";
	}


        $_cols_ram = str_repeat( "00ff00,", $_count_hosts )  .   str_repeat("ff0000,", $_count_hosts ) ;
        $_cols_ram = substr($_cols_ram,0,-1);


	

	$_img_url = "
http://s394.ipx/render/?
lineWidth=3
&from=-{$_hours}hours
&until=-{$_until}hours
&hideLegend=false
&_salt={$_salt}
&width=700
&lineMode=connected
&height={$_height_2}
&colorList={$_cols_ram}{$_color_devices_read}{$_color_devices_write}
&target=alias(lineWidth(currentAbove(movingAverage(servers.{$_HOST_ABBREVIATED}.mysql.Innodb_data_{read,xxxxxxx},18),2),4),'RAM Lesen')
&target=alias(lineWidth(currentAbove(movingAverage(servers.{$_HOST_ABBREVIATED}.mysql.Innodb_data_{written,xxwrites},15),2),4),'RAM Schreiben')
&target=alias(currentAbove(lineWidth(movingAverage(servers.{$_HOST_ABBREVIATED}.iostat.sd?.{xxxxxxxxxxxxxxxxxxxxx,read_byte_per_second},15),2),0),'Platte Lesen')
&target=alias(currentAbove(lineWidth(movingAverage(servers.{$_HOST_ABBREVIATED}.iostat.sd?.{write_byte_per_second,xxxxxxxxxxxxxxxxxxxx},15),2),0),'Platte Schreiben')
&target=alias(color(constantLine(100000000),'cccccc'),'~SSD max')
&target=alias(color(constantLine(20000000), 'cccccc'),'~HDD max')
&vtitle='MB per sec'
&title='Hot Set im RAM?'

	";


	#vd($_img_url);

	echo "<p style='margin-top:20px' >Grüne und rote Linien sollten nahe beieinander sein.  <br>
		
		Lesen grün - Schreiben rot<br>
		RAM - hell und breit  ;  Festplatte(n) - dunkel, schmal   <br>Device(s):  $_str_devices  </p>";
	
	echo "<a href='$_img_url' target='graphite' ><img 
		src=\"  $_img_url
		\"
	/></a>

	";












	$_cols = str_repeat( "aa0000,", $_count_hosts ) . str_repeat( "ff2222,", $_count_hosts )  . str_repeat( "ff8822,", $_count_hosts )  ;  
	$_cols = str_repeat( "aa0000,", $_count_hosts )   . str_repeat( "ff8822,", $_count_hosts )  ;  
	
	$_cols = substr($_cols,0,-1);
	$_cols = "ff8822";


       $_img_url = "
http://s394.ipx/render/?
lineWidth=3
&from=-{$_hours}hours
&until=-{$_until}hours
&hideLegend=false
&_salt={$_salt}
&width=700
&lineMode=connected
&height={$_height_2}
&colorList={$_cols}
&target=alias(color(constantLine(100),'cccccc'),'Util max')
&target=alias(color(constantLine(104),'eeeeee'),'--' )
&target=color(averageAbove(servers.{$_HOST_ABBREVIATED}.iostat.sd?.util_percentage,0),'ee0000')
&target=secondYAxis(lineWidth(alpha(                  sumSeriesWithWildcards(servers.{$_HOST_ABBREVIATED}.iostat.sd?.{read,write}_byte_per_second,4     ),0.9),1))
&title='Utilization und Read-Write pro Sec'

        ";

	#&aaaatarget=secondYAxis(lineWidth(alpha(   averageAbove(servers.{$_HOST_ABBREVIATED}.iostat.sd?.iops,200                                           ),0.9),1))

        echo "<img 
                src=\"  $_img_url
                \"
        />";







	echo "<hr>";






       $_img_url = "
http://s394.ipx/render/?
lineWidth=3
&from=-{$_hours}hours
&until=-{$_until}hours
&hideLegend=false
&_salt={$_salt}
&width=700
&lineMode=connected
&height={$_height_small}
&target=alias(color(constantLine(100),'cccccc'),'100')
&target=servers.{$_HOST_ABBREVIATED}.mysql.{Table_locks_waited,Performance_schema_rwlock_instances_lost,Performance_schema_rwlock_classes_lost,Performance_schema_locker_lost,Innodb_row_lock_current_waits,Innodb_row_lock_waits,Com_lock_tables,Com_unlock_tables})
&target=alias(scale(derivative(servers.{$_HOST_ABBREVIATED}.mysql.Innodb_row_lock_time),0.00001),'row lock time / 100.000')
&target=alias(scale(servers.{$_HOST_ABBREVIATED}.mysql.Table_locks_immediate,0.001),'table locks / 1000')
&title='Locks'

        ";

#vd($_img_url);


        echo "<br><br><br><img 
                src=\"  $_img_url
                \"
        />";


















	echo render_layout_footer();




?>
