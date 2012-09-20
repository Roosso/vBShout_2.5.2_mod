/**
 * Inferno Shoutbox Javascript Engine
 * Created By Inferno Technologies
 * All Rights Reserved
 * * * * * * * * * * * * * * *
 */

_ishout = function()
{
	this.newestbottom = 0;
	this.script = '';
	this.editor = '';
	this.loader = '';
	this.notice = '';
	this.parsebreaker = '<<~~PARSE_^_BREAKER~~>>';
	this.activity = '';
	this.noticemessage = '';
	this.shoutframe = '';
	this.userframe = '';
	this.styleprops = '';
	this.styleprop = new Object;
	this.fetchshout = new Object;
	this.fetchusers = new Object;
	this.editshout = new Object;
	this.fetchsmilies = new Object;
	this.fetchashout = new Object;
	this.fetchaop = new Object;
	this.editshoutform = '';
	this.editshouteditor = '';
	this.smiliesbox = '';
	this.smiliesrow = '';
	this.shout = new Object;
	this.idle = false;
	this.idletimelimit = 0;
	this.loaded = false;
	this.idletime = 0;
	this.floodtime = 0;
	this.fetchingsmilies = false;
	this.showing = 'shoutbox';
	this.detached = false;
	this.aop = false;
	this.aoptime = -1;
	this.refreshspeed = 0;
	this.styleproperties = {
		'fontWeight' : ['bold', false],
		'textDecoration' : ['underline', false],
		'fontStyle' : ['italic', false]
	};
	this.initiate = function(thisscript, idletime, dobold, doitalic, dounderline, newestbottom, floodtime, shoutheight, refreshspeed)
	{
		this.newestbottom = parseInt(newestbottom);
		this.editor = fetch_object('vbshout_pro_shoutbox_editor');
		this.notice = fetch_object('shoutbox_notice');
		this.noticemessage = fetch_object('shoutbox_notice_message');
		this.userframe = fetch_object('shoutbox_users_frame');
		this.editshouteditor = fetch_object('shoutbox_editshout');
		this.editshoutform = document.forms['editshoutform'];
		this.smiliesbox = fetch_object('shoutbox_smilies');
		this.smiliesrow = fetch_object('shoutbox_smilies_row');
		this.tabs = fetch_object('vbshout_pro_tabs');
		this.shoutwindow = fetch_object('shoutbox_window');
		this.tabhistory = new Array();
		this.shoutheight = shoutheight;
		this.refresh_speed = refreshspeed;

		this.append_tab('<a href="?" onclick="return InfernoShoutbox.show(\'shoutbox\');">Чат</a>');
		this.append_tab('<a href="?" onclick="return InfernoShoutbox.show(\'activeusers\');">Активные пользователи</a>: <span id="shoutbox_activity">0</span>');

		this.activity = fetch_object('shoutbox_activity');
		this.floodtime = floodtime;
		this.script = thisscript;
		this.load_editor_settings(parseInt(dobold), parseInt(doitalic), parseInt(dounderline));
		this.idletimelimit = idletime;
		this.idlecheck();

		this.set_shout_params('shoutbox_frame', '', '', '');

		this.fetch_shouts();

		if (this.aop)
		{
			this.get_shouts = setInterval("InfernoShoutbox.fetch_aop();", refreshspeed);
		}
		else
		{
			this.get_shouts = setInterval("InfernoShoutbox.fetch_shouts();", refreshspeed);
		}
	}

	this.load_editor_settings = function(dobold, doitalic, dounderline)
	{
		if (fetch_object('sb_color_mem'))
		{
			this.adjust_property_selection('color', fetch_object('sb_color_mem'), false);
		}

		if (fetch_object('sb_font_mem'))
		{
			this.adjust_property_selection('fontFamily', fetch_object('sb_font_mem'), false);
		}

		if (dobold && fetch_object('sb_mem_bold'))
		{
			this.adjust_property('fontWeight', this.styleproperties['fontWeight'][0], fetch_object('sb_mem_bold'), false);
		}

		if (doitalic && fetch_object('sb_mem_italic'))
		{
			this.adjust_property('fontStyle', this.styleproperties['fontStyle'][0], fetch_object('sb_mem_italic'), false);
		}

		if (dounderline && fetch_object('sb_mem_underline'))
		{
			this.adjust_property('textDecoration', this.styleproperties['textDecoration'][0], fetch_object('sb_mem_underline'), false);
		}
	}

	this.clear = function()
	{
		this.editor.value = '';
	}

	this.assign_styleprop = function(prop, sobj)
	{
		switch (prop)
		{
			case 'fontWeight':
			case 'textDecoration':
			case 'fontStyle':
			{
				this.adjust_property(prop, this.styleproperties[prop][0], sobj, true);
				return false;
			}

			case 'color':
			case 'fontFamily':
			{
				this.adjust_property_selection(prop, sobj, true);
				return false;
			}
		}
	}

	this.adjust_property_selection = function(property, sobj, update)
	{
		value = sobj.options[sobj.options.selectedIndex].value;

		if (!this.styleproperties[property])
		{
			this.styleproperties[property] = [];
		}

		this.styleproperties[property][1] = value;

		eval('this.editor.style.' + property + ' = "' + value + '";');

		if (update)
		{
			this.update_styleprops();
		}
	}

	this.adjust_property = function(property, value, sobj, update)
	{
		sobj.value = sobj.value + '*';

		if (this.styleproperties[property][1])
		{
			value = '';
			sobj.value = sobj.value.replace(/\*/g, '');
		}

		this.styleproperties[property][1] = !this.styleproperties[property][1];

		eval('this.editor.style.' + property + ' = "' + value + '";');

		if (update)
		{
			this.update_styleprops();
		}
	}

	this.set_shout_params = function(windowid, shoutprefix, shoutsuffix, fetchtype)
	{
		this.shout_params = new Object;
		this.shout_params.prefix = shoutprefix;
		this.shout_params.suffix = shoutsuffix;
		this.shout_params.fetchtype = fetchtype;

		this.shoutframe = fetch_object(windowid);
	}

	this.shout = function()
	{
		if (this.posting_shout)
		{
			this.show_notice('Ваще предыдущее сообщение ещё не обработано сервером.');

			return false;
		}

		if (this.idle)
		{
			this.hide_notice();
		}

		this.idle = false;
		this.idletime = 0;

		message = PHP.trim(this.editor.value);

		if (message == '')
		{
			this.show_notice('Сначала надо ввести сообщение!');

			return false;
		}

		message = this.shout_params.prefix + message + this.shout_params.suffix;

		this.posting_shout = true;

		this.set_loader('');

		this.shout.ajax = new vB_AJAX_Handler(true);
		this.shout.ajax.onreadystatechange(InfernoShoutbox.shout_posted);
		this.shout.ajax.send('infernoshout.php', 'do=shout&message=' + PHP.urlencode(message));

		this.clear();

		return false;
	}

	this.unidle = function()
	{
		this.idletime = 0;
		this.idle = false;
		this.hide_notice();

		return false;
	}

	this.idlecheck = function()
	{
		if (this.idle || this.idletime > this.idletimelimit)
		{
			setTimeout("InfernoShoutbox.idlecheck()", 1000);
			return false;
		}

		this.idletime++;

		if (this.idletime > this.idletimelimit)
		{
			this.idle = true;
		}

		setTimeout("InfernoShoutbox.idlecheck()", 1000);
	}

	this.shout_posted = function()
	{
		ajax = InfernoShoutbox.shout.ajax;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			if (PHP.trim(ajax.handler.responseText) == 'completed')
			{
				InfernoShoutbox.force_fetch = true;
				InfernoShoutbox.fetch_shouts();
			}
			else if(PHP.trim(ajax.handler.responseText) == 'flood')
			{
				InfernoShoutbox.show_notice('Вы должны подождать ' + InfernoShoutbox.floodtime + ' секунд перед повторной отправкой.');
				InfernoShoutbox.posting_shout = false;
			}
			else
			{
				InfernoShoutbox.show_notice('Выше сообщение не доставлено.');
				InfernoShoutbox.posting_shout = false;
			}
		}
	}

	this.update_styleprops = function()
	{
		this.set_loader('');

		var bold	= this.styleproperties['fontWeight'][1] ? 1 : 0;
		var italic	= this.styleproperties['fontStyle'][1] ? 1 : 0;
		var underline	= this.styleproperties['textDecoration'][1] ? 1 : 0;
		var colour	= '';
		var fontfamily	= '';

		if (this.styleproperties['color'])
		{
			colour = this.styleproperties['color'][1];
		}

		if (this.styleproperties['fontFamily'])
		{
			fontfamily = this.styleproperties['fontFamily'][1];
		}

		styleproperties = new Array(
			'bold=' + bold,
			'italic=' + italic,
			'underline=' + underline,
			'colour=' + colour,
			'fontfamily=' + fontfamily
		);

		styleproperties = styleproperties.join('&');

		this.styleprop.ajax = new vB_AJAX_Handler(true);
		this.styleprop.ajax.onreadystatechange(InfernoShoutbox.styleprops_updated);
		this.styleprop.ajax.send('infernoshout.php', 'do=styleprops&' + styleproperties);
	}

	this.styleprops_updated = function()
	{
		ajax = InfernoShoutbox.styleprop.ajax;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			InfernoShoutbox.set_loader('none');
			InfernoShoutbox.show_notice('Ваш стиль общения обновлен.');
		}
	}

	this.fetch_aop = function()
	{
		if (this.fetching_aop)
		{
			if (this.failure_count('fetching_aop'))
			{
				this.fetching_aop = false;
			}

			return false;
		}

		if (InfernoShoutbox.new_aoptime)
		{
			this.fetch_shouts();

			return false;
		}

		this.fetching_aop = true;

		if (!this.fetchaop.ajax)
		{
			this.fetchaop.ajax = new vB_AJAX_Handler(true);
			this.fetchaop.ajax.onreadystatechange(InfernoShoutbox.fetch_aop_done);
		}

		this.fetchaop.ajax.send('infernoshout/aop/aop.php', '');
	}

	this.fetch_aop_done = function()
	{
		ajax = InfernoShoutbox.fetchaop.ajax;
		InfernoShoutbox.fetching_aop = false;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			new_aop_time = parseInt(ajax.handler.responseText);

			if (new_aop_time > InfernoShoutbox.aoptime)
			{
				InfernoShoutbox.new_aoptime = new_aop_time;
				InfernoShoutbox.fetch_shouts();
			}
		}
	}

	this.fetch_shouts = function()
	{
		if (this.posting_shout && !this.force_fetch)
		{
			if (this.failure_count('posting_shout'))
			{
				this.posting_shout = false;
			}

			return false;
		}

		if (this.fetching_shouts)
		{
			if (this.failure_count('fetching_shouts'))
			{
				this.fetching_shouts = false;
			}

			return false;
		}

		if (this.idle && this.loaded)
		{
			this.show_notice('Вы были неактивны и чат отключился! Нажмите <a href="?" onclick="return InfernoShoutbox.unidle();">здесь</a> что бы обновить окно чата!.');
			clearTimeout(InfernoShoutbox.kill_notice); // Don't hide this notice

			return false;
		}

		this.fetching_shouts = true;
		this.force_fetch = false;
		this.loaded = true;

		this.set_loader('');

		this.fetchshout.ajax = new vB_AJAX_Handler(true);
		this.fetchshout.ajax.onreadystatechange(InfernoShoutbox.fetch_shouts_completed);
		this.fetchshout.ajax.send('infernoshout.php', 'do=messages' + ((this.detached) ? '&detach=true' : '') + '&fetchtype=' + this.shout_params.fetchtype);
	}

	this.fetch_shouts_completed = function()
	{
		ajax = InfernoShoutbox.fetchshout.ajax;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			data = ajax.handler.responseXML.documentElement;
			data = data.getElementsByTagName('data')[0].firstChild.data;
			data = data.split(InfernoShoutbox.parsebreaker);

			InfernoShoutbox.update_shouts(PHP.trim(data[0]));

			if (data[1])
			{
				InfernoShoutbox.activity.innerHTML = PHP.trim(data[1]);
			}

			// Posting a shout now finishes here, when shouts have been refetched
			if (InfernoShoutbox.posting_shout)
			{
				InfernoShoutbox.posting_shout = false;
			}

			InfernoShoutbox.set_loader('none');
			InfernoShoutbox.fetching_shouts = false;

			if (InfernoShoutbox.new_aoptime)
			{
				InfernoShoutbox.aoptime = InfernoShoutbox.new_aoptime;
				InfernoShoutbox.new_aoptime = false;
			}
		}
	}

	this.update_shouts = function(shouts)
	{
		this.shoutframe.innerHTML = '';
		this.shoutframe.innerHTML = shouts;

		if (this.newestbottom && this.shoutframe.scrollTop < this.shoutframe.scrollHeight)
		{
			this.shoutframe.scrollTop = this.shoutframe.scrollHeight;
		}
	}

	this.set_loader = function(set)
	{
		//this.loader.style.display = set;
	}

	this.show_notice = function(message)
	{
		clearTimeout(InfernoShoutbox.kill_notice);
		InfernoShoutbox.kill_notice = setTimeout("InfernoShoutbox.hide_notice()", 5000);

		this.noticemessage.innerHTML = message;
		this.notice.style.display = '';
	}

	this.hide_notice = function()
	{
		this.notice.style.display = 'none';
	}

	this.show = function(what)
	{
		if (what == this.showing)
		{
			return false;
		}

		if (what == 'shoutbox')
		{
			this.goto_pm_window('shoutbox_frame');
			//this.shoutframe.style.display = 'block';
			this.userframe.style.display = 'none';
		}
		else
		{
			this.fetch_users();
			this.shoutframe.style.display = 'none';
			this.userframe.style.display = 'block';
		}

		this.showing = what;

		return false;
	}

	this.fetch_users = function()
	{
		this.set_loader('');

		this.fetchusers.ajax = new vB_AJAX_Handler(true);
		this.fetchusers.ajax.onreadystatechange(InfernoShoutbox.fetch_users_completed);
		this.fetchusers.ajax.send('infernoshout.php', 'do=userlist');
	}

	this.fetch_users_completed = function()
	{
		ajax = InfernoShoutbox.fetchusers.ajax;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			InfernoShoutbox.userframe.innerHTML = ajax.handler.responseText;
			InfernoShoutbox.set_loader('none');
		}
	}

	this.pm_user = function(username)
	{
		this.editor.value = '/pm ' + username + '; Your message here';

		return false;
	}

	this.edit_shout = function(shoutid)
	{
		this.posting_shout = true;

		this.editshout.ajax = new vB_AJAX_Handler(true);
		this.editshout.ajax.onreadystatechange(InfernoShoutbox.edit_shout_fetched);
		this.editshout.ajax.send('infernoshout.php', 'do=editshout&shoutid=' + shoutid);

		this.set_loader('');

		return false;
	}

	this.edit_shout_fetched = function()
	{
		ajax = InfernoShoutbox.editshout.ajax;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			InfernoShoutbox.set_loader('none');

			data = ajax.handler.responseXML.documentElement;
			data = data.getElementsByTagName('data')[0].firstChild.data;

			if (data != 'deny')
			{
				InfernoShoutbox.show_edit_shout(data.split(InfernoShoutbox.parsebreaker));
			}
			else
			{
				InfernoShoutbox.posting_shout = false;
			}
		}
	}

	this.show_edit_shout = function(data)
	{
		theshout = PHP.trim(data[0]);
		shoutid = parseInt(data[1]);

		if (this.archive)
		{
			fetch_object('shout_' + shoutid).style.display = 'none';
			fetch_object('shout_edit_' + shoutid).style.display = '';
			fetch_tags(fetch_object('shout_edit_' + shoutid), 'input')[0].value = this.unescapeHTML(theshout);

			if (theshout.length < 60)
			{
				fetch_tags(fetch_object('shout_edit_' + shoutid), 'input')[0].size = (theshout.length + 1);
			}
			else
			{
				fetch_tags(fetch_object('shout_edit_' + shoutid), 'input')[0].size = 60;
			}
		}
		else
		{
			this.editshouteditor.style.display = '';
			this.editshoutform.shoutid.value = shoutid;
			this.editshoutform.editshout.value = this.unescapeHTML(theshout);
		}
	}

	this.cancel_edit_shout = function(shoutid)
	{
		if (this.archive)
		{
			fetch_object('shout_' + shoutid).style.display = '';
			fetch_object('shout_edit_' + shoutid).style.display = 'none';

			return false;
		}
		else
		{
			this.editshouteditor.style.display = 'none';
			this.posting_shout = false;
		}
	}

	this.archive_edit_shout = function(shoutid, dodelete)
	{
		if (typeof dodelete != 'undefined')
		{
			dodelete = 1;
		}
		else
		{
			dodelete = 0;
		}

		shout = PHP.urlencode(PHP.trim(fetch_tags(fetch_object('shout_edit_' + shoutid), 'input')[0].value));

		this.editshout.ajax = new vB_AJAX_Handler(true);
		this.editshout.ajax.shoutid = shoutid;
		this.editshout.ajax.dodelete = dodelete;
		this.editshout.ajax.onreadystatechange(InfernoShoutbox.edit_shout_done);
		this.editshout.ajax.send('infernoshout.php', 'do=doeditshout&shoutid=' + shoutid + '&shout=' + shout + '&delete=' + dodelete);

		return false;
	}

	this.do_edit_shout = function(dodelete)
	{
		if (typeof dodelete != 'undefined')
		{
			dodelete = 1;
		}
		else
		{
			dodelete = 0;
		}

		shout = PHP.urlencode(PHP.trim(this.editshoutform.editshout.value));
		shoutid = parseInt(this.editshoutform.shoutid.value);

		this.editshout.ajax = new vB_AJAX_Handler(true);
		this.editshout.ajax.shoutid = shoutid;
		this.editshout.ajax.onreadystatechange(InfernoShoutbox.edit_shout_done);
		this.editshout.ajax.send('infernoshout.php', 'do=doeditshout&shoutid=' + shoutid + '&shout=' + shout + '&delete=' + dodelete);

		this.editshouteditor.style.display = 'none';
		this.set_loader('');

		return false;
	}

	this.fetch_archive_shout = function(shoutid)
	{
		this.fetchashout.ajax = new vB_AJAX_Handler(true);
		this.fetchashout.ajax.shoutid = shoutid;
		this.fetchashout.ajax.onreadystatechange(InfernoShoutbox.fetch_archive_shout_done);
		this.fetchashout.ajax.send('infernoshout.php', 'do=getarchiveshout&shoutid=' + shoutid);
	}

	this.fetch_archive_shout_done = function()
	{
		ajax = InfernoShoutbox.fetchashout.ajax;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			data = ajax.handler.responseXML.documentElement;
			data = data.getElementsByTagName('data')[0].firstChild.data;

			fetch_object('shout_shell_' + ajax.shoutid).innerHTML = data;
		}
	}

	this.edit_shout_done = function()
	{
		ajax = InfernoShoutbox.editshout.ajax;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			if (InfernoShoutbox.archive)
			{
				if (!ajax.dodelete)
				{
					InfernoShoutbox.fetch_archive_shout(ajax.shoutid);
				}
				else
				{
					fetch_object('shout_row_' + ajax.shoutid).parentNode.removeChild(fetch_object('shout_row_' + ajax.shoutid));
				}
			}
			else
			{
				InfernoShoutbox.set_loader('none');
				InfernoShoutbox.posting_shout = false;
				InfernoShoutbox.fetch_shouts();
			}
		}
	}

	this.smilies = function()
	{
		if (this.fetchingsmilies)
		{
			return false;
		}

		if (this.smiliesbox.style.display == '')
		{
			this.smiliesbox.style.display = 'none';
			return false;
		}

		this.fetchingsmilies = true;
		this.fetchsmilies.ajax = new vB_AJAX_Handler(true);
		this.fetchsmilies.ajax.onreadystatechange(InfernoShoutbox.smilies_fetched);
		this.fetchsmilies.ajax.send('infernoshout.php', 'do=fetchsmilies');
		this.set_loader('');
	}

	this.smilies_fetched = function()
	{
		ajax = InfernoShoutbox.fetchsmilies.ajax;

		if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
		{
			InfernoShoutbox.set_loader('none');
			InfernoShoutbox.fetchingsmilies = false;
			InfernoShoutbox.smiliesbox.style.display = '';
			InfernoShoutbox.smiliesrow.innerHTML = ajax.handler.responseText;
		}
	}

	this.append_smilie = function(code)
	{
		applyto = this.editshouteditor.style.display == '' ? this.editshoutform.editshout : this.editor;

		if (PHP.trim(applyto.value) == '')
		{
			applyto.value = code + ' ';
		}
		else
		{
			spacer = applyto.value.substring(applyto.value.length - 1) == ' ' ? '' : ' ';
			applyto.value += spacer + code + ' ';
		}

		try
		{
			applyto.focus();
		}
		catch(e)
		{
		}
	}

	this.make_curve = function(class_a, class_b)
	{
		curve = '<div class="alt2" style="border: 0px !important; margin: 0px; padding: 0px;">';
		curve += '<span style="float: right; border: 0px !important; margin: 0px; padding: 0px;">';
		curve += '<span class="alt1" style="display: block; border: 0px !important; margin: 0px; padding: 0px;">';
		curve += '<span class="alt2" style="width: 1px; height: 1px; display: block; overflow: hidden; border: 0px !important; margin: 0px; padding: 0px;"></span>';
		curve += '<span class="alt2" style="width: 2px; height: 1px; display: block; overflow: hidden; border: 0px !important; margin: 0px; padding: 0px;"></span>';
		curve += '<span class="alt2" style="width: 3px; height: 1px; display: block; overflow: hidden; border: 0px !important; margin: 0px; padding: 0px;"></span>';
		curve += '</span></span>';
		curve += '<span class="alt1" style="width: 3px; height: 1px; display: block; overflow: hidden; border: 0px !important; margin: 0px; padding: 0px;"></span>';
		curve += '<span class="alt1" style="width: 2px; height: 1px; display: block; overflow: hidden; border: 0px !important; margin: 0px; padding: 0px;"></span>';
		curve += '<span class="alt1" style="width: 1px; height: 1px; display: block; overflow: hidden; border: 0px !important; margin: 0px; padding: 0px;"></span>';
		curve += '</div>';
		curve += '';

		return curve;
	}

	this.append_tab = function(html, canclose)
	{
		if (canclose)
		{
			html += ' [<a href="#" onclick="return InfernoShoutbox.close_tab(this);">X</a>]';
		}

		html = this.make_curve('', '') + '<div class="smallfont" style="text-align: center; white-space: nowrap; padding: 5px; padding-top: 0px; padding-bottom: 4px; background: transparent; margin: 0px; border: 0px !important;">' + html + '</div>';

		cellposition = this.tabs.rows[0].cells.length - 1;
		newtab = this.tabs.rows[0].insertCell(cellposition);
		newtab.className = 'alt2';
		newtab.innerHTML = html;
		newtab.style.cssText = "border: 0px !important; margin: 0px; padding: 0px;";

		if (this.tabhistory[newtab.innerHTML])
		{
			newtab.parentNode.removeChild(newtab);
			return false;
		}

		this.tabhistory[newtab.innerHTML] = 1;

		cellposition = this.tabs.rows[0].cells.length - 1;
		newtab = this.tabs.rows[0].insertCell(cellposition);
		newtab.className = 'alt1';
		newtab.innerHTML = '&nbsp;';
		newtab.style.cssText = "border: 0px !important; margin: 0px; padding: 0px;";
	}

	this.close_tab = function(tabobj)
	{
		this.tabhistory[tabobj.parentNode.parentNode.innerHTML] = 0;

		tabobj.parentNode.parentNode.parentNode.removeChild(tabobj.parentNode.parentNode);

		this.clean_tab_spacing();

		if (this.showing != 'activeusers')
		{
			this.goto_pm_window('shoutbox_frame');
		}

		return false;
	}

	this.clean_tab_spacing = function()
	{
		for (var c = 0; c < this.tabs.rows[0].cells.length; c++)
		{
			if (this.tabs.rows[0].cells[c + 1])	
			{
				if (this.tabs.rows[0].cells[c].innerHTML == this.tabs.rows[0].cells[c + 1].innerHTML && this.tabs.rows[0].cells[c].innerHTML == '&nbsp;')
				{
					this.tabs.rows[0].removeChild(this.tabs.rows[0].cells[c]);
					break;
				}
			}
		}
	}

	this.open_pm_tab = function(pmid, username, userid)
	{
		if (!this.pm_tabs)
		{
			this.pm_tabs = {};
		}

		if (this.pm_tabs[pmid])
		{
			this.goto_pm_window(pmid);
			return false;
		}

		// Create the tab
		this.append_tab('<a href="#" onclick="return InfernoShoutbox.goto_pm_window(\'' + pmid + '\');">' + username + '</a>', 1);

		// Create the window
		this.append_shout_window(pmid, '/pm ' + userid + '; ', '', 'pmonly&pmid=' + pmid.split('_')[1]);

		// Switch to the window
		this.goto_pm_window(pmid);

		return false;
	}

	this.set_default_window = function(windowid)
	{
		this.shout_windows[windowid] = new Object;
		this.shout_windows[windowid].suffix = '';
		this.shout_windows[windowid].prefix = '';
		this.shout_windows[windowid].fetchtype = '';
	}

	this.goto_pm_window = function(windowid)
	{
		if (!this.shout_windows)
		{
			this.shout_windows = {};
			this.set_default_window('shoutbox_frame');
		}

		if (!this.shout_windows[windowid])
		{
			return false;
		}

		this.userframe.style.display = 'none';
		this.shoutframe.style.display = 'none';

		this.set_shout_params(windowid, this.shout_windows[windowid].prefix, this.shout_windows[windowid].suffix, this.shout_windows[windowid].fetchtype);

		this.shoutframe.innerHTML = 'Загрузка...';
		this.shoutframe.style.display = 'block';
		this.showing = windowid;

		if (this.idle)
		{
			this.hide_notice();
		}

		this.idle = false;

		this.fetch_shouts();

		return false;
	}

	this.append_shout_window = function(windowid, shoutprefix, shoutsuffix, fetchtype)
	{
		if (!this.shout_windows)
		{
			this.shout_windows = {};
			this.set_default_window('shoutbox_frame');
		}

		if (this.shout_windows[windowid])
		{
			// This window has already been placed, don't remake it.
			return false;
		}

		this.shout_windows[windowid] = new Object;
		this.shout_windows[windowid].prefix = shoutprefix;
		this.shout_windows[windowid].suffix = shoutsuffix;
		this.shout_windows[windowid].fetchtype = fetchtype;

		swindow = document.createElement('span');
		swindow.style.display = 'none';
		swindow.style.padding = '3px';
		swindow.style.height = parseInt(this.shoutheight) + 'px';
		swindow.style.overflow = 'auto';
		swindow.style.width = '99%';
		swindow.innerHTML = 'Загрузка...';
		swindow.id = windowid;

		this.shoutwindow.appendChild(swindow);
	}

	this.goto_options = function()
	{
		window.location.href = 'infernoshout.php?' + SESSIONURL + 'do=options';
	}

	this.detach = function()
	{
		detachwin = window.open('infernoshout.php?' + SESSIONURL + 'do=detach', '_ishout_detach');
		try
		{
			detachwin.focus();
		}
		catch(e){}
	}

	// AJAX lock-ups...  love 'em =D
	this.failure_count = function(failure_type)
	{
		if (!this.failure_log)
		{
			this.failure_log = new Array();
		}

		if (!this.failure_log[failure_type])
		{
			this.failure_log[failure_type] = 0;
		}

		this.failure_log[failure_type]++;

		if (this.failure_log[failure_type] > 2)
		{
			this.failure_log[failure_type] = 0;
			return true;
		}

		return false;
	}

	this.unescapeHTML = function(html)
	{
		tmpdiv = document.createElement('div');
		tmpdiv.innerHTML = html;

		return tmpdiv.childNodes[0] ? tmpdiv.childNodes[0].nodeValue : '';
	}
}