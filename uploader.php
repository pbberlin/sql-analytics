<table width="500" border="0" align="center" cellpadding="0" cellspacing="1" bgcolor="#CCCCCC">
<tr>
<form method="post" enctype="multipart/form-data" name="form1" id="form1">
<td>
<table width="100%" border="0" cellpadding="3" cellspacing="1" bgcolor="#FFFFFF">
<tr>
<td><strong>Angebotsverarbeitung HTML-Fassung neu hochladen</strong></td>
</tr>
<tr>

<td>Select html file
<input name="ufile_html" type="file" id="ufile_html" size="50" accept="text/html"  /></td>
</tr>

<td>Select png file (Angebotsverarbeitung_011_1.png)
<input name="ufile_png" type="file" id="ufile_html" size="50" accept="image/png" /></td>
</tr>



<tr>
<td align="center">

<input type="hidden" name="p_submit" value="true" />
<input type="submit" name="Submit" value="Upload" />

</td>
</tr>
</table>
</td>
</form>
</tr>
</table>


<?php

$path= "upload/".$HTTP_POST_FILES['ufile_html']['name'];

$path_html= dirname(__FILE__).'/index.html';
$path_png = dirname(__FILE__).'/Angebotsverarbeitung_011_1.png';

if($ufile_html !=none AND $_REQUEST['p_submit'] ){
  if(move_uploaded_file($HTTP_POST_FILES['ufile_html']['tmp_name'], $path_html)){
    echo "HTML Successful<BR/>";
    #echo "File Name :".$HTTP_POST_FILES['ufile_html']['name']."<BR/>";
    #echo "File Size :".$HTTP_POST_FILES['ufile_html']['size']."<BR/>";
    #echo "File Type :".$HTTP_POST_FILES['ufile_html']['type']."<BR/>";
  }else{
    echo "HTML Error<BR/>";
  }


  if(move_uploaded_file($HTTP_POST_FILES['ufile_png']['tmp_name'], $path_png)){
    echo "PNG Successful<BR/>";
  }else{
    echo "PNG Error<BR/>";
  }

  echo "<a href='http://wiki.dealdomain/images/process_chart/' target='Angebotsverarbeitung' >neue Ansicht</a><br/>";

}
?>
