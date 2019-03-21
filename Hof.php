<?php
/**
 * @package SMF Hall Of Fame (HOF)
 * @author SychO (M.S) http://sycho.22web.org
 * @version 1.2
 * @license Copyright 2019
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 */

// First of all, we make sure we are accessing the source file via SMF so that people can not directly access the file. 
if (!defined('SMF'))
	die('Hack Attempt...');

/**
 * Main Function.
 */
function Hof()
{
	global $context, $scripturl, $txt, $smcFunc, $settings, $modSettings;
	// Load the css file
	$context['html_headers'] = $context['html_headers'].'<link rel="stylesheet" type="text/css" href="'.$settings['default_theme_url'].'/css/hof.css" /><link rel="stylesheet" type="text/css" href="'.$settings['default_theme_url'].'/css/admin.css?fin20" />';
	// Template, Language
	loadTemplate('Hof');
	// Seriously where am I ?
	$context['page_title'] = !empty($modSettings['hof_globalTitle']) ? $modSettings['hof_globalTitle'] : $txt['hof_PageTitle'];
	$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title']));
	$context['linktree'][] = array(
		'url' => $scripturl. '?action=hof',
		'name' => !empty($modSettings['hof_globalTitle']) ? $modSettings['hof_globalTitle'] : $txt['hof_PageTitle'],
	);
	// SubActions
	$subActions = array(
		'admin' => 'HofSettings',
		'edit' => 'editClass',
		'add_class' => 'addClass',
		'remove_class' => 'removeClass',
		'update_class' => 'updateClass',
		'add_famer' => 'addFamer',
		'remove_famer' => 'removeFamer',
		'hofeditSettings' => 'hofeditSettings',
	);
	// Take Me To The SubAction ?
	$sa = !empty($_GET['sa']) ? $smcFunc['htmlspecialchars']($_GET['sa'], ENT_QUOTES) : '';
	if (!empty($sa) && !empty($subActions[$sa]))
		$subActions[$sa]();
	else
		ViewHof();
}

/**
 * Add a Class
 */
function addClass()
{
	global $smcFunc;
	// Are you allowed to be here sir ?
	isAllowedTo('admin_forum');
	// Sanitize posted data
	$title = !empty($_POST['title']) ? $smcFunc['htmlspecialchars']($_POST['title'], ENT_QUOTES) : '';
	$description = !empty($_POST['description']) ? $smcFunc['htmlspecialchars']($_POST['description'], ENT_QUOTES) : '';
	// At least you've chosen a title right ? right ?
	if(!empty($title))
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}hof_classes',
			array(
				'title' => 'string', 'description' => 'string',
			),
			array(
				$title, $description,
			),
			array('id_class')
		);
		redirectexit('action=admin;area=hof;sa=admin;state=success');
	} else
		redirectexit('action=admin;area=hof;sa=admin;state=fail');
}

/**
 * Remove a Class.
 */
function removeClass()
{
	global $smcFunc;
	isAllowedTo('admin_forum');
	// Values.
	$class_id = (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) ? ((int)$_REQUEST['id']) : 0;
	
	if(!empty($class_id))
	{
		// first Delete the class itself
		$smcFunc['db_query']('', "
			DELETE FROM {db_prefix}hof_classes 
			WHERE id_class = {int:id}",
			array(
				'id' => $class_id,
			)
		);
		// Second Delete users that belong to the class
		$smcFunc['db_query']('', "
			DELETE FROM {db_prefix}hof
			WHERE id_class = {int:id}",
			array(
				'id' => $class_id,
			)
		);
		redirectexit('action=admin;area=hof;sa=admin;state=success');
	}
	else
		redirectexit('action=admin;area=hof;sa=admin;state=fail');
}

/**
 * Update a Class.
 */
function updateClass()
{
	global $smcFunc;
	isAllowedTo('admin_forum');
	
	// Sanitize
	$class_id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
	$title = !empty($_POST['title']) ? $smcFunc['htmlspecialchars']($_POST['title'], ENT_QUOTES) : '';
	$description = !empty($_POST['description']) ? $smcFunc['htmlspecialchars']($_POST['description'], ENT_QUOTES) : '';
	
	if(!empty($title) && !empty($class_id!=0)) {
		$smcFunc['db_insert']('replace',
			'{db_prefix}hof_classes',
			array(
				'id_class' => 'int', 'title' => 'string', 'description' => 'string',
			),
			array(
				$class_id, $title, $description,
			),
			array('id_class')
		);
		redirectexit('action=admin;area=hof;sa=admin;state=success');
	} else
		redirectexit('action=admin;area=hof;sa=admin;state=fail');
}

/**
 * Add a Member to a Hall Of Fame Class
 */
function addFamer()
{
	global $smcFunc;
	isAllowedTo('admin_forum');
	// Sanitize
	$member_name = !empty($_POST['famer']) ? $smcFunc['htmlspecialchars']($_POST['famer'], ENT_QUOTES) : '';
	// query
	if(!empty($member_name))
	{
		$query = $smcFunc['db_query']('', "
			SELECT id_member
			FROM {db_prefix}members
			WHERE real_name = {string:member_name}",
			array(
				'member_name' => $member_name,
			)
		);
		$member_id = (int) $smcFunc['db_fetch_assoc']($query)['id_member'];
		$smcFunc['db_free_result']($query);

		// Sanitize Numeric
		$class = !empty($_POST['class']) ? (int)$_POST['class'] : 0;
		$date = !empty($_POST['date']) ? (int)$_POST['date'] : 0;
		// Do they already belong to this class ?
		$query2 = $smcFunc['db_query']('', "
			SELECT COUNT(*) AS count 
			FROM {db_prefix}hof 
			WHERE id_member = {int:id_mem} 
				AND id_class = {int:class}",
			array(
				'id_mem' => $member_id,
				'class' => $class,
			)
		);
		$count = $smcFunc['db_fetch_assoc']($query2)['count'];
		$smcFunc['db_free_result']($query2);
		$duplicate = $count > 0;
		// Don't add people more than once
		if(!empty($member_id) && !empty($date) && !$duplicate)
		{
			$smcFunc['db_insert']('insert',
				'{db_prefix}hof',
				array(
					'id_member' => 'int', 'date_added' => 'int', 'id_class' => 'int',
				),
				array(
					$member_id, $date, $class,
				),
				array('id_member', 'id_class')
			);
			redirectexit('action=admin;area=hof;sa=admin;state=success');
		}
		elseif($duplicate)
			redirectexit('action=admin;area=hof;sa=admin;state=fail');
		else
			redirectexit('action=admin;area=hof;sa=admin;state=fail');
	}
	else redirectexit('action=admin;area=hof;sa=admin;state=fail');
}

/**
 * Remove a Member from a HOF Class.
 */
function removeFamer()
{
	global $smcFunc;
	// no sneaky peaky !
	isAllowedTo('admin_forum');
	// Values.
	$id = (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) ? (int)$_REQUEST['id'] : 0;
	$class = (isset($_REQUEST['class']) && !empty($_REQUEST['class'])) ? (int)$_REQUEST['class'] : 0;
	if(!empty($id) && !empty($class))
	{
		$smcFunc['db_query']('', "
			DELETE FROM {db_prefix}hof
			WHERE id_member = {int:id}
				AND id_class = {int:class}",
			array(
				'id' => $id,
				'class' => $class,
			)
		);
		redirectexit('action=admin;area=hof;sa=admin;state=success');
	}
	else
		redirectexit('action=admin;area=hof;sa=admin;state=fail');
}

/**
 * Fetch Classes & Famers.
 */
function ViewHof()
{
	global $context, $mbname, $txt, $modSettings, $smcFunc, $user_info, $sourcedir, $scripturl;
	// The Usual Stuff First
	isAllowedTo('view_mlist');
	$context['sub_template']  = 'main';
	// Query Dem Classes
	$classes = array();
	$query = $smcFunc['db_query']('', "
	SELECT id_class, title, description
	FROM {db_prefix}hof_classes");
	while ($row = $smcFunc['db_fetch_assoc']($query))
	{
		$classes[$row['id_class']]  = array(
			'id' => $row['id_class'],
			'title' => $row['title'],
			'description' => $row['description'],
		);
	}
	$smcFunc['db_free_result']($query);
	$context['hof_classes'] = $classes;

	// The Famers of the Class
	$famers = array();
	foreach ($classes as $id => $data)
	{
		$query2 = $smcFunc['db_query']('', "
			SELECT 
				m.ID_GROUP, m.avatar, m.ID_MEMBER, m.real_name, m.email_address, m.hide_email, m.date_registered, h.id_member, h.date_added, h.id_class
			FROM ({db_prefix}members as m, {db_prefix}hof as h) 
			WHERE h.id_member = m.ID_MEMBER
				AND h.id_class = {int:class}
			ORDER BY h.date_added",
			array(
				'class' => $data['id'],
			)
		);
		while ($row2 = $smcFunc['db_fetch_assoc']($query2))
		{
			$famers[$data['id']][] = array(
				'avatar' => $row2['avatar'],
				'ID_MEMBER' => $row2['ID_MEMBER'],
				'realName' => $row2['real_name'],
				'emailAddress' => $row2['email_address'],
				'hideEmail' => $row2['hide_email'],
				'dateRegistered' => $row2['date_registered'],
				'ID_GROUP' => $row2['ID_GROUP'],
			);
		}
		$smcFunc['db_free_result']($query2);
	}
	$context['hof_famers'] = $famers;
}

/**
 * Settings Page.
 */
function HofSettings()
{
	global $context, $mbname, $txt, $smcFunc;
	// Again
	isAllowedTo('admin_forum');
	// Layout Setting
	if(isset($_REQUEST['hof_layout'])) {
		$hof_layout = empty($_REQUEST['hof_layout']) ? 2 : $_REQUEST['hof_layout'];
		updateSettings(
			array(
				'hof_layout' => $hof_layout,
			)
		);
	}
	if(isset($_REQUEST['active'])) {
		updateSettings(
			array(
				'hof_active' => (int) $_REQUEST['active'],
			)
		);
	}
	$context['sub_template']  = 'adminset';
	$context['page_title'] = $mbname.' - '.$txt['hof_admin'];	

	// Get all the Classes
	$classes = array();
	// QUERY
	$query = $smcFunc['db_query']('', "
	SELECT id_class, title, description
	FROM {db_prefix}hof_classes");
	while ($row = $smcFunc['db_fetch_assoc']($query))
	{
		$classes[$row['id_class']]  = array(
			'id' => $row['id_class'],
			'title' => $row['title'],
			'description' => $row['description'],
		);
	}
	$smcFunc['db_free_result']($query);

	$context['hof_classes'] = $classes;

	$famers = array();
	foreach ($classes as $id => $data)
	{
		$query2 = $smcFunc['db_query']('', "
			SELECT 
				m.ID_GROUP, m.avatar, m.ID_MEMBER, m.real_name, m.email_address, m.hide_email, m.posts, m.last_login, m.date_registered, h.id_member, h.date_added, h.id_class
			FROM ({db_prefix}members as m, {db_prefix}hof as h) 
			WHERE h.id_member = m.ID_MEMBER
				AND h.id_class = {int:class}
			ORDER BY h.date_added",
			array(
				'class' => $data['id'],
			)
		);
		while ($row2 = $smcFunc['db_fetch_assoc']($query2))
		{
			$famers[$data['id']][] = array(
				'ID_GROUP' => $row2['ID_GROUP'],
				'avatar' => $row2['avatar'],
				'ID_MEMBER' => $row2['ID_MEMBER'],
				'realName' => $row2['real_name'],
				'emailAddress' => $row2['email_address'],
				'hideEmail' => $row2['hide_email'],
				'lastLogin' => $row2['last_login'],
				'dateRegistered' => $row2['date_registered'],
				'posts' => $row2['posts'],
				'class' => $row2['id_class'],
			);
		}
		$smcFunc['db_free_result']($query2);
	}
	$context['hof_famers'] = $famers;
	
}

/**
 * Edit a Class Page.
 */
function editClass()
{
	global $context, $mbname, $txt, $smcFunc, $scripturl;
	// Let's Make Sure we know where we are ye ?
	$context['page_title'] = $txt['hof_edit_class'];
	$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title']));
	$context['linktree'][] = array(
		'url' => $scripturl. '?action=hof;sa=edit_class',
		'name' => $txt['hof_edit_class'],
	);
	// No access, means no access
	isAllowedTo('admin_forum');
	// Sanitization
	$class = (isset($_REQUEST['class']) && !empty($_REQUEST['class'])) ? (int)$_REQUEST['class'] : 0;
	$context['sub_template']  = 'editClass';
	if(!empty($class))
	{
		$class_content = array();
		$query = $smcFunc['db_query']('', "
			SELECT id_class, title, description
			FROM {db_prefix}hof_classes 
			WHERE id_class = {int:class}",
			array(
				'class' => $class,
			)
		);
		while($row = $smcFunc['db_fetch_assoc']($query))
		{
			$class_content = array(
				'id' => $row['id_class'],
				'title' => $row['title'],
				'description' => $row['description']
			);
		}
		$smcFunc['db_free_result']($query);

		$context['hof_current_class'] = $class_content;
	}
	else
		redirectexit('action=admin;area=hof;sa=admin;state=fail');
}

/**
 * Change Global Title.
 */
function hofeditSettings()
{
	global $smcFunc;
	// Are we using a custom title ?
	if(!empty($_POST['globalTitle']))
	{
		$globalTitle = !empty($_POST['globalTitle']) ? $smcFunc['htmlspecialchars']($_POST['globalTitle'], ENT_QUOTES) : '';
		updateSettings(
			array(
				'hof_globalTitle' => $globalTitle,
			)
		);
		redirectexit('action=admin;area=hof;sa=admin;state=success');
	}
	// A specific avatar width ?
	elseif(!empty($_POST['ewidth']))
	{
		$ewidth = !empty($_POST['ewidth']) ? (int)($_POST['ewidth']) : '';
		updateSettings(
			array(
				'hof_ewidth' => $ewidth,
			)
		);
		redirectexit('action=admin;area=hof;sa=admin;state=success');
	}
	// We're not doing anything ?
	else 
		redirectexit('action=admin;area=hof;sa=admin;state=fail');
}