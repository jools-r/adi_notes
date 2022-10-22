<?php
/*
	adi_notes - Leave notes for Textpattern users

	Written by Adi Gilbert

	Released under the GNU Lesser General Public License

	Version history:
		1.4		- fix: variable parsing
				- fix: debug mode error
				- fix: admin footer conflict (4.6)
				- fix: generate admin style only on admin page
				- fix: private note display issue
				- fix: prevent duplicate style in custom style on install
				- update TXP lastmod
				- Textile fix for 4.7 (thanks Gallex)
				- fix: last_mod errors in strict MySQL (thanks RedFox)
		1.3.1	- strict MySQL workarounds: removed DEFAULT from 'note' column, restricted event name to 12 chars in set_pref for pre TXP 4.6 (thanks Gallex)
		1.3		- edit link fixes for 4.6 (thanks jpdupont)
				- tag parsing (for jpdupont)
				- style fixes & improvements (thanks Bloke)
				- inline style fix (thanks uli)
				- fix for Hive theme header (thanks MarcoK)
				- fix for preference save issue in pre-4.5.6
				- admin tab layout & styling
				- TXP 4.5+ only
		1.2		- TXP 4.5-ified
				- code tidy up
				- changed: admin tab name/title now "Notes Admin"
				- now uses lifecycle events
				- changed the default width (thanks RedFox)
		1.1.2	- fix for multi-site installs (thanks AdamK)
				- style tweak for Hive
		1.1.1	- fixed: get_off_my_lawn error in new article tab in 4.4.1
				- admin: style improvements for theme compatibility
		1.1		- admin: moved install/uninstall etc to plugin options
				- enhancement: embedded notes at top or bottom of page
				- enhancement: embedded notes now available on all TXP admin pages
				- enhancement: note tab for Home/Start tab
				- enhancement: Textpack
				- enhancement: TinyMCE support
				- new options: custom note style, note width, note edit privs
				- change: default note width now 900px (was 90%)
		1.0.2	- enhancement: embedded notes for Variables tab (for maruchan) and also adi_menu, adi_prefs (for good measure)
		1.0.1	- fix: uninitialised variable errors
		1.0		- enhancement: footnotes
				- enhancement: public/private notes
				- enhancement: in situ editing
		0.3		- added edit links & links from Admin tab to notes tabs
		0.2		- fix: CREATE TABLE error (thanks gocom)
		0.1		- initial release

	Upgrades:
		- database tables/preferences tweaked automatically if required

	Downgrade instructions:
		1)	Go to Options page.
		2)	Add "&action=downgrade" to end of URL & hit enter - should then see "adi_notes: Downgraded" message.
		3)	Immediately go to Plugins tab to delete adi_notes.
		4)	Install older adi_notes plugin version.
		5)	Go to adi_notes admin tab & verify.

*/

// TODO
//	- behind the scenes: MODERNISE PREFS (INPUT_TYPES)
//	- enhancement: note tab, public note, private note - visibility privs


if (@txpinterface == 'admin') {
	global $adi_notes_debug;

	$adi_notes_debug = 0;

	if (!version_compare(txp_version, '4.5.0', '>=')) return;

	adi_notes_init();
}

function adi_notes_init() {
// initial set up
	global $event, $prefs, $txp_user, $adi_notes_login_user, $adi_notes_tab_list, $adi_notes_gtxt, $adi_notes_prefs, $adi_notes_debug, $adi_notes_plugin_status, $adi_notes_url, $adi_notes_priv_levels, $adi_notes_txp460, $adi_notes_txp470;

	$adi_notes_txp460 = (version_compare(txp_version, '4.6-dev', '>='));
	$adi_notes_txp470 = (version_compare(txp_version, '4.7-dev', '>='));

	$adi_notes_login_user = $txp_user; // record txp_user here, so including publish.php later doesn't splat it

	if (!$adi_notes_txp470)
 		@include_once txpath.'/lib/classTextile.php';

	// default preferences
	$adi_notes_prefs = array(
		'style' => 'sticky',	// or 'red', 'minimal', 'custom'
		'position' => 'footer', // or 'header'
		'width' => 'width:90%; max-width:900px',
		'width_old' => '',
		'custom_style' =>
'#adi_notes { margin:1em auto; width:90%; max-width:900px; padding:2em; border:1px solid red; background-color:lightgray; }
#adi_notes h1 { color:red }
#adi_notes p { margin:1em 0; color:green }
#adi_notes p:first-child { margin-top:0 }
#adi_notes p:last-child { margin-bottom:0 }
#adi_notes ul li { color:blue }
.adi_notes_tab_link { background-color:yellow }
.adi_notes_embed_links { background-color:orange }
#adi_notes.adi_notes_embed_headnote {  background-color:pink; color:red; margin-bottom:2em }',
		'custom_style_old' => '',
		'note_tab_edit_privs' => '1,2',
		'public_note_edit_privs' => '1,2', // future requirement?
		'txp_tag_privs' => '1', // always allow publisher
		'markup' => 'textile',	// or 'html'
		'tiny_mce' => 'none',	// or 'hak', 'jquery', 'javascript' - may be modified on install if hak_tiny_mce found
		'tiny_mce_dir_path' => '../scripts/tiny_mce',
		'convert_link' => '0'
		);

# --- BEGIN PLUGIN TEXTPACK ---
	$adi_notes_gtxt = array(
		'adi_add_note' => 'Add note',
		'adi_add_private_note' => 'Add private note',
		'adi_add_public_note' => 'Add public note',
		'adi_convert_textile' => 'Convert Textile',
		'adi_custom' => 'Custom',
		'adi_display_convert_option' => 'Show Textile convert option',
		'adi_edit_private_note' => 'Edit private note',
		'adi_edit_public_note' => 'Edit public note',
		'adi_footer' => 'Footer',
		'adi_header' => 'Header',
		'adi_install_fail' => 'Unable to install',
		'adi_installed' => 'Installed',
		'adi_minimal' => 'Minimal',
		'adi_not_installed' => 'Not installed',
		'adi_note_delete_fail' => 'Unable to delete note',
		'adi_note_deleted' => 'Note deleted',
		'adi_note_editing_private' => 'Editing private note',
		'adi_note_editing_public' => 'Editing public note',
		'adi_note_markup' => 'Markup',
		'adi_note_public_edit_privs' => 'Public note edit privileges',
		'adi_note_save_fail' => 'Unable to save note',
		'adi_note_saved' => 'Note saved',
		'adi_note_tabs' => 'Note tabs',
		'adi_note_txp_tag_privs' => 'Private note TXP tag privileges',
		'adi_notes' => 'Notes',
		'adi_notes_admin' => 'Notes Admin',
		'adi_nothing_to_do' => 'Nothing to do',
		'adi_position' => 'Position',
		'adi_pref_update_fail' => 'Preference update failed',
		'adi_red' => 'Red',
		'adi_textpack_fail' => 'Textpack installation failed',
		'adi_textpack_feedback' => 'Textpack feedback',
		'adi_textpack_online' => 'Textpack also available online',
		'adi_tiny_mce_dir_path' => 'TinyMCE directory path',
		'adi_tiny_mce_hak' => 'TinyMCE (hak_tinymce)',
		'adi_tiny_mce_javascript' =>'TinyMCE (Javascript)',
		'adi_tiny_mce_jquery' => 'TinyMCE (jQuery)',
		'adi_uninstall' => 'Uninstall',
		'adi_uninstall_fail' => 'Unable to uninstall',
		'adi_uninstalled' => 'Uninstalled',
		'adi_update_prefs' => 'Update preferences',
		'adi_upgrade_fail' => 'Unable to upgrade',
		'adi_upgrade_required' => 'Upgrade required',
		'adi_upgraded' => 'Upgraded',
		'adi_width' => 'Width',
		'adi_yellow' => 'Yellow',
		);
# --- END PLUGIN TEXTPACK ---

	// Textpack
	$adi_notes_url = array(
		'textpack' => 'http://www.greatoceanmedia.com.au/files/adi_textpack.txt',
		'textpack_download' => 'http://www.greatoceanmedia.com.au/textpack/download',
		'textpack_feedback' => 'http://www.greatoceanmedia.com.au/textpack/?plugin=adi_notes',
	);
	if (strpos($prefs['plugin_cache_dir'], 'adi') !== FALSE) // use Adi's local version
		$adi_notes_url['textpack'] = $prefs['plugin_cache_dir'].'/adi_textpack.txt';

	// plugin lifecycle
	register_callback('adi_notes_lifecycle', 'plugin_lifecycle.adi_notes');

	// privilege levels
	$adi_notes_priv_levels = array(
		1 => gTxt('publisher'),
		2 => gTxt('managing_editor'),
		3 => gTxt('copy_editor'),
		4 => gTxt('staff_writer'),
		5 => gTxt('freelancer'),
		6 => gTxt('designer'),
	);

	// 'note tab name' (used in the database) => ('title' => nice title, 'where' => parent tab, 'visible' => installed visibility)
	// - could probably take this back to old format: array('start'=> 0, 'content' => 1 ...)
	// - as of v1.1, only Content tab note added to database on install (adi_notes_update_visibility() adds other tabs as & when)
	$adi_notes_tab_list = array(
		'start' =>			array('title' => gTxt('tab_start'), 'where' => 'start', 'visible' => '0'),
		'content' =>		array('title' => gTxt('tab_content'), 'where' => 'content', 'visible' => '1'),
		'presentation' =>	array('title' => gTxt('tab_presentation'), 'where' => 'presentation', 'visible' => '0'),
		'admin' =>			array('title' => gTxt('tab_admin'), 'where' => 'admin', 'visible' => '0'),
		);

	// adi_notes Admin tab
	add_privs("adi_notes_admin"); // defaults to priv '1' only
	register_tab("extensions", "adi_notes_admin", adi_notes_gtxt('adi_notes_admin')); // add new tab under 'Extensions'
	register_callback("adi_notes_admin", "adi_notes_admin");

	// adi_notes tabs & embedded notes
	$installed = adi_notes_installed();
	if ($installed) {
		if (!adi_notes_upgrade()) {
			$visibility = adi_notes_tab_visibility();
			foreach ($adi_notes_tab_list as $tab_name => $tab_info) {
				// register note tab (if marked as visible)
				if ($visibility[$tab_name]) {
					add_privs("adi_notes_tab_".$tab_name, '1,2,3,4,5,6'); // all privs
					register_callback("adi_notes_tab", "adi_notes_tab_".$tab_name);
					register_tab($tab_info['where'], "adi_notes_tab_".$tab_name, adi_notes_gtxt('adi_notes'));
				}
			}
			// switch on Home tab
			if ($visibility['start'])
				add_privs('tab.start', '1,2,3,4,5,6'); // all privs
			if (strpos($event, 'adi_notes_tab_') === FALSE) { // add embedded notes to all tabs (except note tabs)
				register_callback('adi_notes_embed_note', 'admin_side', 'footer'); // shifted into place by jQuery in 4.6
				// jQuery fix for reliable header position (works for Classic/Remora/Hive in 4.5 & 4.6)
				if (adi_notes_prefs('position') == 'header')
					register_callback('adi_notes_headnote_script', 'admin_side', 'head_end');
				else
					register_callback('adi_notes_footnote_script', 'admin_side', 'head_end');
				register_callback('adi_notes_embed_note_links', 'admin_side', 'footer'); // add/edit links in footer, shifted into place by jQuery in 4.6
				if ($adi_notes_txp460)
					register_callback('adi_notes_links_script', 'admin_side', 'head_end');
			}
		}
	}

	// note tab & embedded note style
	register_callback('adi_notes_style', 'admin_side', 'head_end');

	// admin page script & style
	if ($event == 'adi_notes_admin') {
		register_callback('adi_notes_admin_script', 'admin_side', 'head_end');
		register_callback('adi_notes_admin_style', 'admin_side', 'head_end');
	}

	// TinyMCE
	if (adi_notes_prefs('tiny_mce') != 'none')
		register_callback('adi_notes_tiny_mce_'.adi_notes_prefs('tiny_mce'), 'admin_side', 'footer');

	// plugin options
	$adi_notes_plugin_status = fetch('status', 'txp_plugin', 'name', 'adi_notes', $adi_notes_debug);
	if ($adi_notes_plugin_status) { // proper install - options under Plugins tab
		add_privs('plugin_prefs.adi_notes'); // defaults to priv '1' only
		register_callback('adi_notes_options', 'plugin_prefs.adi_notes');
	}
	else { // txpdev - options under Extensions tab
		add_privs('adi_notes_options'); // defaults to priv '1' only
		register_tab('extensions', 'adi_notes_options', 'adi_notes options');
		register_callback('adi_notes_options', 'adi_notes_options');
	}

}

function adi_notes_admin_style() {
	echo
		'<style type="text/css">
			/* adi_notes - admin tab */
			.adi_notes_form { width:60%; margin:0 auto 2em; padding:1em; text-align:center }
			.adi_notes_form h2 { margin:1.5em 0 0 }
			.adi_notes_form p { margin:0.8em 0 0 }
			.adi_notes_form textarea { margin-top:0.8em }
			.adi_notes_form .smallerbox { margin-top:3em }
			.adi_notes_form label { margin-left:2em }
			.adi_notes_form label:first-child { margin-left:0 }
			.adi_notes_form p.adi_notes_width label, .adi_notes_form p.adi_notes_input_dir label { margin-left:0; margin-right:0.5em }
			.adi_notes_form p.adi_notes_width input { width:18em }
		</style>';
}

function adi_notes_style() {
// some style for the page

	$adi_notes_step = gps('adi_notes_step');
	if (($adi_notes_step == 'edit') || ($adi_notes_step == 'edit_user'))
		$nav_fix = t.t.t.'#nav li ul { z-index:999 }'.n; // so TXP remora dropdown overlays TinyMCE toolbar properly
	else
		$nav_fix = '';

	// set up note style
	$note_styles = array();
	switch (adi_notes_prefs('style')) {
		case 'sticky':
			$note_styles[] = '#adi_notes { margin:1em auto; padding:1em; border:1px solid; border-color:#e3d8c3 #c3b8a3 #c3b8a3 #e3d8c3; background-color:#ffffcc; color:#202020 }';
			break;
		case 'red':
			$note_styles[] = '#adi_notes { margin:1em auto; padding:1em; border:1px solid #b22222; background-color:#ffffff; color:#b22222 }';
			break;
		case 'minimal':
			$note_styles[] = '#adi_notes { margin:1em auto }';
			break;
		case 'custom':
			$note_styles = explode(n, adi_notes_prefs('custom_style'));
			break;
		default:
			$note_styles[] = '/* no style found */';
	}
	if (adi_notes_prefs('style') != 'custom') {
		$note_styles[] = '#adi_notes p { margin:1em 0; }';
		$note_styles[] = '#adi_notes p:first-child { margin-top:0 }';
		$note_styles[] = '#adi_notes p:last-child { margin-bottom:0 }';
		$note_styles[] = '#adi_notes.adi_notes_embed_headnote { margin-bottom:2em }';
	}

	// collate rules
	$note_style = '/* adi_notes '.adi_notes_prefs('style').' style */'.n;
	foreach ($note_styles as $style)
		$note_style .= t.t.t.$style.n;

	// generate <style>...</style>
	echo
		'<style type="text/css">
			/* adi_notes - note tabs & embedded notes */
			#adi_notes { text-align:left }
			#adi_notes .adi_notes_hr { width:100%; margin:1em auto; border:none; height:1px; color:#e0e0e0; background-color:#e0e0e0 }
			#adi_notes .adi_notes_textarea { width:98%; height:25em; margin-top:0 }
			#adi_notes .adi_notes_textarea { height:25em ! important; } /* something is applying an inline style of 46px in TXP 4.6 */
			#adi_notes .adi_notes_textarea_tab { height:50em }
			#adi_notes .adi_notes_label { margin:0 0 0.5em 0; color:black }
			#adi_notes .adi_notes_save { margin:0.5em 0 0 0; color:black }
			#adi_notes .adi_notes_save input.publish { margin:0 1em 0 0 }
			.adi_notes_embed_links { margin:1em 0; text-align:center }
			/* adi_notes - note tabs */
			.adi_notes_tab_link { margin:1em 0; text-align:center }
			'.$nav_fix
			.$note_style
			.(adi_notes_prefs('style') == 'custom' ? '' : t.t.t.'/* adi_notes width */'.n.t.t.t.'#adi_notes { '.adi_notes_prefs('width').' }').n
		.'</style>';
}

function adi_notes_admin_script() {
// jQuery magic for admin tab

	$script = <<<END_SCRIPT
<script type="text/javascript">

	// adi_notes admin script

	$().ready(function() {

		// auto hide/show TinyMCE dir path input field
		$('#adi_notes_tiny_mce_none,#adi_notes_tiny_mce_hak').click(
			function(){
				$('p.adi_notes_input_dir').hide();
			}
		);
		$('#adi_notes_tiny_mce_jquery,#adi_notes_tiny_mce_javascript').click(
			function(){
				$('p.adi_notes_input_dir').show();
			}
		);

		// auto hide/show Textile convert option input field
		$('#adi_notes_tiny_mce_none').click(
			function(){
				$('p.adi_notes_input_convert').hide();
			}
		);
		$('#adi_notes_tiny_mce_jquery,#adi_notes_tiny_mce_javascript,#adi_notes_tiny_mce_hak').click(
			function(){
				$('p.adi_notes_input_convert').show();
			}
		);

		// auto hide/show custom style textarea
		$('#adi_notes_style_custom').click(
			function(){
				$('textarea[name="custom_style"]').show();
				$('p.adi_notes_width').hide();
			}
		);
		$('#adi_notes_style_sticky,#adi_notes_style_red,#adi_notes_style_minimal').click(
			function(){
				$('textarea[name="custom_style"]').hide();
				$('p.adi_notes_width').show();
			}
		);

	});

</script>

END_SCRIPT;
	echo $script;
}

function adi_notes_headnote_script() {
// header position fix for Hive theme

	$script = <<<END_SCRIPT
<script type="text/javascript">
	// adi_notes_headnote_script
	$().ready(function() {
		$("#adi_notes").prependTo(".txp-body");
	});
</script>

END_SCRIPT;

	echo $script;
}

function adi_notes_footnote_script() {
// scriptish to shift embedded note out of footer

	echo <<<END_SCRIPT
<script type="text/javascript">
	// adi_notes_footnote_script
	$(function() {
		$("#adi_notes").insertBefore("footer");
	});
</script>
END_SCRIPT;
}

function adi_notes_links_script() {
// scriptish to shift embedded links out of footer

	echo <<<END_SCRIPT
<script type="text/javascript">
	// adi_notes_links_script
	$(function() {
		$("p.adi_notes_embed_links").insertBefore("footer");
	});
</script>
END_SCRIPT;
}

function adi_notes_tiny_mce_jquery() {
// TinyMCE implementation, using jQuery - TinyMCE options copied from hak_tinymce

	$adi_notes_step = gps('adi_notes_step');
	if (($adi_notes_step != 'edit') && ($adi_notes_step != 'edit_user')) return;

	$script = <<<END_SCRIPT
<script type="text/javascript" src="adi_notes_tiny_mce_dir_path/jquery.tinymce.js"></script>

<script type="text/javascript">

	$().ready(function() {

		$('#adi_notes textarea').tinymce({

			// Location of TinyMCE script

			script_url : 'adi_notes_tiny_mce_dir_path/tiny_mce.js',

			// General options

			theme : "advanced",

			language : "en",
			relative_urls : false,
			remove_script_host : false,
			plugins : "searchreplace",
			entity_encoding : "numeric",
			// Theme options

			theme_advanced_buttons1 : "bold,italic,underline,strikethrough,forecolor,backcolor,removeformat,numlist,bullist,outdent,indent,justifyleft,justifycenter,justifyright,justifyfull",
			theme_advanced_buttons2 : "link,unlink,separator,image,separator,search,replace,separator,cut,copy,paste,separator,code,separator,formatselect",
			theme_advanced_buttons3 : "",
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",

		});


	});

</script>

END_SCRIPT;

	$script = preg_replace('/adi_notes_tiny_mce_dir_path/', adi_notes_prefs('tiny_mce_dir_path'), $script);
	echo $script;
}

function adi_notes_tiny_mce_hak() {
// TinyMCE implementation, using hak_tinymce options & script installed in textpattern subdir
	global $adi_notes_debug;

	$adi_notes_step = gps('adi_notes_step');
	if (($adi_notes_step != 'edit') && ($adi_notes_step != 'edit_user')) return;

	$script = '<script language="javascript" type="text/javascript" src="tiny_mce/tiny_mce.js"></script>';
	$script .= '<script type="text/javascript">';
	$script .= 'tinyMCE.init({';
	$script .= 'mode : "textareas",';
	$script .= fetch('pref_value', 'txp_hak_tinymce', 'pref_name', 'body_init', $adi_notes_debug);
	$script .= 'editor_selector : "adi_notes_mceEditor",';
	$script .= '});';
	$script .= '</script>';
	echo $script;
}

function adi_notes_tiny_mce_javascript() {
// TinyMCE implementation, using Javascript

	$adi_notes_step = gps('adi_notes_step');
	if (($adi_notes_step != 'edit') && ($adi_notes_step != 'edit_user')) return;

	$script = <<<END_SCRIPT
<script type="text/javascript" src="adi_notes_tiny_mce_dir_path/tiny_mce.js"></script>

<script type="text/javascript">

	tinyMCE.init({

			// General options
			mode : "textareas",

			theme : "advanced",

			language : "en",
			relative_urls : false,
			remove_script_host : false,
			plugins : "searchreplace",
			entity_encoding : "numeric",
			editor_selector : "adi_notes_mceEditor",

			// Theme options

			theme_advanced_buttons1 : "bold,italic,underline,strikethrough,forecolor,backcolor,removeformat,numlist,bullist,outdent,indent,justifyleft,justifycenter,justifyright,justifyfull",
			theme_advanced_buttons2 : "link,unlink,separator,image,separator,search,replace,separator,cut,copy,paste,separator,code,separator,formatselect",
			theme_advanced_buttons3 : "",
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",

	});

</script>
END_SCRIPT;

	$script = preg_replace('/adi_notes_tiny_mce_dir_path/', adi_notes_prefs('tiny_mce_dir_path'), $script);
	echo $script;
}

function adi_notes_has_privs($res, $user='') {
// like txplib_misc.php has_privs() but accepts a comma separated list of privs rather than a standard named combo
	global $adi_notes_login_user, $adi_notes_debug;

	if ($res) {
		if (empty($user)) // no user supplied, so use the current login name
			$user = $adi_notes_login_user;
		$user_privs = safe_field("privs", "txp_users", "name='".doSlash($user)."'");
		if ($adi_notes_debug)
			echo 'PRIVS: required='.$res.' actual='.$user_privs.' ('.$user.') ';
		return in_array($user_privs, explode(',', $res));
	}
	else
		return false;
}

function adi_notes_admin($event, $step) {
// the admin tab
	global $prefs, $adi_notes_tab_list, $adi_notes_debug, $adi_notes_plugin_status, $adi_notes_priv_levels, $adi_notes_prefs;

	$message = '';
	$installed = adi_notes_installed();
	$submit = gps('adi_notes_admin_submit'); // check that someone's hit the tit

	// process the information
	if ($installed) {
		$upgrade_required = adi_notes_upgrade();
		if ($upgrade_required)
			$message = array(adi_notes_gtxt('adi_upgrade_required'), E_WARNING);
		else if ($submit) {
			if ($step == 'update') {
				// massage submitted note_tab_edit_privs
				$submitted_note_tab_edit_privs = ps("note_tab_edit_privs");
				if (empty($submitted_note_tab_edit_privs)) // revert to default privs
					foreach (explode(',', $adi_notes_prefs['note_tab_edit_privs']) as $priv)
						$submitted_note_tab_edit_privs[$priv] = 1;
				$submitted_note_tab_edit_privs[1] = TRUE; // make sure publisher priv switched on
				$note_tab_edit_privs = implode(',', array_keys($submitted_note_tab_edit_privs));
				// txp_tag_privs
				$submitted_txp_tag_privs = ps("txp_tag_privs");
				if (empty($submitted_txp_tag_privs))
					$submitted_txp_tag_privs = array();
				$txp_tag_privs = implode(',', array_keys($submitted_txp_tag_privs));
				// do update
				$update_ok =
					adi_notes_update_visibility(ps("visibility"))
					&& adi_notes_prefs('note_tab_edit_privs', $note_tab_edit_privs)
					&& adi_notes_prefs('public_note_edit_privs', $note_tab_edit_privs) // currently same as note tab edit privs
					&& adi_notes_prefs('txp_tag_privs', $txp_tag_privs)
					&& adi_notes_prefs('style', strip_tags(ps("style")))
					&& adi_notes_prefs('custom_style', strip_tags(ps("custom_style")))
					&& adi_notes_prefs('position', strip_tags(ps("position")))
					&& adi_notes_prefs('width', strip_tags(ps("width")))
					&& adi_notes_prefs('tiny_mce', strip_tags(ps("tiny_mce")))
					&& adi_notes_prefs('tiny_mce_dir_path', strip_tags(ps("tiny_mce_dir_path")))
					&& adi_notes_prefs('convert_link', strip_tags(ps("convert_link")));
				if (adi_notes_prefs('tiny_mce') == 'none')
					$update_ok &= adi_notes_prefs('markup', 'textile');
				else
					$update_ok &= adi_notes_prefs('markup', 'html');
				if ($update_ok)
					$message = adi_notes_gtxt('preferences_saved');
				else
					$message = array(adi_notes_gtxt('adi_pref_update_fail'), E_ERROR);
			}
		}
	}
	else
		$message = array(adi_notes_gtxt('adi_not_installed'), E_ERROR);

	// generate page
	pagetop(adi_notes_gtxt('adi_notes').' '.gTxt('admin'), $message);

	if ($installed && !$upgrade_required) {
		// visibility checkboxes
		$visibility = adi_notes_tab_visibility();
		$visibility_checkboxes = '';
		foreach ($adi_notes_tab_list as $tab_name => $tab_info)
			$visibility_checkboxes .= tag(checkbox("visibility[$tab_name]", '1', $visibility[$tab_name]).sp.$tab_info['title'], 'label');

		// note tab edit priv checkboxes
		$note_tab_edit_priv_checkboxes = '';
		$current_privs = explode(',', adi_notes_prefs('note_tab_edit_privs'));
		foreach ($adi_notes_priv_levels as $priv => $priv_name)
			$note_tab_edit_priv_checkboxes .= tag(checkbox("note_tab_edit_privs[$priv]", '1', in_array($priv, $current_privs)).sp.$priv_name, 'label');

		// txp tag priv checkboxes
		$txp_tag_priv_checkboxes = '';
		$current_privs = explode(',', adi_notes_prefs('txp_tag_privs'));
		foreach ($adi_notes_priv_levels as $priv => $priv_name)
			$txp_tag_priv_checkboxes .= tag(checkbox("txp_tag_privs[".$priv."]",  "1", in_array($priv, $current_privs)).sp.$priv_name, 'label');

		// width field peekaboo
		if (adi_notes_prefs('style') == 'custom') {
			$hide_custom = '';
			$hide_width = ' style="display:none"';
		}
		else {
			$hide_width = '';
			$hide_custom = 'display:none';
		}
		// TinyMCE peepo
		if ((adi_notes_prefs('tiny_mce') == 'none') || (adi_notes_prefs('tiny_mce') == 'hak'))
			$hide_dir = ' style="display:none"';
		else
			$hide_dir = '';
		if (adi_notes_prefs('tiny_mce') == 'none')
			$hide_convert = ' style="display:none"';
		else
			$hide_convert = '';

		// position
		$position =
			tag(adi_notes_gtxt('adi_position'), "h2")
			.graf(
				tag(radio('position', 'header', (adi_notes_prefs('position') == 'header')).sp.adi_notes_gtxt('adi_header'), 'label')
				.tag(radio('position', 'footer', (adi_notes_prefs('position') == 'footer')).sp.adi_notes_gtxt('adi_footer'), 'label')
			);

		$hak_tinymce_installed = safe_row("version", "txp_plugin", "status = 1 AND name='hak_tinymce'", $adi_notes_debug);

	    echo form(
			tag(adi_notes_gtxt('adi_notes_admin'), 'h1')
			// visibility
			.tag(adi_notes_gtxt('adi_note_tabs'), 'h2')
			.graf($visibility_checkboxes)
			// note tab/public note edit privs
			.tag(adi_notes_gtxt('adi_note_public_edit_privs'), "h2")
			.graf($note_tab_edit_priv_checkboxes)
			// txp tag privs
			.tag(adi_notes_gtxt('adi_note_txp_tag_privs'), "h2")
			.graf($txp_tag_priv_checkboxes)
			// position
			.$position
			// style
			.tag(gTxt('style'), "h2")
			.graf(
				tag(radio('style', 'sticky', (adi_notes_prefs('style') == 'sticky'), 'adi_notes_style_sticky').sp.adi_notes_gtxt('adi_yellow'), 'label')
				.tag(radio('style', 'red', (adi_notes_prefs('style') == 'red'), 'adi_notes_style_red').sp.adi_notes_gtxt('adi_red'), 'label')
				.tag(radio('style', 'minimal', (adi_notes_prefs('style') == 'minimal'), 'adi_notes_style_minimal').sp.adi_notes_gtxt('adi_minimal'), 'label')
				.tag(radio('style', 'custom', (adi_notes_prefs('style') == 'custom'), 'adi_notes_style_custom').sp.adi_notes_gtxt('adi_custom'), 'label')
			)
			// width
			.graf(
				tag(adi_notes_gtxt('adi_width'), 'label')
				.finput("text", 'width', adi_notes_prefs('width'))
				,' class="adi_notes_width"'.$hide_width
			)
			// custom style
			.adi_notes_textarea('custom_style', adi_notes_prefs('custom_style'), 'adi_notes_style_custom_style', 5, 40, $hide_custom)
			// TinyMCE
			.tag(adi_notes_gtxt('adi_note_markup'), "h2")
			.graf(
				tag(radio('tiny_mce', 'none', (adi_notes_prefs('tiny_mce') == 'none'), 'adi_notes_tiny_mce_none').sp.gTxt('txptextile'), 'label')
				.($hak_tinymce_installed ? tag(radio('tiny_mce', 'hak', (adi_notes_prefs('tiny_mce') == 'hak'), 'adi_notes_tiny_mce_hak').sp.adi_notes_gtxt('adi_tiny_mce_hak'), 'label') : '')
				.tag(radio('tiny_mce', 'jquery', (adi_notes_prefs('tiny_mce') == 'jquery'), 'adi_notes_tiny_mce_jquery').sp.adi_notes_gtxt('adi_tiny_mce_jquery'), 'label')
				.tag(radio('tiny_mce', 'javascript', (adi_notes_prefs('tiny_mce') == 'javascript'), 'adi_notes_tiny_mce_javascript').sp.adi_notes_gtxt('adi_tiny_mce_javascript'), 'label')
			)
			.graf(
				tag(adi_notes_gtxt('adi_tiny_mce_dir_path'), 'label').finput("text", 'tiny_mce_dir_path', adi_notes_prefs('tiny_mce_dir_path'))
				,' class="adi_notes_input_dir"'.$hide_dir
			)
			.graf(adi_notes_gtxt('adi_display_convert_option').checkbox('convert_link', '1', adi_notes_prefs('convert_link'))
				,' class="adi_notes_input_convert"'.$hide_convert
			)
	        .fInput('submit', 'adi_notes_admin_submit', adi_notes_gtxt('adi_update_prefs'), "smallerbox", "", '').
	        eInput("adi_notes_admin").sInput("update")
			,'', '', 'post', 'adi_notes_form'
		);
	}
}

function adi_notes_textarea($name, $thing = '', $id = '', $rows='5', $cols='40', $inline_style = '') {
// based on text_area() but with added inline style & without height & width

	$id = ($id) ? ' id="'.$id.'"' : '';
	$rows = ' rows="' . ( ($rows && is_numeric($rows)) ? $rows : '5') . '"';
	$cols = ' cols="' . ( ($cols && is_numeric($cols)) ? $cols : '40') . '"';
	$style = !empty($inline_style) ? ' style="'.$inline_style.'"' : '';
	return '<textarea'.$id.' name="'.$name.'"'.$rows.$cols.$style.'>'.txpspecialchars($thing).'</textarea>';
}

function adi_notes_options($event, $step) {
// display adi_notes options: install/uninstall/upgrade
	global $adi_notes_debug, $adi_notes_url, $adi_notes_plugin_status, $adi_notes_txp460;

	$message = '';
	$installed = adi_notes_installed();

	$action = gps('action'); // "under the counter" action

	$adi_notes_txp460 ? $e = 'adi_notes_admin' : $e = 'adi_notes_ad'; // transitional fix for event field length (prior to 4.6 = 12) & strict MySQL implementations

	if ($installed) {
		$upgrade_required = adi_notes_upgrade();
		if ($upgrade_required) { // upgrade
			$res = adi_notes_upgrade(TRUE); // copy notes old -> new
			if ($res)
				$message = adi_notes_gtxt('adi_upgraded');
			else
				$message = array(adi_notes_gtxt('adi_upgrade_fail'), E_ERROR);
		}
		// $stepping out
		if ($step == "uninstall") { // uninstall adi_notes
			$res = adi_notes_drop('adi_notes');
			$res = $res && adi_notes_delete_prefs();
			if ($res)
	    		$message = adi_notes_gtxt('adi_uninstalled');
			else
	    		$message = array(adi_notes_gtxt('adi_uninstall_fail'), E_ERROR);
		}
		else if ($step == 'textpack') {
			$adi_textpack = file_get_contents($adi_notes_url['textpack']);
			if ($adi_textpack) {
				$result = install_textpack($adi_textpack);
				$message = gTxt('textpack_strings_installed', array('{count}' => $result));
				$textarray = load_lang(LANG); // load in new strings
			}
			else
				$message = array(adi_notes_gtxt('adi_textpack_fail'), E_ERROR);
		}
		// $action man
		else if ($action == "downgrade") { // revert
			if (adi_notes_installed('adi_notes_old')) {
				$res = adi_notes_drop('adi_notes');
				$res = $res && adi_notes_rename('adi_notes_old', 'adi_notes'); // restore from 'adi_notes_old'
				$res = $res && safe_delete('txp_prefs', "name = 'adi_notes_txp_tag_privs'", $adi_notes_debug); // delete pref introduced in 1.3
				// restore old custom style
				$res = $res && set_pref('adi_notes_custom_style', adi_notes_prefs('custom_style_old'), $e, 2);
				// restore old width style
				$res = $res && set_pref('adi_notes_width', adi_notes_prefs('width_old'), $e, 2);
				if ($res)
					$message = "downgraded";
				else
					$message = array("downgrade failed", E_ERROR);
			}
			else
				$message = array("downgrade failed, no backup found", E_ERROR);
		}
		else if ($action == "backup") { // create backup in 'adi_notes_backup'
			$res = TRUE;
			if (adi_notes_installed('adi_notes_backup'))
				$res = adi_notes_drop('adi_notes_backup');
			$res = $res && adi_notes_copy('adi_notes', 'adi_notes_backup');
			if ($res)
				$message = "backed up";
			else
				$message = array("backup failed", E_ERROR);
		}
		else if ($action == "restore") { // restore from 'adi_notes_backup'
			if (adi_notes_installed('adi_notes_backup')) {
				$res = adi_notes_drop('adi_notes');
				$res = $res && adi_notes_copy('adi_notes_backup', 'adi_notes');
				if ($res)
					$message = "restored";
				else
					$message = array("restore failed", E_ERROR);
			}
			else
				$message = array("restore failed, no backup found", E_ERROR);
		}
		else if ($action == "cleanup") { // delete upgrade backup
			$res = TRUE;
			if (adi_notes_installed('adi_notes_old'))
				$res = adi_notes_drop('adi_notes_old');
			if ($res) {
				$message = "upgrade backup deleted";
			}
			else
				$message = array("unable to delete upgrade backup", E_ERROR);
		}
	}
	else { // not installed
		if ($step == "install") { // install adi_notes
			$res = adi_notes_install();
			if ($res)
				$message = adi_notes_gtxt('adi_installed');
			else
				$message = array(adi_notes_gtxt('adi_install_fail'), E_ERROR);
		}
		else
			$message = array(adi_notes_gtxt('adi_not_installed'), E_ERROR);
	}

	// generate page
	pagetop('adi_notes - '.gTxt('plugin_prefs'), $message);

	$install_button =
		form(
			fInput("submit", "adi_notes_options_submit", gTxt('install'), "publish", "", 'return verify(\''.gTxt('are_you_sure').'\')')
			.eInput($event).sInput("install")
			,'', '', 'post', 'adi_notes_nstall_button'
		);
	$uninstall_button =
		form(
			fInput("submit", "do_something", adi_notes_gtxt('adi_uninstall'), "publish", "", 'return verify(\''.gTxt('are_you_sure').'\')')
			.eInput($event).sInput("uninstall")
			,'', '', 'post', 'adi_notes_nstall_button adi_notes_uninstall_button'
		);

	if ($adi_notes_plugin_status) // proper plugin install, so lifecycle takes care of install/uninstall
		$install_button = $uninstall_button = '';

	$installed = adi_notes_installed();

	// options
	echo tag(
		tag('adi_notes '.gTxt('plugin_prefs'), 'h2')
		.( $installed ?
			// textpack links
			graf(href(gTxt('install_textpack'), '?event='.$event.'&amp;step=textpack'))
			.graf(href(adi_notes_gtxt('adi_textpack_online'), $adi_notes_url['textpack_download']))
			.graf(href(adi_notes_gtxt('adi_textpack_feedback'), $adi_notes_url['textpack_feedback']))
	    	.$uninstall_button
			: $install_button
		)
		,'div'
		,' style="text-align:center"'
	);
}

function adi_notes_prefs($name, $new_value=NULL) {
// set/read preferences
	global $prefs, $adi_notes_prefs, $adi_notes_txp460, $adi_notes_debug;

	if ($new_value === '') // reset to default
		$new_value = $adi_notes_prefs[$name];

	// workaround for set pref escaping issue (fixed in 4.5.6)
	if (!version_compare(txp_version, '4.5.5', '>'))
		$new_value = doSlash($new_value);

	// read current value (either from database or $adi_notes_prefs)
	isset($prefs['adi_notes_'.$name]) ? $value = $prefs['adi_notes_'.$name] : $value = $adi_notes_prefs[$name];

// 	if ($adi_notes_debug) echo "adi_notes_prefs: name=$name, value=$value, new_value=$new_value".br;

	$adi_notes_txp460 ? $e = 'adi_notes_admin' : $e = 'adi_notes_ad'; // transitional fix for event field length (prior to 4.6 = 12) & strict MySQL implementations

	if ($new_value == '!toggle!') { // toggle boolean
		$value ? $value = 0 : $value = 1; // toggle debug mode
		set_pref('adi_notes_'.$name, $value, $e, 2);
		$prefs = get_prefs(); // re-sample $prefs
	}
	else if ($new_value === NULL) // just return value
		return $value;
	else { // update pref
		$res = set_pref('adi_notes_'.$name, $new_value, $e, 2);
		$prefs = get_prefs(); // re-sample $prefs
		return $res;
	}
}

function adi_notes_install($vanilla=TRUE) {
// create adi_notes table & insert note tabs that are visible by default (i.e. content) - adi_notes_tab_visibility will insert the others
	global $adi_notes_tab_list, $adi_notes_debug;

	// database table
	$res = safe_query( // maintain quirk in CREATE TABLE - type maybe should've defaulted to 'tab'
		"CREATE TABLE IF NOT EXISTS ".safe_pfx('adi_notes')." (
		`id` INT(11) NOT NULL AUTO_INCREMENT,
		`name` VARCHAR(64) NOT NULL DEFAULT '',
		`type` VARCHAR(8) NOT NULL DEFAULT '',
		`user` VARCHAR(64) NULL DEFAULT '',
		`privs` VARCHAR(22) NOT NULL DEFAULT '1,2,3,4,5,6',
		`visible` TINYINT(1) NOT NULL DEFAULT '0',
		`last_mod` DATETIME NULL DEFAULT NULL,
		`last_mod_user` VARCHAR(64) NOT NULL DEFAULT '',
		`note` TEXT NOT NULL,
		PRIMARY KEY(id)
		);"
		,$adi_notes_debug);

	if ($vanilla) // populate table with empty note tab row(s)
		foreach ($adi_notes_tab_list as $tab_name => $tab_info)
			if ($tab_info['visible'])
				$res = $res &&
					safe_query(
						"INSERT INTO ".
						safe_pfx('adi_notes').
// 						" VALUES (DEFAULT,'".$tab_name."','tab',DEFAULT,DEFAULT,1,DEFAULT,DEFAULT,DEFAULT)" // pre-1.3.1
						" VALUES (DEFAULT,'".$tab_name."','tab',DEFAULT,DEFAULT,1,DEFAULT,DEFAULT,'')" // uses explicit value for empty note
						,$adi_notes_debug);
	return $res;
}

function adi_notes_installed($table='adi_notes') {
// test if supplied table is present
	global $adi_notes_debug;

	$found = FALSE;
	$rs = safe_query("SHOW TABLES LIKE '".safe_pfx($table)."'", $adi_notes_debug);
	$a = nextRow($rs);
	if ($a)
		$found = TRUE;
	return $found;
}

function adi_notes_upgrade($do_upgrade=FALSE) {
// check format of adi_notes table / copy from previous version backup table if required
	global $adi_notes_debug, $prefs;

	if (adi_notes_installed()) { // find out what needs upgrading

		// pre-v1.0 - check for presence of 'id' column
		$rs = safe_query("SHOW COLUMNS FROM ".safe_pfx('adi_notes')." LIKE 'id'", $adi_notes_debug); // "SHOW COLUMNS WHERE" no good for MySQL version 4.1
		$a = nextRow($rs);
		$pre_v1_0 = empty($a);

		// v1.0 - look for non-event names - 'comment','user','variables','adi_menu','adi_prefs' (should be 'discuss','admin','adi_variables_admin','adi_menu_admin','adi_prefs_admin')
		$oldies = "'comment','user','variables','adi_menu','adi_prefs'";
		$newbies = "'discuss','admin','adi_variables_admin','adi_menu_admin','adi_prefs_admin'";
		$v1_0 = safe_rows('*','adi_notes',"name IN ($oldies)", $adi_notes_debug); // read data from current table
		$v1_0 = !empty($v1_0);

		// v1.3 - look for txp_tag_privs preference
		$v1_3 = !isset($prefs['adi_notes_txp_tag_privs']);

		$upgrade_required = $pre_v1_0 || $v1_0 || $v1_3;

		if ($adi_notes_debug) echo "pre_v1_0=$pre_v1_0, v1_0=$v1_0, v1_3=$v1_3, upgrade_required=$upgrade_required".br;

		if ($do_upgrade) {
			if ($upgrade_required) {
				if ($pre_v1_0)
					$backup_version = 'pre_v1_0';
				else
					$backup_version = 'post_v1_0';
				if ($adi_notes_debug) echo 'Upgrading from '.$backup_version.br;
				$res = TRUE; // optimistic
				$current_rs = safe_rows('*', 'adi_notes', "1=1", $adi_notes_debug); // read data from current table
				if (isset($last_mod))
					$last_mod_q = "'$last_mod'"; // date & time quoted
				else
					$last_mod_q = "NULL"; // NULL unquoted
				// take backup
				if (adi_notes_installed('adi_notes_old')) // delete previous "upgrade" backup
					$res = adi_notes_drop('adi_notes_old');
				$res = $res && adi_notes_copy('adi_notes', 'adi_notes_old', $backup_version);	// take an "upgrade" backup
				// do upgrade stuff
				if ($pre_v1_0) {
					// change table structure
					$res = $res && adi_notes_drop('adi_notes'); // delete previous version table
					$res = $res && adi_notes_install(FALSE); // create vanilla new format table
					foreach ($current_rs as $name => $row) { // copy note data to new table
						extract($row);
						if ($adi_notes_debug) dmp($row);
						$res = $res && safe_query(
							"INSERT INTO ".
							safe_pfx('adi_notes').
							" VALUES (DEFAULT,'".$name."','tab',DEFAULT,DEFAULT,'".$visible."',".$last_mod_q.",'".$last_mod_ID."','".doSlash($note)."')", $adi_notes_debug
							);
					}
				}
				if ($v1_0) {
					if ($adi_notes_debug) echo 'v1_0 upgrade:'.br;
					// rename non-event names
					$old_list = explode(',', $oldies);
					$new_list = explode(',', $newbies);
					foreach ($old_list as $index => $name) {
						$rs = safe_row('*', 'adi_notes', "name=$name", $adi_notes_debug);
						if ($rs)
							$res = $res && safe_update('adi_notes', "name=$new_list[$index]", "name=$name", $adi_notes_debug);
					}
					// switch embedded note visibility on (for consistency & possible future use)
					$res = $res && safe_update('adi_notes', "visible=1", "type='embed'", $adi_notes_debug);
				}
				if ($v1_3) {
					if ($adi_notes_debug) echo 'v1_3 upgrade:'.br;
					// add TXP tag privs preference
					$res = $res && adi_notes_prefs('txp_tag_privs', '');
					// make copy of old custom style
					(isset($prefs['adi_notes_custom_style'])) ? $old_custom_style = adi_notes_prefs('custom_style') : $old_custom_style = '';
					$res = $res && adi_notes_prefs('custom_style_old', $old_custom_style);
					// make copy of old width style
					(isset($prefs['adi_notes_width'])) ? $old_width = adi_notes_prefs('width') : $old_width = '';
					$res = $res && adi_notes_prefs('width_old', $old_width);
					// massage v1.1 width pref
					if ((strpos($old_width, 'width:') === FALSE) && $old_width) // v1.1 width pref didn't have "width:"
						$res = $res && adi_notes_prefs('width', 'width:'.$old_width);
					// wrap old custom style in #adi_notes selector
					(isset($prefs['adi_notes_custom_style'])) ? $new_custom_style = '#adi_notes { '.adi_notes_prefs('custom_style').' }' : $new_custom_style = adi_notes_prefs('custom_style');
					// massage v1.1 width pref
					if ((strpos($old_width, 'width:') === FALSE) && $old_width) // v1.1 width pref didn't have "width:"
						$old_width = 'width:'.$old_width;
					if (empty($old_width)) // use new default
						$old_width = adi_notes_prefs('width');
					//??? add old width onto end of custom style ONLY NEED TO DO THIS IF UPGRADING FROM v1.1 or v1.2 DO A STRPOS CHECK TO SEE IF WIDTH PRESENT & CHECK IF CUSTOM HAS BEEN EDITED OR DON'T BOTHER AT ALL
				}
				return $res;
			}
			else
				return FALSE;
		}
		else
			return $upgrade_required;
	}
	else
		return FALSE;
}

function adi_notes_copy($source, $destination, $backup_version='') {
// copy table: source -> destination
	global $adi_notes_debug;

	$res = safe_query("CREATE TABLE ".safe_pfx($destination)." LIKE ".safe_pfx($source).";", $adi_notes_debug);
	// can't use following because single quotes etc. in $note need to be escaped
	// $res = $res && safe_query("INSERT ".safe_pfx($destination)." SELECT * FROM ".safe_pfx($source).";",$adi_notes_debug);
	$rs = safe_rows('*', $source, "1=1", $adi_notes_debug);
	foreach ($rs as $index => $row) {
		extract($row);
		if (isset($last_mod))
			$last_mod_q = "'$last_mod'"; // date & time quoted
		else
			$last_mod_q = "NULL"; // NULL unquoted
		if ($backup_version == 'pre_v1_0')
			$res = $res && safe_query(
						"INSERT INTO ".
						safe_pfx($destination).
						" VALUES ('".$name."','".$visible."',".$last_mod_q.",'".$last_mod_ID."','".$restricted_privs."','".$restricted_ID."','".doSlash($note)."')", $adi_notes_debug
						);
		else
			$res = $res && safe_query(
						"INSERT INTO ".
						safe_pfx($destination).
						" VALUES (DEFAULT,'".$name."','".$type."','".$user."','".$privs."','".$visible."',".$last_mod_q.",'".$last_mod_user."','".doSlash($note)."')", $adi_notes_debug
						);
	}
	return $res;
}

function adi_notes_rename($source, $destination) {
// rename table: source -> destination
	global $adi_notes_debug;

	return safe_query("RENAME TABLE ".safe_pfx($source)." TO ".safe_pfx($destination), $adi_notes_debug);
}

function adi_notes_drop($db_table) {
// delete a table
	global $adi_notes_debug;

	return safe_query("DROP TABLE ".safe_pfx($db_table).";", $adi_notes_debug);
}

function adi_notes_delete_prefs() {
// leave no trace
	global $adi_notes_prefs, $adi_notes_debug;

	$res = TRUE;
	foreach ($adi_notes_prefs as $name => $value)
		if (safe_row("*", 'txp_prefs', "name = 'adi_notes_$name'", $adi_notes_debug))
			$res = $res && safe_delete('txp_prefs', "name = 'adi_notes_$name'", $adi_notes_debug);
	return $res;
}

function adi_notes_lifecycle($event, $step) {
// a matter of life & death
// $event:	"plugin_lifecycle.adi_plugin"
// $step:	"installed", "enabled", disabled", "deleted"
// TXP 4.5: reinstall/upgrade only triggers "installed" event (now have to manually detect whether upgrade required)
	global $adi_notes_debug;

	$result = '?';
	// set upgrade flag if reinstalling in TXP 4.5+
	$upgrade = (($step == "installed") && adi_notes_installed());
	if ($step == 'enabled')
		$result = $upgrade = adi_notes_install(); // still need to run upgrade on new install, to run v1.3 steps
	else if ($step == 'deleted') {
		$result = adi_notes_drop('adi_notes');
		$result = $result && adi_notes_delete_prefs();
	}
	if ($upgrade)
		$result = $result && adi_notes_upgrade(TRUE);
	if ($adi_notes_debug)
		echo "Event=$event Step=$step Result=$result Upgrade=$upgrade";
}

function adi_notes_tab_visibility() {
// create visibility list array of note tabs from database
	global $adi_notes_tab_list, $adi_notes_debug;

	$visibility = array();
	// get current note tab visibility from database (BEWARE user tab event = "admin")
	$rs = safe_rows('*', 'adi_notes', "type='tab'", $adi_notes_debug);
	foreach ($rs as $index => $row) {
		extract($row);
		if (array_key_exists($name, $adi_notes_tab_list))
			$visibility[$name] = $visible; // create array, indexed by name, of note tab visibility found in database
	}
	// compare $adi_notes_tab_list with $visibility & add any missing tabs to array
	foreach ($adi_notes_tab_list as $tab_name => $tab_info)
		if (!array_key_exists($tab_name, $visibility))
			$visibility[$tab_name] = 0;
	return $visibility;
}

function adi_notes_update_visibility($visibility) {
// update database with new note tab visibility info (adding any newly visible note tabs to the mix at the same time)
	global $adi_notes_debug;

	$res = TRUE;
	// switch all note tab visibility off (BEWARE user tab event = "admin")
	$rs = safe_rows('*', 'adi_notes', "type='tab'", $adi_notes_debug);
	foreach ($rs as $index => $row) {
		extract($row);
		$res = $res && safe_update('adi_notes', "visible=0", "name='$name' AND type = 'tab'", $adi_notes_debug);
	}
	if ($visibility) {
		// selectively switch visibility back on (and insert row if missing)
		foreach ($visibility as $tab_name => $visible) {
			$found = safe_row('name', 'adi_notes', "name='$tab_name' AND type='tab'", $adi_notes_debug);
			if ($found)
				$res = $res && safe_update('adi_notes', "visible=$visible", "name='$tab_name' AND type='tab'", $adi_notes_debug);
			else
// 				$res = $res && safe_insert('adi_notes',"name='$tab_name', visible=$visible, type='tab'",$adi_notes_debug); // (pre-1.3.1) set type = 'tab' coz it's not the default (historical quirk in CREATE TABLE)
				$res = $res && safe_insert('adi_notes', "name='$tab_name', visible=$visible, type='tab', note=''", $adi_notes_debug); // set type = 'tab' coz it's not the default (historical quirk in CREATE TABLE)
		}
	}
	return $res;
}

function adi_notes_read($note_name, $type, $note_id, $user) {
// read given note from database
	global $adi_notes_debug, $adi_notes_txp470;

	$this_note = array();
	if (empty($note_id)) { // selection of note based on context
		$where = "name='".$note_name."' AND type='".$type."'";
		$user ?
			$where .= " AND user='".$user."'" :
			$where .= " AND user=''";
		$this_note = safe_row("*", 'adi_notes', $where, $adi_notes_debug);
	}
	else // specific note id supplied
		$this_note = safe_row("*", 'adi_notes', "id='".$note_id."'", $adi_notes_debug);
	if ($this_note) {
		if (adi_notes_prefs('markup') == 'textile') {
			if ($adi_notes_txp470) {
				$textile = new \Textpattern\Textile\Parser();
				$this_note['formatted'] = $textile->parse($this_note['note']);
			}
			else {
				$textile = new Textile;
				$this_note['formatted'] = $textile->TextileThis($this_note['note']);
			}
		}
		else
			$this_note['formatted'] = $this_note['note'];
	}
	return $this_note;
}

function adi_notes_tab($event, $step) {
// display note tab
	global $pretext, $adi_notes_debug, $adi_notes_tab_list, $adi_notes_prefs, $adi_notes_txp460;

	include_once txpath.'/publish.php';

	$message = '';

	// style
	adi_notes_prefs('style') ?
		$class = ' class="adi_notes_'.adi_notes_prefs('style').'"' :
		$class = '';

	$note_name = substr($event, strlen("adi_notes_tab_")); // strip "adi_notes_tab_" from event name
	if (adi_notes_upgrade())
		pagetop($adi_notes_tab_list[$note_name]['title'].' - '.adi_notes_gtxt('adi_notes'), 'adi_notes: <strong>'.adi_notes_gtxt('adi_upgrade_required').'</strong>');
	else {
		$adi_notes_step = gps('adi_notes_step');
		$adi_notes_id = gps('adi_notes_id');
		$adi_notes_user = gps('adi_notes_user');
		$adi_notes_note = gps('adi_notes_note');
		$adi_notes_convert = gps('adi_notes_convert');

		if ($adi_notes_debug)
			echo __FUNCTION__.": event=$event, step=$step, adi_notes_step=$adi_notes_step, note_name=$note_name, adi_notes_id=$adi_notes_id, adi_notes_user=$adi_notes_user";

		// one $step at a time
		if ($adi_notes_step == 'save') {
			$res = adi_notes_save($note_name, 'tab', $adi_notes_id, '', $adi_notes_note);
			if ($adi_notes_note)
				$res ? $message = adi_notes_gtxt('adi_note_saved') : $message = adi_notes_gtxt('adi_note_save_fail');
			else
				$res ? $message = adi_notes_gtxt('adi_note_deleted') : $message = adi_notes_gtxt('adi_note_delete_fail');
		}

		// decide what to display
		if ($adi_notes_step == 'edit')
			$thing = adi_notes_edit_form($note_name, 'tab', $adi_notes_id, '', $adi_notes_convert);
		else {
			$this_note = adi_notes_read($note_name, 'tab', $adi_notes_id, '');
			$thing = $this_note['formatted'];
			if ($this_note['note'])
				$linktext = gTxt('edit');
			else
				$linktext = adi_notes_gtxt('adi_add_note');
			$thing = parse($thing);
		}

		// output page
		pagetop($adi_notes_tab_list[$note_name]['title'].' - '.adi_notes_gtxt('adi_notes'), $message);
		if ($thing)
			echo tag($thing, 'div',' id="adi_notes"'.$class);

		// display add/edit link
		if (adi_notes_has_privs(adi_notes_prefs('note_tab_edit_privs')) && !($adi_notes_step == 'edit'))
			echo graf(
				'['
				.($adi_notes_txp460 ?
					href(
						$linktext
						,array(
							'event' => $event,
							'adi_notes_step' => 'edit',
							'adi_notes_id' => $this_note['id'],
							'adi_notes_user' => ''
						)
// 						,array('class' => 'small')
					)
				:
					slink($event, '&amp;adi_notes_step=edit'.'&amp;adi_notes_id='.$this_note['id'].'&amp;adi_notes_user=', $linktext, 'small')
				)
				.']'
				,' class="adi_notes_tab_link"'
			);
	}
}

function adi_notes_edit_form($note_name, $type, $note_id, $note_user, $convert='') {
// display individual note edit form
	global $event, $step, $adi_notes_login_user, $timeoffset, $adi_notes_debug, $adi_notes_txp460, $adi_notes_txp470;

	$preserve_urlvar = adi_notes_preserve_urlvar();
	if ($note_id) { // read note from database
		$this_note = safe_row("*", 'adi_notes', "id='".$note_id."'", $adi_notes_debug);
		extract($this_note); // $name, $type, $user, $privs, $visible, $last_mod, $last_mod_user, $note
		$realname = fetch('RealName', 'txp_users', 'name', $last_mod_user);
		$mod_msg = gTxt('modified_by')." $realname: ".date("H:i, d M Y", strtotime($last_mod) + $timeoffset);
		if ($convert == 'textile') {
			if ($adi_notes_txp470) {
				$textile = new \Textpattern\Textile\Parser();
				$note = $textile->parse($note);
			}
			else {
				$textile = new Textile;
				$note = $textile->TextileThis($note);
			}
		}
	}
	else // note doesn't exist yet
		$note = '';

	if (strpos($event, 'adi_notes_tab_') === FALSE) { // don't want a footnote on a Notes tab
		if ($note_user) {
			$adi_notes_step = 'save_user';
			$title = adi_notes_gtxt('adi_note_editing_private').':';
		}
		else {
			$submit_button = gTxt('save');
			$adi_notes_step = 'save';
			$title = adi_notes_gtxt('adi_note_editing_public').':';
		}
	}
	else {
		$adi_notes_step = 'save';
		$title = '';
	}

	if ($type == 'tab')
		$extra_class = ' adi_notes_textarea_tab';
	else
		$extra_class = '';
	if (adi_notes_prefs('tiny_mce') != 'none')
		$extra_class .= ' adi_notes_mceEditor';

	if ($title)
		$title = tag($title, 'p', ' class="adi_notes_label"');
	$out[] =
		$title
		.'<textarea name="adi_notes_note" class="adi_notes_textarea'.$extra_class.'">'.htmlspecialchars($note).'</textarea>'
		.graf(
			fInput('submit', 'savenote', gTxt('save'), 'publish')
			.eInput($event)
			.sInput($step)
			.($preserve_urlvar ? hInput(adi_notes_preserve_urlvar('var'), adi_notes_preserve_urlvar('value')) : '') // pass on edit screen article/image ID/id
			.hInput('adi_notes_step', $adi_notes_step)
			.($note_user ? hInput('adi_notes_user_note_id', $note_id) : hInput('adi_notes_id', $note_id))
			.hInput('adi_notes_user', $adi_notes_login_user)
			.(empty($note) ? '' : $mod_msg)
			.((adi_notes_prefs('markup') != 'textile') && adi_notes_prefs('convert_link') && !empty($note) ? // not textile AND convert link requested AND non-empty note
				sp
				.sp
				.'['
				.($adi_notes_txp460 ?
					tag(
						adi_notes_gtxt('adi_convert_textile')
						,'a'
						,'href="'.join_qs(array('event'=>$event, 'adi_notes_step'=>'edit', 'adi_notes_user'=>'', 'adi_notes_id'=>$this_note['id'], 'adi_notes_convert'=>'textile')).'"'
					)
				:
					slink($event, '&amp;adi_notes_step=edit'.'&amp;adi_notes_id='.$this_note['id'].'&amp;adi_notes_user=&amp;adi_notes_convert=textile', adi_notes_gtxt('adi_convert_textile'), 'small')
				)
				.']'
			:
				''
			)
			,' class="adi_notes_save"'
		);

	if ($adi_notes_debug)
		$note_user ?
		$out[] = '<p>'.__FUNCTION__.": Event=$event,Step=$step,adi_notes_step=$adi_notes_step,note_name=$note_name,adi_notes_id=$note_id,note_user=$note_user,adi_notes_login_user=$adi_notes_login_user</p>" :
		$out[] = '<p>'.__FUNCTION__.": Event=$event,Step=$step,adi_notes_step=$adi_notes_step,note_name=$note_name,adi_notes_user_note_id=$note_id,note_user=$note_user,adi_notes_login_user=$adi_notes_login_user</p>";

	if (adi_notes_prefs('position') == 'header')
		$anchor = ''; // jump back to top of page after edit headnotes
	else
		$anchor = 'adi_notes';

	return form(implode('', $out), '', '', 'post', '', $anchor);
}

function adi_notes_save($note_name, $type, $note_id, $user, $note) {
// save note to database
	global $event, $adi_notes_login_user, $adi_notes_debug;

	$note_name = doSlash($note_name);
	$type = doSlash($type);
	$note_id = doSlash($note_id);
	$user = doSlash($user);
	$note = doSlash($note);
	empty($note_id) ? // new note
		$note_id = "DEFAULT" :
		$note_id = "'".$note_id."'";
	empty($user) ? // blank user = public note
		$user = "DEFAULT" :
		$user = "'".$user."'";

	update_lastmod();

	return safe_query(
		"REPLACE INTO ".
		safe_pfx('adi_notes').
		" VALUES ($note_id,'$note_name','$type',$user,DEFAULT,'1',now(),'$adi_notes_login_user','$note')", $adi_notes_debug
		);
}

function adi_notes_preserve_urlvar($what='') {
// get an $event specific URL var & return various value permutations
	global $event, $adi_notes_txp460;

	$this_var = array();
	$var_name = '';
	// define which one
	if ($event == 'article')
		$var_name = 'ID'; // upper case ID
	if ($event == 'image')
		$var_name = 'id'; // lower case id
	if (($event == 'page') || ($event == 'css') || ($event == 'form'))
		$var_name = 'name';
	// get it
	if ($var_name) {
		$value = gps($var_name);
		if ($value) {
			$this_var['var'] = $var_name;
			$this_var['value'] = $value;
		}
	}
	// sort out what to return
	if ($this_var) {
		if ($what == 'var')
			return $this_var['var'];
		else if ($what == 'value')
			return $this_var['value'];
		else
			return '&amp;'.$this_var['var'].'='.$this_var['value'];
	}
	else
		return '';
}

function adi_notes_embed_note($x, $y, $default) {
// generate markup for embedded notes
	global $pretext, $event, $step, $adi_notes_debug, $adi_notes_prefs, $adi_notes_login_user, $adi_notes_txp460;

	include_once txpath.'/publish.php';

	if (strpos($event, 'adi_notes_tab_') !== FALSE) // don't want a embedded note on a Notes tab
		return '';
	if ($event == 'adi_notes_options') // don't want embedded note on txpdev adi_notes options page
		return '';
	if ($event == 'plugin') // don't want a embedded note during lifecycle events on plugin page (e.g. plugin delete)
		return '';

	$adi_notes_step = gps('adi_notes_step');
	$note = gps('adi_notes_note');
	$note_id = gps('adi_notes_id');
	$note_name = $event;
	$note_user = gps('adi_notes_user');
	$user_note_id = gps('adi_notes_user_note_id');

	// class
	adi_notes_prefs('style') ?
		$class = 'adi_notes_'.adi_notes_prefs('style') :
		$class = '';
	if (adi_notes_prefs('position') == 'header')
		$class .= ' adi_notes_embed_headnote';
	else
		$class .= ' adi_notes_embed_footnote';
	if ($class)
		$class = ' class="'.$class.'"';

	$admin_note = $user_note = $edit_note = $edit_user_note = '';

	$preamble = '<div id="adi_notes"'.$class.'>';
	$postamble = '</div>';

	if ($adi_notes_debug)
		echo __FUNCTION__.": event=$event, step=$step, adi_notes_login_user=$adi_notes_login_user, note_user=$note_user, adi_notes_step=$adi_notes_step, note_name=$note_name, adi_notes_id=$note_id, adi_notes_user_note_id=$user_note_id";

	if ($adi_notes_step == 'edit')
		$edit_note = adi_notes_edit_form($note_name, 'embed', $note_id, '');
	else if ($adi_notes_step == 'save')
		adi_notes_save($note_name, 'embed', $note_id, '', $note);
	else if (($adi_notes_step == 'edit_user') && adi_notes_has_privs('1,2,3,4,5,6'))
		$edit_user_note = adi_notes_edit_form($note_name, 'embed', $user_note_id, $note_user);
	else if ($adi_notes_step == 'save_user')
		adi_notes_save($note_name, 'embed', $user_note_id, $note_user, $note);

	if (!($adi_notes_step == 'edit')) {
		$this_note = adi_notes_read($note_name, 'embed', '', '');
		if (!empty($this_note)) {
			$admin_note = $this_note['formatted'];
			$admin_note = parse($admin_note);
			$note_id = $this_note['id'];
		}
	}

	if (!($adi_notes_step == 'edit_user')) {
		$this_note = adi_notes_read($note_name, 'embed', '', $adi_notes_login_user);
		if (!empty($this_note) && adi_notes_has_privs('1,2,3,4,5,6')) {
			$user_note = $this_note['formatted'];
			if (adi_notes_has_privs(adi_notes_prefs('txp_tag_privs'))) // only parse private notes if user has sufficient privs
				$user_note = parse($user_note);
			$user_note_id = $this_note['id'];
		}
	}

	// set up what needs to be output
	$out = '';
	if (($admin_note || $edit_note) && ($user_note || $edit_user_note))
		$hr = '<hr class="adi_notes_hr"/>';
	else
		$hr ='';
	if ($admin_note || $edit_note || $user_note || $edit_user_note)
		$out .= $preamble.$admin_note.$edit_note.$hr.$user_note.$edit_user_note.$postamble;

	if ($adi_notes_txp460)
		echo $out;
	else
		return $out; // adi_notes_embed_note_links() sorts out $default (div#end_page)
}

function adi_notes_embed_note_links($x, $y, $default) {
// create edit/add links for embedded notes
	global $event, $step, $adi_notes_debug, $adi_notes_txp460, $adi_notes_login_user;

	if (strpos($event, 'adi_notes_tab_') !== FALSE) // don't want a embedded note on a Notes tab
		return '';
	if ($event == 'adi_notes_options') // don't want embedded note on txpdev adi_notes options page
		return '';
	if ($event == 'plugin') // don't want a embedded note during lifecycle events on plugin page (e.g. plugin delete)
		return '';
	if (!adi_notes_has_privs('1,2,3,4,5,6'))
		return $default;

	// article edit & image edit screens get special treatment
	$this_step = $step;
	if (($event != 'article') && ($event != 'image'))
		$this_step = ''; // too dangerous to preserve step (in edit/add links) if not article/image edit screen
	$preserve_urlvar = adi_notes_preserve_urlvar(); // article/image edit tab id/ID

	$adi_notes_step = gps('adi_notes_step');
	$note_name = $event;

	if (adi_notes_prefs('position') == 'header')
		$anchor = ''; // jump back to top of page after editing headnotes
	else
		$anchor = '#adi_notes';

	$out = '';
	$admin_link = $user_link = '';

	// set up admin link
	$admin_link = '';
	if (!($adi_notes_step == 'edit')) {
		$this_note = adi_notes_read($note_name, 'embed', '', '');
		if (!empty($this_note)) {
			$admin_note = $this_note['formatted'];
			$note_id = $this_note['id'];
		}
		else {
			$admin_note = '';
			$note_id = '';
		}
		if ($admin_note)
			$link_text = adi_notes_gtxt('adi_edit_public_note');
		else
			$link_text = adi_notes_gtxt('adi_add_public_note');
		if (adi_notes_has_privs(adi_notes_prefs('public_note_edit_privs')))
			$admin_link =
				'['
				.($adi_notes_txp460 ?
					tag(
						$link_text
						,'a'
						,'href="'.join_qs(array('event'=>$event, 'step'=>$this_step, 'adi_notes_step'=>'edit', 'adi_notes_id'=>$note_id)).$preserve_urlvar.$anchor.'"'
					)
				:
					slink($event, $this_step.$preserve_urlvar.'&amp;adi_notes_step=edit'.'&amp;adi_notes_id='.$note_id.$anchor, $link_text, 'small')
				)
				.']';

		if ($adi_notes_debug)
			echo __FUNCTION__.": event=$event, step=$step, adi_notes_login_user=$adi_notes_login_user, this_step=$this_step, preserve_urlvar=$preserve_urlvar, adi_notes_step=$adi_notes_step, note_name=$note_name, adi_notes_id=$note_id";
	}

	// set up user link
	$user_link = '';
	if (!($adi_notes_step == 'edit_user')) {
		$this_note = adi_notes_read($note_name, 'embed', '', $adi_notes_login_user);
		if (!empty($this_note)) {
			$user_note = $this_note['formatted'];
			$user_note_id = $this_note['id'];
		}
		else {
			$user_note = '';
			$user_note_id = '';
		}
		if ($user_note) {
			$link_text = adi_notes_gtxt('adi_edit_private_note');
			$note_user = $this_note['user'];
		}
		else {
			$link_text = adi_notes_gtxt('adi_add_private_note');
			$note_user = $adi_notes_login_user;
		}
		$user_link =
			' ['
			.($adi_notes_txp460 ?
				tag( //? TOKEN
					$link_text
					,'a'
					,'href="'.join_qs(array('event'=>$event, 'step'=>$this_step, 'adi_notes_step'=>'edit_user', 'adi_notes_user_note_id'=>$user_note_id, 'adi_notes_user'=>$note_user)).$preserve_urlvar.$anchor.'"'
				)
			:
				slink($event, $this_step.$preserve_urlvar.'&amp;adi_notes_step=edit_user'.'&amp;adi_notes_user_note_id='.$user_note_id.'&amp;adi_notes_user='.$note_user.$anchor, $link_text, 'small')
			)
			.']';

		if ($adi_notes_debug)
			echo __FUNCTION__.": event=$event, step=$step, this_step=$this_step, adi_notes_login_user=$adi_notes_login_user, note_user=$note_user, preserve_urlvar=$preserve_urlvar, adi_notes_step=$adi_notes_step, note_name=$note_name, adi_notes_user_note_id=$user_note_id";
	}

	$out .= graf($admin_link.$user_link, ' class="adi_notes_embed_links"');

	if ($adi_notes_txp460)
		echo $out; // & shifted by jQuery
	else
		return $out.$default;
}

function adi_notes_gtxt($phrase, $atts=array()) {
// will check installed language strings before embedded English strings - to pick up Textpack
// - for TXP standard strings gTxt() & adi_notes_gtxt() are functionally equivalent
	global $adi_notes_gtxt;

	if (gTxt($phrase, $atts) == $phrase) // no TXP translation found
		if (array_key_exists($phrase, $adi_notes_gtxt)) // adi translation found
			return $adi_notes_gtxt[$phrase];
		else // last resort
			return $phrase;
	else // TXP translation
		return gTxt($phrase, $atts);
}
