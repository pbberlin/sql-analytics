<?php
$cfg['blowfish_secret'] = ''; /* YOU MUST FILL IN THIS FOR COOKIE AUTH! */
$cfg['MaxRows'] = 100;
$cfg['PmaAbsoluteUri'] = '';

$i = 0;

$i++;
$cfg['Servers'][$i]['host']          = 'anp-db.ipx';
$cfg['Servers'][$i]['adv_auth']      = false;
$cfg['Servers'][$i]['user']          = 'crawler';
$cfg['Servers'][$i]['password']      = 'secret';
$cfg['Servers'][$i]['bookmarkdb']    = '';
$cfg['Servers'][$i]['auth_type']     = 'config';
$cfg['Servers'][$i]['compress']      = FALSE;
$cfg['Servers'][$i]['connect_type']  = 'tcp';
$cfg['Servers'][$i]['controluser']   = '';
$cfg['Servers'][$i]['controlpass']   = '';
$cfg['Servers'][$i]['pdf_pages']     = '';
$cfg['Servers'][$i]['pmadb']         = '';
$cfg['Servers'][$i]['relation']      = '';
$cfg['Servers'][$i]['table_coords']  = '';
$cfg['Servers'][$i]['table_info']    = '';
$cfg['Servers'][$i]['AllowDeny']['rules'] = array();

?>
