<?php
/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
|| + Inferno Technologies Software
|| +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
|| + Product: Inferno vBShout Pro
|| +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
|| + Website: http://infernotechnologies.net
|| + Email: webmaster@infernotechnologies.net
|| + Copyright 2004 - 2006 Inferno Technologies
|| + All Rights Reserved
\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */



/**
 * Deploy the shoutbox
 * * * * * * * * * * * * * * * * * * * * * * * * * * 
 */

global $infernoshout;

$output = str_replace('<!--{%SHOUTBOX%}-->', 	$infernoshout->box, $output);
$output = str_replace('[%SHOUTBOX%]', 			$infernoshout->box, $output);

unset($infernoshout);
?>