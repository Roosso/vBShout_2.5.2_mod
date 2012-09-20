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
 * Inferno Shoutbox Pro
 * Created By Inferno Technologies
 * Copyright 2004-2007
 * All rights reserved
 * Project Development Team: Zero Tolerance
 * * * * * * * * * * * * * * * * * *
 */

error_reporting(E_ALL & ~E_NOTICE);

define('NO_REGISTER_GLOBALS', 1);
define('LOCATION_BYPASS', 1);
define('THIS_SCRIPT', 'infernoshout');

$phrasegroups = $specialtemplates = array();

$actiontemplates = array(
	'archive'	=> array(
		'inferno_shoutbox_archive',
		'inferno_shoutbox_archive_shout',
		'inferno_shoutbox_archive_topshouter',
	),
	'options'	=> array(
		'inferno_shoutbox_cp',
		'inferno_shoutbox_filters',
		'inferno_shoutbox_ignore',
		'inferno_shoutbox_ignore_user',
		'inferno_shoutbox_commands',
		'inferno_shoutbox_commands_row',
	),
	'detach'	=> array(
		'inferno_shoutbox_box',
		'inferno_shoutbox_box_alt',
		'inferno_shoutbox_editor',
		'inferno_shoutbox_shout',
		'inferno_shoutbox_user',
		'inferno_shoutbox_detach',
	),
);

$globaltemplates = array(
	'GENERIC_SHELL',
	'inferno_shoutbox_shout',
);

require_once('./global.php');
require_once(DIR . '/infernoshout/engine/inferno_engine.php');

$infernoshout =& new infernoshout_engine;

# ------------------------------------------------------- #
# Display the messages
# ------------------------------------------------------- #

if ((empty($_REQUEST['do']) || $_REQUEST['do'] == 'messages'))
{
	$charset = $vbulletin->userinfo['lang_charset'];
	$charset = strtolower($charset) == 'iso-8859-1' ? 'windows-1251' : $charset;
	@header('Content-Type: text/html; charset=' . $charset);
	//@header("Content-type: text/html; charset=windows-1251");
	$infernoshout->load_engine('shout');

	$shout =& new shout;

	echo $infernoshout->xml_document($infernoshout->fetch_shouts($shout, $_REQUEST['detach'] ? $vbulletin->options['ishout_shouts_detach'] : $vbulletin->options['ishout_shouts']));
	exit;
}

# ------------------------------------------------------- #
# Post a message
# ------------------------------------------------------- #

if ($_POST['do'] == 'shout' && ($message = trim($_REQUEST['message'])) != '')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'message'	=> TYPE_STR,
	));

	$message =& trim($vbulletin->GPC['message']);

	if ($vbulletin->userinfo['userid'] > 0)
	{
		$infernoshout->load_engine('shout');

		$shout =& new shout;
		$shout->process($message);
	}
}

# ------------------------------------------------------- #
# Edit a shout
# ------------------------------------------------------- #

if ($_POST['do'] == 'editshout')
{
	$infernoshout->fetch_edit_shout(intval($_POST['shoutid']));
	exit;
}

if ($_REQUEST['do'] == 'getarchiveshout')
{
	$infernoshout->load_engine('shout');
	$shout =& new shout;

	echo $infernoshout->xml_document($infernoshout->fetch_shouts($shout, '--nolim--', true, false, intval($_REQUEST['shoutid'])));
	exit;
}

if ($_POST['do'] == 'doeditshout')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'shout'	=> TYPE_STR,
	));

	$infernoshout->do_edit_shout(intval($_POST['shoutid']));
	exit;
}

# ------------------------------------------------------- #
# Update Style Properties
# ------------------------------------------------------- #

if ($_REQUEST['do'] == 'styleprops')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'colour'	=> TYPE_NOHTML,
		'fontfamily'	=> TYPE_NOHTML,
	));

	$_REQUEST['colour']	=& $vbulletin->GPC['colour'];
	$_REQUEST['fontfamily']	=& $vbulletin->GPC['fontfamily'];

	if ($vbulletin->userinfo['userid'] > 0)
	{
		$infernoshout->set_style_properties();
	}
}

# ------------------------------------------------------- #
# Show active users
# ------------------------------------------------------- #

if ($_POST['do'] == 'userlist')
{
	echo $infernoshout->fetch_users_list($shout);
	exit;
}

# ------------------------------------------------------- #
# Fetch smilies
# ------------------------------------------------------- #

if ($_POST['do'] == 'fetchsmilies')
{
	echo $infernoshout->fetch_smilies($infernoshout->vbulletin->options['ishout_smiliesshow']);
	exit;
}

# ------------------------------------------------------- #
# Display the archive
# ------------------------------------------------------- #

if ($_REQUEST['do'] == 'archive')
{
	$perpage	= $vbulletin->input->clean_gpc('r', 'perpage', TYPE_UINT);
	$page		= $vbulletin->input->clean_gpc('r', 'page', TYPE_UINT);
	$navbits	= array("infernoshout.php?" . $vbulletin->session->vars['sessionurl'] . "do=archive" => 'Archive');
	$navbits[""]	= 'Viewing Shoutbox Archive';

	if (!$infernoshout->is_in_ug_list($vbulletin->options['ishout_archiveperm']))
	{
		standard_error('У вас нет прав на просмотр архива!.');
	}

	$cansearch = $infernoshout->is_in_ug_list($infernoshout->vbulletin->options['ishout_archive_search']);

	$TopTen = '';

	$TS = $infernoshout->vbulletin->db->query_first("select count(*) as `ts` from " . TABLE_PREFIX . "infernoshout");
	$TSN = $TS['ts'];
	$TS = vb_number_format($TS['ts']);

	$T4 = $infernoshout->vbulletin->db->query_first("select count(*) as `T4` from " . TABLE_PREFIX . "infernoshout where s_time > " . (TIMENOW - (60 * 60 * 24)));
	$T4 = vb_number_format($T4['T4']);

	$TY = $infernoshout->vbulletin->db->query_first("select count(*) as `TY` from " . TABLE_PREFIX . "infernoshout where s_user = '{$vbulletin->userinfo['userid']}'");
	$TY = vb_number_format($TY['TY']);

	if (!$cansearch)
	{
		unset($_REQUEST['search']);
	}

	// Are we searching?
	if ($_REQUEST['search'])
	{
		// Sanitise search parameters
		$vbulletin->input->clean_array_gpc('r', array(
			'search'	=> TYPE_ARRAY_NOHTML,
		));

		if ($vbulletin->GPC['search']['time'] < 1)
		{
			$vbulletin->GPC['search']['time'] = 1;
		}

		$search = array(
			'phrase'	=> addslashes($vbulletin->GPC['search']['phrase']),
			'username'	=> addslashes($vbulletin->GPC['search']['username']),
			'time'		=> intval($vbulletin->GPC['search']['time']),
			'sort'		=> $vbulletin->GPC['search']['sort'] == 'new' ? 'new' : 'old',
		);

		$search_time = TIMENOW - ((60 * 60) * $search['time']);

		// Re-calculate total shout results
		$TSN = $infernoshout->vbulletin->db->query("
			select u.userid, s.sid
			from " . TABLE_PREFIX . "infernoshout s
			left join " . TABLE_PREFIX . "user u on(u.userid = s.s_user)
			where s.s_shout like '%{$search['phrase']}%'
			and u.username like '%" . htmlspecialchars_uni($search['username']) . "%'
			and s.s_time > $search_time
		");
		$TSN = $infernoshout->vbulletin->db->num_rows($TSN);
	}

	sanitize_pageresults($TSN, $page, $perpage, 40, 10);

	$limitlower = ($page - 1) * $perpage + 1;
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}

	$TT = $infernoshout->vbulletin->db->query('
			select s.*, count(s.sid) as `TS`, u.username, u.usergroupid from '.TABLE_PREFIX.'infernoshout s
			left join '.TABLE_PREFIX.'user u on (u.userid = s.s_user)
			where u.userid > 0
			group by s.s_user having TS > 0
			order by `TS` desc limit ' . intval($vbulletin->options['ishout_topshouters']));

	while ($TTS = $infernoshout->vbulletin->db->fetch_array($TT))
	{
		$TTS['username'] = fetch_musername($TTS, 'usergroupid');
		eval('$TopTen .= "' . fetch_template('inferno_shoutbox_archive_topshouter') . '";');
	}

	$top_shouter_num = $vbulletin->db->num_rows($TT);

	$infernoshout->load_engine('shout');
	$shout =& new shout;

	$shouthtml = $infernoshout->fetch_shouts($shout, '' . ($limitlower - 1) . ',' . $perpage, true, $_REQUEST['search'] ? $search : false);

	$pagenav = construct_page_nav($page, $perpage, $TSN, 'infernoshout.php?' . $vbulletin->session->vars['sessionurl'] . 'do=archive', ''
		. (!empty($vbulletin->GPC['perpage'])			? "&amp;pp=$perpage" : '')
		. (!empty($vbulletin->GPC['search']['phrase'])		? "&amp;search[phrase]={$vbulletin->GPC['search']['phrase']}" : '')
		. (!empty($vbulletin->GPC['search']['username'])	? "&amp;search[username]={$vbulletin->GPC['search']['username']}" : '')
		. (!empty($vbulletin->GPC['search']['time'])		? "&amp;search[time]={$vbulletin->GPC['search']['time']}" : '')
		. (!empty($vbulletin->GPC['search']['sort'])		? "&amp;search[sort]={$vbulletin->GPC['search']['sort']}" : '')
	);

	// Are we searching?
	if ($_REQUEST['search'])
	{
		// Sanitise search parameters
		$search['phrase']	= stripslashes($search['phrase']);
		$search['username']	= stripslashes($search['username']);
	}

	eval('$HTML = "' . fetch_template('inferno_shoutbox_archive') . '";');
}

# ------------------------------------------------------- #
# Display the control panel/options
# ------------------------------------------------------- #

if ($_REQUEST['do'] == 'options')
{
	$perpage	= $vbulletin->input->clean_gpc('r', 'perpage', TYPE_UINT);
	$page		= $vbulletin->input->clean_gpc('r', 'page', TYPE_UINT);
	$navbits	= array("infernoshout.php?" . $vbulletin->session->vars['sessionurl'] . "do=options" => 'Настройки Чатика');
	$navbits[""]	= 'Ваши опции чатика';

	$infernoshout->load_engine('settings');

	$settings =& new shout_settings;
	$SETTING = '';

	switch ($_REQUEST['area'])
	{
		case 'ignore':
		{
			$settings->ignore();
		}
		break;

		case 'commands':
		{
			$settings->commands();
		}
		break;

		default:
		{
			$settings->filters();
		}
		break;
	}

	eval('$HTML = "' . fetch_template('inferno_shoutbox_cp') . '";');
}

# ------------------------------------------------------- #
# Display the detached shoutbox
# ------------------------------------------------------- #

if ($_REQUEST['do'] == 'detach')
{
	$infernoshout->vbulletin->options['ishout_height'] = $vbulletin->options['ishout_height_detach'];
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

	$HTML = $infernoshout->box;

	unset($infernoshout->box);
	eval('print_output("' . fetch_template('inferno_shoutbox_detach') . '");');
}

$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('GENERIC_SHELL') . '");');
?>