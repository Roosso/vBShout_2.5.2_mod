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
 * Load the shoutbox
 * * * * * * * * * * * * * * * * * * * * * * * * * * 
 */

global $infernoshout;

if ($infernoshout->deploy)
{
	$infernoshout->load_editor_settings();
	$infernoshout->build_editor_select('colours', 'color');
	$infernoshout->build_editor_select('fonts', 'font-family');

	$template = 'inferno_shoutbox_box';

	eval('$infernoshout->editor = "' . fetch_template('inferno_shoutbox_editor') . '";');
	eval('$infernoshout->box = "' . fetch_template($template) . '";');

	if ($infernoshout->vbulletin->options['ishout_largertext'])
	{
		$infernoshout->box = str_replace('smallfont', '', $infernoshout->box);
	}
}
?>