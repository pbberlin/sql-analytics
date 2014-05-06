<?php
# Distributed processing of a large table


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


	# count_batches: how often will a thread read an additional chunk
	# 	before it gently phases out

	#

	$_col1_left_percent = 25;
	$_col1_width_percent = $_col1_left_percent - 2;
	$_col2_left_percent = 100 - $_col1_left_percent - 2;
	$_col2_width_percent = $_col2_left_percent - 2;

	#$_col1_left_percent = 45;

	$_start_new_thread_checks = get_param("start_new_thread_checks",3600);		

	$_js_dry_run = get_param("js_dry_run","true");		

	$_url_params = getLinkAnhang();
	$_top_r1 = 120;
	$_top_r2 = 150;

	$_height_r1 =  28;
	$_height_r2 = 550;

	echo "
			<a href='#' onclick='javascript:reset_max();return false;' accesskey='p' >sto<u>p</u> requests</a><br>
			<a href='#' onclick='javascript:reload_start(false);return false;' accesskey='q' >run once</a><br>
			Example: &hostname=sXXX&js_dry_run=false&batch_size=25000<br>
			
				



			<div id='area_thread_start_r1' style='display:block; position: absolute; left: 0px;               top: {$_top_r1}px; width: {$_col1_width_percent}%; padding:0 20px 0 10px; height: {$_height_r1}px; overflow:hidden; overflow-y: scroll; border: 1 solid #aab'>
				<div id='progressbar_1' style='width:200px; height:12px; margin-top:4px;' ></div>
			</div >



			<div id='area_thread_start_r1' style='display:block; position: absolute; left: {$_col1_left_percent}%; top: {$_top_r1}px; width: {$_col2_width_percent}%; padding:0 20px 0 10px; height: {$_height_r1}px; overflow:hidden; overflow-y: scroll; border: 1 solid #aab'>
				<div id='progressbar_2' style='width:200px; height:12px; margin-top:4px;' ></div>
			</div >



 
			<div id='area_thread_start_r2' style='display:block; position: absolute; left: 0px;               top: {$_top_r2}px; width: {$_col1_width_percent}%; padding:0 20px 0 10px; height: {$_height_r2}px; overflow:hidden; overflow-y: scroll; border: 1 solid #aab'>
				<!-- area_thread_start_r2 -->
			</div >



			<div id='area_monitoring_r2' style='display:block; position: absolute; left: {$_col1_left_percent}%; top: {$_top_r2}px; width: {$_col2_width_percent}%; padding:0 20px 0 10px; height: {$_height_r2}px; overflow:hidden; overflow-y: scroll; border: 1 solid #aab'>
				<!-- area_monitoring_r2 -->
			</div >

		<script>

			var js_dry_run = $_js_dry_run;

			var cntr_mon = 0;
			var interval_id_mon   = 0;

			var cntr_start = 0;
			var interval_id_start   = 0;


			var cntr_mon_max = {$_start_new_thread_checks}*2;		// two times more often fired
			var start_new_thread_checks = {$_start_new_thread_checks};

			function reset_max(){ 
				cntr_mon_max = 2;
				start_new_thread_checks = 2;
				alert('Ajax requests stopped: ' + cntr_mon_max + ' - ' + start_new_thread_checks);
			}


			function reload_mon(){ 
				cntr_mon++;
				if( cntr_mon >= cntr_mon_max ){
					cntr_mon = 'finished'
					clearInterval(interval_id_mon);
					return;
				} 
				if( js_dry_run ) return;
				$.ajax({
					url: 'distributed_processing_monitor.php?{$_url_params}',
					cache: false
				}).done(function( html ) {
					$('#area_monitoring_r2').prepend( 'Durchlauf ' + cntr_mon + '<br>' + html);

					$('#area_monitoring_r2').html(  $('#area_monitoring_r2').html().substring(0,12000 ) );

				});
			}


			function reload_start(check){ 
				cntr_start++;
				if( check && cntr_start >= start_new_thread_checks ){
					cntr_start = 'finished'
					clearInterval(interval_id_start);
					return;
				} 
				if( js_dry_run ) return;
				$.ajax({
					url: 'distributed_processing_start_thread_http.php?{$_url_params}',
					cache: false
				}).done(function( html ) {
					$('#area_thread_start_r2').prepend( 'Durchlauf ' + cntr_start + '<br>' + html);

					$('#area_thread_start_r2').html(  $('#area_thread_start_r2').html().substring(0,4000 ) );
				});
			}






			jQuery(document).ready(function($) {
				reload_mon();
				interval_id_mon   = setInterval('reload_mon()',1000);

				reload_start(true)
				interval_id_start = setInterval('reload_start(true)',2000);


				var pbar1 = $( '#progressbar_1' );
				var pbar2 = $( '#progressbar_2' );

				pbar1.progressbar({
					value: false,
					change: function() {},
					complete: function() {}
				});
				pbar2.progressbar({
					value: false,
					change: function(){},
					complete: function(){}
				});

				function fn_progress_1() {
					//var val = pbar1.progressbar( 'value' ) || 0;
					val =  100* cntr_start / start_new_thread_checks ;
					val = Math.round(val);
					if( val > 99 ) val = 99;
					pbar1.progressbar( 'value', val );

				}

				function fn_progress_2() {
					val =  100* cntr_mon / cntr_mon_max ;
					console.log('val befor ' + val );
					val = Math.round(val);
					if( val > 99 ) val = 99;
					console.log('val after ' + val );
					pbar2.progressbar( 'value', val );

				}

				setInterval( fn_progress_1, 400 );
				setInterval( fn_progress_2, 400 );





			});
							
		</script>

		<style>
			 .ui-progressbar-value 
			,.ui-widget-header 
			,.ui-corner-left {
				background-color: #ff6600;
				background-image: none;
			}
			
		</style>
	";





	echo render_layout_footer();




?>