<?php



	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");


	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();

	if( ! $_HOST  ) {
		echo "please select host name<br>";
		echo render_layout_footer();
		exit;
	}


	echo render_layout_footer();


	render_elapsed_time_since("start");

	echo "<h1 style='margin-bottom:8px;'>group by sum problem</h1>";

	$_columns_grouped_by = get_param("columns_grouped_by", "shopId productId pcatId provider partner clicktype manufacturerId site");
	$_columns_summed_up  = get_param("columns_summed_up",  "clickcount");
	
	$_tbl_src     	= get_param("tbl_src"  ,  "statistic.adclicks_summary_2013_12");
	$_dest_where_2   = get_param("where"  ,  " month=12 ");

	$_tbl_dest  = get_param("tbl_dest",  "statistic.adclicks_summary_month_2013");

	$_start = get_param("start",0);
	$_count = get_param("count",100);

	echo "<form>";
	echo getHiddenInputs();
	echo render_inline_block("table dest",100);
	echo "<input type='text' name='tbl_dest' size='60' value='$_tbl_dest' ><br>";
	echo render_inline_block(" &nbsp; additional dest where",120);
	echo "<input type='text' name='where'   size='60' value='$_dest_where_2'   ><br>";
	echo render_inline_block("table src",100);
	echo "<input type='text' name='tbl_src'   size='60' value='$_tbl_src'   ><br>";

	echo render_inline_block("columns grouped by",100);
	echo "<input type='text' name='columns_grouped_by' size='100' value='$_columns_grouped_by' ><br>";
	echo render_inline_block("column summed up",100);
	echo "<input type='text' name='columns_summed_up'  size='40'  value='$_columns_summed_up'  ><br>";
	echo render_inline_block("from - count",100);
	echo "<input type='text' name='start'  size='5'  value='$_start'  >  ";
	echo "<input type='text' name='count'  size='5'  value='$_count'  ><br>";

	echo "<input type='submit' /><br>";
	echo "</form>";
	
	$_arr_columns_grouped_by = explode( " ", $_columns_grouped_by );
	#vd(  $_arr_columns_grouped_by,"xx"  );
	

	$_select1 = "";
	foreach( $_arr_columns_grouped_by  as $_key_unused => $_lp_col ){
		$_select1 .= ", IFNULL($_lp_col, 'NULL') $_lp_col \n";
	}
	$_select1 = substr($_select1,2);
	#vd($_select1);


	flush_http();

	$_sql  = " select $_select1
			, $_columns_summed_up
		from  $_tbl_dest
		where 1=1
			AND $_dest_where_2

		limit $_start, $_count
	";

	vd($_sql);

	$_arr_t = execute_query($_sql );

	#echo array_to_table($_arr_t);
	
	flush_http();


	$_cntr = 0;
	foreach($_arr_t as $_key_unused => $_arr_lp){

		$_cntr++;

		extract( expand_options_array($_arr_lp) );
		#vd( expand_options_array($_arr_lp) );
		
		$_src_where_1 = "";
		foreach($_arr_columns_grouped_by as $unused => $k){
			$v = $_arr_lp[$k];
			if( $v == 'NULL' ){
				$_src_where_1 .= "\n AND $k IS NULL ";
			} else {
				$_src_where_1 .= "\n AND $k =  '$v' ";
			}

		}
		$_val_src  = $_arr_lp[$_columns_summed_up];

		#vd($_src_where_1);

		$_sql = "
			select 
				SUM( $_columns_summed_up  ) val_dst
			FROM  $_tbl_src 
			WHERE 1=1
			$_src_where_1

		";
		#vd($_sql );
		$_arr_r = execute_query_get_first($_sql);
		extract( expand_options_array($_arr_r ) );

		#vd($_arr_r);
		if ( $_val_src <> $_val_dst ){
			echo "<br>distinct val for $_val_src vs $_val_dst  - 	for " .  substr($_src_where_1,5);
		} else {
			if( $_cntr % 400  == 0 ){
				echo "row $_cntr of $_count";
				flush_http();
			} else if( $_cntr % 20 == 0) {
				echo ". ";
			}
		}
		#echo array_to_table($_arr_lp);

	
	}
	flush_http();


	echo render_layout_footer();





?>
