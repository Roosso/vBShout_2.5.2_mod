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



error_reporting(E_ALL & ~E_NOTICE);

$phrasegroups = array('logging');
$specialtemplates = array();

require_once('./global.php');

log_admin_action();

if ($_REQUEST['do'] != 'snapshot')
{
	print_cp_header('Inferno Shoutbox Log');
}
else
{
	//header("Content-type: text/plain;");
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'choose';
}

if ($_REQUEST['do'] == 'snapshot')
{
	if (!$snapshot = $vbulletin->db->query_first("select * from " . TABLE_PREFIX . "infernoshoutlog where lid = '" . intval($_REQUEST['id']) . "'"))
	{
		echo 'Snapshot was not found in database!';
		exit;
	}

	echo "<pre>";

	$snapshot = unserialize(stripslashes($snapshot['l_message']));

	foreach ($snapshot as $shout)
	{
		echo "[" . vbdate($vbulletin->options['logdateformat'], $shout['s_time']) . "]	{$shout['username']}: " . htmlspecialchars_uni($shout['s_shout']) . "\n";
	}

	echo "</pre><noscript>";
}

if ($_REQUEST['do'] == 'choose')
{
	$logtypes = array(
		'all'		=> 'All Log Types',
		'edit'		=> 'Edited Shouts',
		'delete'	=> 'Deleted Shouts',
		'ban'		=> 'Ban and Unban Commands',
		'silence'	=> 'Silence and Unsilence Commands',
		'notice'	=> 'Notice Commands',
		'prune'		=> 'Prune Commands',
		'pruneuser'	=> 'Prune User Commands',
	);

	print_form_header('infernoshoutlog', 'view');
	print_table_header('Inferno Shoutbox Log Viewer');
	print_select_row('Log Entries To View', 'logtype', $logtypes);
	print_input_row($vbphrase['log_entries_to_show_per_page'], 'perpage', 15);
	print_input_row('Log Entries Only By User: ', 'username', '');
	print_time_row($vbphrase['start_date'], 'startdate', 0, 0);
	print_time_row($vbphrase['end_date'], 'enddate', 0, 0);
	print_select_row($vbphrase['order_by'], 'orderby', array('date' => $vbphrase['date'], 'user' => $vbphrase['username']), 'date');
	print_submit_row($vbphrase['view'], 0);
}

if ($_REQUEST['do'] == 'view')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'	=> TYPE_UINT,
		'pg'		=> TYPE_UINT,
		'orderby'	=> TYPE_NOHTML,
		'logtype'	=> TYPE_NOHTML,
		'startdate'	=> TYPE_UNIXTIME,
		'enddate'	=> TYPE_UNIXTIME,
		'username'	=> TYPE_STR,
	));

	$vbulletin->GPC['pagenumber'] = $vbulletin->GPC['pg'];

	if ($vbulletin->GPC['pagenumber'] < 1)
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}

	if ($vbulletin->GPC['perpage'] < 1)
	{
		$vbulletin->GPC['perpage'] = 15;
	}

	$sql_conditions = array('1=1');

	if ($vbulletin->GPC['username'])
	{
		if ($user = $vbulletin->db->query_first("select userid from " . TABLE_PREFIX . "user where username='" . htmlspecialchars_uni(addslashes($vbulletin->GPC['username'])) . "'"))
		{
			$sqlconds[] = "u.userid = {$user[userid]}";
		}
	}

	if ($vbulletin->GPC['startdate'])
	{
		$sqlconds[] = "log.l_time >= " . $vbulletin->GPC['startdate'];
	}

	if ($vbulletin->GPC['enddate'])
	{
 		$sqlconds[] = "log.l_time <= " . $vbulletin->GPC['enddate'];
	}

	if ($vbulletin->GPC['logtype'] == 'all')
	{
		$sqlconds[] = "log.l_type <> 'snapshot'";
	}
	else if ($vbulletin->GPC['logtype'] == 'ban')
	{
		$sqlconds[] = "log.l_type in ('ban', 'unban')";
	}
	else if ($vbulletin->GPC['logtype'] == 'silence')
	{
		$sqlconds[] = "log.l_type in ('silence', 'unsilence')";
	}
	else
	{
		$sqlconds[] = "log.l_type = '{$vbulletin->GPC['logtype']}'";
	}

	if ($vbulletin->GPC['orderby'] == 'date')
	{
		$sqlorder = 'log.l_time desc';
	}
	else
	{
		$sqlorder = 'u.username asc';
	}

	$countget = $vbulletin->db->query("
		select log.*, u.username
		from " . TABLE_PREFIX . "infernoshoutlog log
		left join " . TABLE_PREFIX . "user u on (u.userid = log.l_user)
		where " . implode(' and ', $sqlconds) . "
	");
	$count = array();
	$count['total'] = $vbulletin->db->num_rows($countget);

	$totalpages	= ceil($count['total'] / $vbulletin->GPC['perpage']);
	$startat	= ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'];

	$logs = $vbulletin->db->query("
		select log.*, u.username
		from " . TABLE_PREFIX . "infernoshoutlog log
		left join " . TABLE_PREFIX . "user u on (u.userid = log.l_user)
		where " . implode(' and ', $sqlconds) . "
		order by $sqlorder
	");

	if ($vbulletin->db->num_rows($logs))
	{

		if ($vbulletin->GPC['pagenumber'] != 1)
		{
			$prv = $vbulletin->GPC['pagenumber'] - 1;
			$firstpage = make_nav_button(
				'&laquo; ' . $vbphrase['first_page'],
				array(
					'pp'		=> $vbulletin->GPC['perpage'],
					'pg'		=> 1,
					'orderby'	=> $vbulletin->GPC['orderby'],
					'logtype'	=> $vbulletin->GPC['logtype'],
					'username'	=> $vbulletin->GPC['username'],
				)
			);

			$prevpage = make_nav_button(
				'&lt; ' . $vbphrase['prev_page'],
				array(
					'pp'		=> $vbulletin->GPC['perpage'],
					'pg'		=> $prv,
					'orderby'	=> $vbulletin->GPC['orderby'],
					'logtype'	=> $vbulletin->GPC['logtype'],
					'username'	=> $vbulletin->GPC['username'],
				)
			);
		}

		if ($vbulletin->GPC['pagenumber'] != $totalpages)
		{
			$nxt = $vbulletin->GPC['pagenumber'] + 1;

			$nextpage = make_nav_button(
				$vbphrase['next_page'] . ' &gt;',
				array(
					'pp'		=> $vbulletin->GPC['perpage'],
					'pg'		=> $nxt,
					'orderby'	=> $vbulletin->GPC['orderby'],
					'logtype'	=> $vbulletin->GPC['logtype'],
					'username'	=> $vbulletin->GPC['username'],
				)
			);

			$lastpage = make_nav_button(
				$vbphrase['last_page'] . ' &raquo;',
				array(
					'pp'		=> $vbulletin->GPC['perpage'],
					'pg'		=> $totalpages,
					'orderby'	=> $vbulletin->GPC['orderby'],
					'logtype'	=> $vbulletin->GPC['logtype'],
					'username'	=> $vbulletin->GPC['username'],
				)
			);
		}

		print_form_header('infernoshoutlog', 'remove');
		print_table_header('Inferno Shoutbox Log Viewer (page ' . vb_number_format($vbulletin->GPC['pagenumber']) . '/' . vb_number_format($totalpages) . ') | Total Logs: ' . vb_number_format($count['total']), 5);

		$headings = array(
			'Type',
			'Username',
			'Date',
			'Log',
			'IP Address'
		);
		print_cells_row($headings, 1);

		while ($log = $db->fetch_array($logs))
		{
			$cell = array();
			$cell[] = '<span class="smallfont">' . $log['l_type'] . '</span>';
			$cell[] = '<span class="smallfont">' . $log['username'] . '</span>';
			$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['logdateformat'], $log['l_time']) . '</span>';
			$cell[] = '<span class="smallfont">' . parse_message($log['l_message']) . '</span>';
			$cell[] = '<span class="smallfont">' . iif($log['l_ip'], "<a href=\"usertools.php?" . $vbulletin->session->vars['sessionurl'] . "do=gethost&ip=$log[l_ip]\">$log[l_ip]</a>", '&nbsp;') . '</span>';
			print_cells_row($cell);
		}

		print_table_footer(5, "$firstpage $prevpage &nbsp; $nextpage $lastpage");
	}
}

function parse_message($message)
{
	while (preg_match("#(<snapshot>(\d+)</snapshot>)#", $message, $bits))
	{
		$snapshot = $bits[2];

		$message = str_replace($bits[0], " (<a href='infernoshoutlog.php?do=snapshot&amp;id=$snapshot' target='_blank'>View Shoutbox Snapshot</a>)", $message);
	}

	return str_replace(
		array(
			'<box>',
			'</box>',
		),
		array(
			'<div style="text-align: left; border: 1px red solid; margin-top: 3px; padding: 3px;">',
			'</div>',
		),
		'<div style="text-align: left;">' . $message . '</div>'
	);
}

function make_nav_button($value, $inlinkattrs = array())
{
	$linkattrs = array();

	foreach ($inlinkattrs as $frag => $fvalue)
	{
		if ($fvalue != '')
		{
			$linkattrs[] = "$frag=$fvalue";
		}
	}

	$linkattrs = implode('&', $linkattrs);

	return "<input type=\"button\" class=\"button\" value=\"{$value}\" tabindex=\"1\" onclick=\"window.location='infernoshoutlog.php?" . $vbulletin->session->vars['sessionurl'] . "do=view&" . $linkattrs . "'\">";
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 24802 $
|| ####################################################################
\*======================================================================*/
?>