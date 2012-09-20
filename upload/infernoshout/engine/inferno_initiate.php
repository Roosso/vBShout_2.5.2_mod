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
 * Initiate engine if needed
 * * * * * * * * * * * * * * * * * * * * * * * * * * 
 */

$script_allow = explode(',', preg_replace('#\s#', '', $this->vbulletin->options['ishout_pages']));

if ((@in_array(THIS_SCRIPT, $script_allow) || $this->vbulletin->options['ishout_globaldeploy']) && $this->vbulletin->options['ishout_online'] && !$this->is_in_ug_list($this->vbulletin->options['ishout_hideshoutbox']))
{
	$this->setup_deployment();
}
?>