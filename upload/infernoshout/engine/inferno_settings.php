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
 * Extension to shoutbox engine
 * * * * * * * * * * * * * * * * * * * * * * * * * * 
 */

class shout_settings extends infernoshout_engine
{
	var $settings = array();

	function load_settings()
	{
		$this->settings = $this->vbulletin->db->query_first("select * from " . TABLE_PREFIX . "infernoshoutusers where s_user='{$this->vbulletin->userinfo['userid']}'");		

		if (!is_array($this->settings) || empty($this->settings))
		{
			$this->settings = array();
		}
	}

	function clean_array(&$array)
	{
		if (is_array($array))
		{
			foreach ($array as $key => $data)
			{
				if (trim($data) == '')
				{
					unset($array[$key]);
				}
			}
		}
	}

	function update_ignored()
	{
		$ignore = $_POST['ignore'];

		if (is_array($ignore))
		{
			foreach ($ignore as $key => $username)
			{
				$ignore[$key] = "'" . addslashes(htmlspecialchars_uni($username)) . "'";
			}

			$doignore = array();
			$users = $this->vbulletin->db->query("select userid from " . TABLE_PREFIX . "user where username in(" . implode(',', $ignore) . ")");

			while ($user = $this->vbulletin->db->fetch_array($users))
			{
				$doignore[] = $user['userid'];
			}

			$this->vbulletin->db->query("update " . TABLE_PREFIX . "infernoshoutusers set s_ignored='" . implode(',', $doignore) . "' where s_user='{$this->vbulletin->userinfo['userid']}'");

			if ($this->vbulletin->db->affected_rows() < 1 && !$this->vbulletin->db->query_first("select * from " . TABLE_PREFIX . "infernoshoutusers where s_user='{$this->vbulletin->userinfo['userid']}'"))
			{
				$this->vbulletin->db->query("
					insert into " . TABLE_PREFIX . "infernoshoutusers
					(s_user, s_ignored)
					values
					({$this->vbulletin->userinfo['userid']}, '" . implode(',', $doignore) . "')
				");
			}
		}
	}

	function ignore()
	{
		global $SETTING, $stylevar;

		if ($_REQUEST['update'])
		{
			$this->update_ignored();
		}

		$this->load_settings();

		// Ignorance is bliss
		$ignored = explode(',', $this->settings['s_ignored']);

		if (!is_array($ignored) || empty($ignored) || sizeof($ignored) < 1 || trim($this->settings['s_ignored']) == '')
		{
			$ignored = array(-1);
		}

		$this->clean_array($ignored);

		$already_ignored = '';
		$new_ignore = '';

		$fetch_ignored = $this->vbulletin->db->query("select username from " . TABLE_PREFIX . "user where userid in(" . implode(',', $ignored) . ")");
		while ($ignore = $this->vbulletin->db->fetch_array($fetch_ignored))
		{
			eval('$already_ignored .= "' . fetch_template('inferno_shoutbox_ignore_user') . '";');
		}

		unset($ignore);

		// Let's make a couple of blank fields
		for ($i = 0; $i < 2; $i++)
		{
			eval('$new_ignore .= "' . fetch_template('inferno_shoutbox_ignore_user') . '";');
		}

		eval('$SETTING .= "' . fetch_template('inferno_shoutbox_ignore') . '";');
	}

	function update_filters()
	{
		$filters = array(
			'bbcode'	=> intval($_POST['filters']['bbcode']),
			'pm'		=> intval($_POST['filters']['pm']),
			'notice'	=> intval($_POST['filters']['notice']),
			'me'		=> intval($_POST['filters']['me']),
		);

		$this->vbulletin->db->query("update " . TABLE_PREFIX . "infernoshoutusers set s_filters='" . serialize($filters) . "' where s_user='{$this->vbulletin->userinfo['userid']}'");

		if ($this->vbulletin->db->affected_rows() < 1 && !$this->vbulletin->db->query_first("select * from " . TABLE_PREFIX . "infernoshoutusers where s_user='{$this->vbulletin->userinfo['userid']}'"))
		{
			$this->vbulletin->db->query("
				insert into " . TABLE_PREFIX . "infernoshoutusers
				(s_user, s_filters)
				values
				({$this->vbulletin->userinfo['userid']}, '" . serialize($filters) . "')
			");
		}
	}

	function filters()
	{
		global $SETTING, $stylevar;

		if ($_REQUEST['update'])
		{
			$this->update_filters();
		}

		$this->load_settings();

		$filters = unserialize($this->settings['s_filters']);

		eval('$SETTING .= "' . fetch_template('inferno_shoutbox_filters') . '";');
	}

	function commands()
	{
		global $SETTING, $stylevar;

		if ($_REQUEST['update'])
		{
			$this->update_commands();
		}

		$this->load_settings();

		$commands = unserialize($this->settings['s_commands']);

		$current_comments = $new_commands = '';

		if (is_array($commands))
		{
			foreach ($commands as $command)
			{
				eval('$current_commands .= "' . fetch_template('inferno_shoutbox_commands_row') . '";');
			}
		}

		unset($command);

		for ($c = 0; $c < 2; $c++)
		{
			eval('$new_commands .= "' . fetch_template('inferno_shoutbox_commands_row') . '";');	
		}

		eval('$SETTING .= "' . fetch_template('inferno_shoutbox_commands') . '";');
	}

	function update_commands()
	{
		$commands = array();

		if (is_array($_POST['commands']['input']))
		{
			foreach ($_POST['commands']['input'] as $key => $input)
			{
				if (trim($input != '') && trim($_POST['commands']['output'][$key]) != '')
				{
					$commands[] = array(
						'input'		=> trim(htmlspecialchars_uni($input)),
						'output'	=> trim(htmlspecialchars_uni($_POST['commands']['output'][$key])),
					);
				}
			}
		}

		$this->vbulletin->db->query("update " . TABLE_PREFIX . "infernoshoutusers set s_commands='" . addslashes(serialize($commands)) . "' where s_user='{$this->vbulletin->userinfo['userid']}'");

		if ($this->vbulletin->db->affected_rows() < 1 && !$this->vbulletin->db->query_first("select * from " . TABLE_PREFIX . "infernoshoutusers where s_user='{$this->vbulletin->userinfo['userid']}'"))
		{
			$this->vbulletin->db->query("
				insert into " . TABLE_PREFIX . "infernoshoutusers
				(s_user, s_commands)
				values
				({$this->vbulletin->userinfo['userid']}, '" . serialize($commands) . "')
			");
		}
	}

}
?>