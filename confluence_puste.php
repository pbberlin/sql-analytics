<?php



	require_once("bootstrap_functions.php");
	require_once("functions_helpers.php");
	require_once("functions_url_tools.php");


	require('reusable_globals.php');		# should be repeatable in function scope - therefore not ONCE but only require


	echo render_layout_header();


	render_elapsed_time_since("start");

	echo "<h1 style='margin-bottom'>Confluence SOAP API Test </h1>";

	echo "

	Remote Access muss extra <span style='font-size:15px;'>aktiviert</span> werden:<br>
        https://confluence.atlassian.com/display/DOC/Enabling+the+Remote+API<br><br>

        SOAP API Doku <a href='https://developer.atlassian.com/display/CONFDEV/Confluence+XML-RPC+and+SOAP+APIs#ConfluenceXML-RPCandSOAPAPIs-soapCreatingaSOAPClient' target='doku'>here</a><br>

	CLI Doku <a href='https://marketplace.atlassian.com/plugins/org.swift.confluence.cli' target='doku' > here</a><br>
	CLI Doku <a href='https://bobswift.atlassian.net/wiki/display/CSOAP/How+to+automate+adding+text+to+Confluence+pages' target='doku' >Examples</a><br>

	";
	

	$_confluence_page_id  = get_param("confluence_page_id",  "1376343");
	$_confluence_host     = get_param("confluence_host"   ,  "ws302021:8071");
	$_user                = get_param("user"       ,  "peter.buchmann");
	$_pw                  = get_param("password"   ,  "12345678");


	echo "<form>";
	echo getHiddenInputs();

	echo render_inline_block("confluence host und port",200);
	echo "<input type='text' name='confluence_page_id' size='60' value='$_confluence_host' ><br>";
	
	echo render_inline_block("user - pw",200);
	echo "<input type='text' name='confluence_page_id' size='20' value='$_user' > - ";
	echo "<input type='password' name='confluence_page_id' size='20' value='$_pw' ><br>";


	echo render_inline_block("confluence page id (im Bearbeiten Modus aus URL kopieren)",200);
	echo "<input type='text' name='confluence_page_id' size='20' value='$_confluence_page_id' ><br>";


	echo "<input type='submit' accesskey='s' /><br><br>";
	echo "</form>";


	$sc = new \SoapClient("http://{$_confluence_host}/rpc/soap-axis/confluenceservice-v2?wsdl");


	$token = $sc->login($_user,$_pw);
	#$page = $sc->getPage($token, 'space', 'soaptarget'); // works
	$page = $sc->getPage($token, $_confluence_page_id);

	echo " type - class: ". gettype($page) . " - " . get_class($page). "<br>";

	echo "Inhalt der seite $_confluence_page_id: <br>";
	echo "<b>" . $page->title . "</b><br>";
	echo $page->content;

	$page->content .= "<br/><b>remote</b> content appended " . date("h:m:s") . " - " . get_host_name();


	

	echo "<br> trying to append <br>...";
	$resp = $sc->storePage($token, $page);

	$_s1 = print_r($_resp,1);
	$_s2 = print_r($page ,1);
	$_s3 = print_r($page_new ,1);
	echo "<pre>$_s1 \n  $_s2  \n  $_s3  </pre>";




	flush_http();


	echo render_layout_footer();





?>
