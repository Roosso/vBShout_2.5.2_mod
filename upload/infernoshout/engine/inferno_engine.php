<?php
error_reporting(E_ALL & ~E_NOTICE);

class infernoshout_engine
{
	var $jversion = '2.5.1';
	var $vbulletin;
	var $db;
	var $deploy = false;
	var $box = '';
	var $script = '';
	var $parsebreaker = '<<~~PARSE_^_BREAKER~~>>';
	var $engines = array();
	var $editor_selects = array();

	function infernoshout_engine()
	{
		global $vbulletin;

		$this->vbulletin =& $vbulletin;
		$this->db =& $this->vbulletin->db;
		$this->vbulletin->options['ishout_jversion'] = $this->jversion;

		$this->script = THIS_SCRIPT;
	}

	function trigger_error($error = '', $method = 'unknown')
	{
		echo "<b>Fatal Error</b>: ./infernoshout/engine/inferno_engine.php has encountered a problem and is required to shut down.
		<pre>-----------------------------------------------
Problem encountered in: $method method
System Response: $error
-----------------------------------------------</pre>";

		exit;
	}

	function load_engine($engine = '')
	{
		if (in_array($engine, $this->engines))
		{
			return false;
		}

		if (!file_exists(DIR . '/infernoshout/engine/inferno_' . $engine . '.php'))
		{
			$this->trigger_error("The engine file '{$engine}' is missing from ./infernoshout/engine/", 'load_engine');
		}

		global $output;

		$this->engines[] = $engine;

		require_once(DIR . '/infernoshout/engine/inferno_' . $engine . '.php');
	}

	function setup_deployment()
	{
		global $globaltemplates;

		$this->deploy = true;

		// cache templates

		$globaltemplates = @array_merge(
			array(
				'inferno_shoutbox_box',
				'inferno_shoutbox_box_alt',
				'inferno_shoutbox_editor',
				'inferno_shoutbox_shout',
				'inferno_shoutbox_user',
			),
			$globaltemplates
		);
	}

	function load_editor_settings()
	{
		$this->editor_settings = $this->vbulletin->db->query_first("select * from " . TABLE_PREFIX . "infernoshoutusers where s_user='{$this->vbulletin->userinfo['userid']}'");
	}

	function build_editor_select($selection, $css)
	{
		if (!$this->editor_selects[$selection])
		{
			$this->editor_selects[$selection] = '';

			$selections = explode("\n", $this->vbulletin->options['ishout_' . $selection]);

			$optiontitle = "Default";

			eval('$this->editor_selects["$selection"] .= "' . fetch_template('option') . '";');

			if (is_array($selections))
			{
				foreach ($selections as $option)
				{
					if (($option = trim($option)) != '')
					{
						$optionvalue =& $option;
						$optiontitle =& $option;
						$optionselected = 'style="' . $css . ': ' . $option . ';"' . (($this->editor_settings[($selection == 'colours') ? 's_color' : 's_font'] == $option) ? ' selected="selected"' : '');
						eval('$this->editor_selects["$selection"] .= "' . fetch_template('option') . '";');
					}
				}
			}
		}
	}

	function wrap_tag(&$shout, $option, $tag, $param = false)
	{
		if ($option && trim($option) != '')
		{
			$shout = '[' . $tag . ($param ? '="' . $option . '"' : '') . ']' . $shout . '[/' . $tag . ']';
		}
	}

	function fetch_banned()
	{
		$banned = explode(',', str_replace(' ', '', trim($this->vbulletin->options['ishout_banned'])));

		foreach ($banned as $key => $data)
		{
			if (trim($data) == '')
			{
				unset($banned[$key]);
			}
		}

		if (is_array($banned) && !empty($banned))
		{
			return $banned;
		}

		return array();
	}

	function is_banned()
	{
		if (in_array($this->vbulletin->userinfo['userid'], $this->fetch_banned()) && $this->vbulletin->userinfo['userid'] > 0)
		{
			return true;
		}

		if ($this->is_in_ug_list($this->vbulletin->options['ishout_bannedgroups']))
		{
			return true;
		}

		return false;
	}

	function clean_array(&$array)
	{
		if (is_array($array))
		{
			foreach ($array as $key => $value)
			{
				if (trim($value) == '')
				{
					unset($array[$key]);
				}
			}
		}
	}

	function fetch_filters(&$settings)
	{
		$settings['s_filters'] = unserialize($settings['s_filters']);
	}

	function fetch_shouts(&$shoutobj, $limit = 20, $archive = false, $search_terms = false, $single_only = false)
	{
		$settings =& $this->fetch_user_settings($this->vbulletin->userinfo['userid']);

		$ignored = explode(',', $settings['s_ignored']);
		$ignored[] = '-1';

		$this->clean_array($ignored);

		$ignored = implode(',', $ignored);

		$template = !$archive ? 'inferno_shoutbox_shout' : 'inferno_shoutbox_archive_shout';

		switch ($_REQUEST['fetchtype'])
		{
			case 'pmonly':
			{
				$sqlcond = "and
					(
						(s.s_private = '" . intval($_REQUEST['pmid']) . "' and s.s_user = '{$this->vbulletin->userinfo['userid']}')
						or
						(s.s_private = '{$this->vbulletin->userinfo['userid']}' && s.s_user = '" . intval($_REQUEST['pmid']) . "')
					)";

				$ispmwindow = true;
			}
			break;

			default:
			{
				$sqlcond = '';
			}
		}

		$this->fetch_filters($settings);

		if ($settings['s_filters']['me'])
		{
			$sqlcond .= ' and s.s_me <> \'1\'';
		}

		if ($settings['s_filters']['pm'] && intval($_REQUEST['pmid']) < 1) // don't hide PMs in PM windows
		{
			$sqlcond .= ' and s.s_private = \'-1\'';
		}

		if ($single_only)
		{
			$sqlcond .= ' and s.sid = \'' . $single_only . '\'';
		}

		if ($search_terms)
		{
			$search_time = TIMENOW - ((60 * 60) * $search_terms['time']);
			$sqlcond .= "
				and s.s_shout like '%{$search_terms['phrase']}%'
				and u.username like '%" . htmlspecialchars_uni($search_terms['username']) . "%'
				and s.s_time > $search_time
			";
		}

		$build = '';
		$shouts = $this->vbulletin->db->query("
			select s.*, u.username, u.displaygroupid, u.usergroupid, u.userid, o.*
			from " . TABLE_PREFIX . "infernoshout s
			left join " . TABLE_PREFIX . "user u on (u.userid = s.s_user)
			left join " . TABLE_PREFIX . "infernoshoutusers o on (o.s_user = s.s_user)
			where
			(
				(s.s_private = -1)
				OR
				(s.s_private = '{$this->vbulletin->userinfo['userid']}')
				OR
				(s.s_private <> -1 AND s.s_user = '{$this->vbulletin->userinfo['userid']}')
			)
			and u.userid not in ($ignored)
			and
			(
				o.s_silenced = '0'
				OR
				(o.s_silenced <> '0' AND u.userid = '{$this->vbulletin->userinfo['userid']}')
			)
			$sqlcond
			order by s.s_time " . (($search_terms) ? (($search_terms['sort'] == 'new') ? 'desc' : 'asc') : 'desc') . "
			" . ((trim($limit) != '--nolim--') ? "limit $limit" : '') . "
		");

		if ($this->is_banned())
		{
			$shout = array(
				's_notice'	=> 1,
				's_shout'	=> 'Вы не можете участвовать в чате. Администрация забанила вас.',
				'musername'	=> 'Уведомление',
			);

			$shoutobj->parse($shout['s_shout']);

			eval('$build = "' . fetch_template($template) . '";');

			return $build;
		}

		if ($this->vbulletin->options['ishout_notice'] != '' && !$archive)
		{
			$shout = array(
				's_notice'	=> 1,
				's_shout'	=> $this->vbulletin->options['ishout_notice'],
				'musername'	=> 'Уведомление',
			);

			$shoutobj->parse($shout['s_shout']);

			eval('$build .= "' . fetch_template('inferno_shoutbox_shout') . '";');
		}

		$canadmin = $this->is_in_ug_list($this->vbulletin->options['ishout_admincommands']);

		if (!function_exists('convert_url_to_bbcode'))
		{
			require_once(DIR . '/includes/functions_newpost.php');
		}

		while ($shout = $this->vbulletin->db->fetch_array($shouts))
		{
			if ($this->vbulletin->options['ishout_bbcodes'] & 64)
			{
				$shout['s_shout'] = convert_url_to_bbcode($shout['s_shout']);
			}

			$canmod = $canadmin || $shout['userid'] == $this->vbulletin->userinfo['userid'];

			$this->wrap_tag($shout['s_shout'], $shout['s_bold'], 'b');
			$this->wrap_tag($shout['s_shout'], $shout['s_italic'], 'i');
			$this->wrap_tag($shout['s_shout'], $shout['s_underline'], 'u');
			$this->wrap_tag($shout['s_shout'], $shout['s_font'], 'font', true);
			$this->wrap_tag($shout['s_shout'], $shout['s_color'], 'color', true);

			if ($settings['s_filters']['bbcode'])
			{
				$shout['s_shout'] = strip_bbcode($shout['s_shout']);
			}

			$shoutobj->parse($shout['s_shout']);

			$date = vbdate($this->vbulletin->options['dateformat'], $shout['s_time'], $this->vbulletin->options['yestoday']);
			$time = vbdate($this->vbulletin->options['timeformat'], $shout['s_time'], $this->vbulletin->options['yestoday']);

			fetch_musername($shout);

			$shout['javascript_name'] = addslashes($shout['username']);

			if (!$this->vbulletin->options['ishout_shoutorder'] || $archive)
			{
				eval('$build .= "' . fetch_template($template) . '";');
			}
			else
			{
				eval('$build = "' . fetch_template($template) . '" . $build;');
			}
		}

		if ($this->vbulletin->options['ishout_largertext'])
		{
			$build = str_replace('smallfont', '', $build);
		}

		if ($archive)
		{
			return $build;
		}

		return $build . $this->parsebreaker . $this->fetch_activity();
	}

	function fetch_active_users()
	{
		$cutoff = TIMENOW - (60 * 5);

		$users = $this->vbulletin->db->query("
			select s.sid, u.username, u.userid, u.displaygroupid, u.usergroupid
			from " . TABLE_PREFIX . "infernoshoutsessions s
			left join " . TABLE_PREFIX . "user u on (u.userid = s.s_user)
			where s.s_activity > $cutoff
			group by s_user
		");

		return $users;
	}

	function fetch_users_list()
	{
		$activeusers = array();

		$users = $this->fetch_active_users();
		while ($user = $this->vbulletin->db->fetch_array($users))
		{
			fetch_musername($user);
			eval('$activeusers[] = "' . fetch_template('inferno_shoutbox_user') . '";');
		}

		$total = count($activeusers);
		$activeusers = implode(', ', $activeusers);

		if (trim($activeusers) == '')
		{
			$activeusers = 'Сейчас в чате нет активных пользователей кроме Вас';
		}

		eval('$activeusers = "' . fetch_template('inferno_shoutbox_activeusers') . '";');

		return $activeusers;
	}

	function fetch_activity()
	{
		$total = 0;
		$users = $this->fetch_active_users();
		while ($user = $this->vbulletin->db->fetch_array($users))
		{
			$total++;
		}

		return $total;
	}

	function set_style_properties()
	{
		$colour = $_REQUEST['colour'];
		$fontfamily = $_REQUEST['fontfamily'];
		$bold = intval($_REQUEST['bold']);
		$italic = intval($_REQUEST['italic']);
		$underline = intval($_REQUEST['underline']);

		if (!preg_match("#^[\#a-z0-9\s]+$#i", $colour))
		{
			$colour = '';
		}

		if (!preg_match("#^[\#a-z0-9\s]+$#i", $fontfamily))
		{
			$fontfamily = '';
		}

		$this->vbulletin->db->query("
			update " . TABLE_PREFIX . "infernoshoutusers set
			s_bold='{$bold}',
			s_italic='{$italic}',
			s_underline='{$underline}',
			s_color='{$colour}',
			s_font='{$fontfamily}'

			where s_user = '{$this->vbulletin->userinfo['userid']}';
		");

		if ($this->vbulletin->db->affected_rows() < 1)
		{
			$this->vbulletin->db->query("
				insert into " . TABLE_PREFIX . "infernoshoutusers
				(s_user, s_bold, s_italic, s_underline, s_color, s_font)
				values
				('{$this->vbulletin->userinfo['userid']}', '{$bold}', '{$italic}', '{$underline}', '{$color}', '{$fontfamily}')
			");
		}
	}

	function fetch_user_settings($userid = -1)
	{
		$usersettings = $this->vbulletin->db->query_first("select * from " . TABLE_PREFIX . "infernoshoutusers where s_user='{$userid}'");

		if ($usersettings['s_user'] == '')
		{
			$this->vbulletin->db->query("
				insert into " . TABLE_PREFIX . "infernoshoutusers
				(s_user)
				values
				('{$userid}')
			");

			$usersettings = array('s_user' => $userid);
		}

		return $usersettings;
	}

	function is_in_ug_list($list, $userinfo = false)
	{
		if (!$userinfo)
		{
			$userinfo =& $this->vbulletin->userinfo;
		}

		$groups = explode(',', $list);

		if (is_array($groups))
		{
			foreach ($groups as $ug)
			{
				if (is_member_of($userinfo, $ug))
				{
					return true;
				}
			}
		}

		return false;
	}

	function fetch_edit_shout($shoutid)
	{
		$shout = $this->vbulletin->db->query_first("select * from " . TABLE_PREFIX . "infernoshout where sid='{$shoutid}'");

		if (!$this->is_in_ug_list($this->vbulletin->options['ishout_admincommands']) && $shout['s_user'] != $this->vbulletin->userinfo['userid'])
		{
			$this->xml_document('deny');
		}

		echo $this->xml_document($shout['s_shout'] . $this->parsebreaker . $shout['sid']);
	}

	function do_edit_shout($shoutid)
	{
		$shout = addslashes(convert_urlencoded_unicode(trim($this->vbulletin->GPC['shout'])));
		$extra = '';

		if (!$this->is_in_ug_list($this->vbulletin->options['ishout_admincommands']))
		{
			$extra = "and s_user='{$this->vbulletin->userinfo['userid']}'";
		}

		if ($this->vbulletin->options['ishout_logging'])
		{
			$old = $this->vbulletin->db->query_first("select s.*, u.username from " . TABLE_PREFIX . "infernoshout s left join " . TABLE_PREFIX . "user u on(u.userid = s.s_user) where s.sid='{$shoutid}'");
			$this->load_engine('log');

			$log = new log;

			if ($_POST['delete'])
			{
				$log->log_action(
					"Shout Deleted (Shouter: " . $old['username'] . ") <box>Shout: {$old['s_shout']}</box>",
					'delete'
				);
			}
			else
			{
				$log->log_action(
					"Shout Edited (Shouter: " . $old['username'] . ") <box>Previous: {$old['s_shout']}</box><box>New: $shout</box>",
					'edit'
				);
			}
		}

		if ($shout != '' && $_POST['delete'] != 1)
		{
			$this->vbulletin->db->query_first("update " . TABLE_PREFIX . "infernoshout set s_shout='{$shout}' where sid='{$shoutid}' $extra");
		}
		else if ($_POST['delete'] == 1)
		{
			$this->vbulletin->db->query_first("delete from " . TABLE_PREFIX . "infernoshout where sid='{$shoutid}' $extra");
		}

		$this->load_engine('shout');
		$shout = new shout;

		$shout->update_activity();

		echo 'completed';
	}

	function fetch_smilies($show = 1)
	{
		$show = intval($show);

		if ($show < 1)
		{
			$show = 1;
		}

		$smilies = array();
		$frame = '<img src="%s" alt="%s" onclick="InfernoShoutbox.append_smilie(\'%s\');" onmouseover="this.style.cursor = \'pointer\';" />';

		$fetchsmilies = $this->vbulletin->db->query("select * from " . TABLE_PREFIX . "smilie order by RAND() limit 0,$show");

		while ($smilie = $this->vbulletin->db->fetch_array($fetchsmilies))
		{
			$smilies[] = sprintf($frame, $smilie['smiliepath'], $smilie['title'], $smilie['smilietext']);
		}

		if (is_array($smilies))
		{
			return implode(' ', $smilies);
		}
		else
		{
			return 'Не найдено ни одного смайла.';
		}
	}

	function xml_document($data)
	{
		@header( "Content-type: text/xml;charset=".$this->vbulletin->userinfo['lang_charset'] );
		
		$xmldoc = '
		<?xml version="1.0" encoding="windows-1251"?>
		<inferno>
			<data><![CDATA[' . $data . ']]></data>
		</inferno>
		';

		return trim($xmldoc);
	}
}

global $infernoshout;

if (!isset($infernoshout))
{
	$infernoshout = new infernoshout_engine;
}
?>