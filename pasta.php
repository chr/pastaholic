<?php

/********************************************************************
 *
 * Pastaholic: The 1000000th pastebin.
 *
 * All files (except geshi.php and those in geshi/) are released into
 * the public domain. For more information, see http://unlicense.org/
 * or the accompanying UNLICENSE file.
 *
 ********************************************************************/

$script_time_start = microtime(true);

$php_version = '4.3';

if ( version_compare(PHP_VERSION, $php_version) < 0 ) {
	die("At least version $php_version is required.");
}

if ( file_exists('pasta_config.ini') ) {
	$config = parse_ini_file('pasta_config.ini', true);
} else {
	die('Ops, no pasta_config.ini');
}

if ( isset($config['main']['geshi']) ) {
	$geshi_php = $config['main']['geshi'] . '/' . 'geshi.php';
} else {
	$geshi_php = 'geshi.php';
}

if ( file_exists($geshi_php) ) {
	include_once $geshi_php;
} else {
	die ('Could not locate geshi.');
}

if ( $_POST['pasta'] != '' ) {
	$limit = isset($config['main']['limit']) ? $config['main']['limit'] : 20;
	$limit *= 1024;
	if (mb_strlen($_POST['pasta'], '8bit') >= 20480) {
		die('Please, don\'t. Keep it below 20K.');
	}

	$source = $_POST['pasta'];
	$lang = ( $_POST['lang'] ) ? $_POST['lang'] : 'txt';
	if ( $_POST['title'] ) {
		$title = $_POST['title'] . ' ';
	}
	$title .= date('c');
} else {
	die('Pasta required.');
}

function file_name() {
	return base_convert(time() - 1290000000, 10, 36);
}

function write_file($content, $file) {
	$fh = fopen($file, 'w');
	fwrite($fh, $content);
	fclose($fh);

	if ( file_exists($file) ) {
		return true;
	} else {
		return false;
	}
}

$geshi = new GeSHi($source, $lang);

if ( $geshi->error() ) {
	die('An error occurred. You broke the internet.');
}

// Will contain the rendered geshi page: 1) header; 2) content; 3) footer.
$page = array();

// CLASSES
// They seem to work only if numbers are set
if ( isset($config['classes']) ) {
	$geshi->enable_classes();
	if ( $config['classes']['id'] ) {
		$geshi->set_overall_class($config['classes']['id']);
	} else {
		$geshi->set_overall_class('pastaclass');
	}
	if ( $config['classes']['class'] ) {
		$geshi->set_overall_class($config['classes']['class']);
	} else {
		$geshi->set_overall_id('pastaid');
	}
}

// STYLES
if ( isset($config['style']) ) {

	// XXX Also: scape_characters, symbols, methods, regexps
	// XXX header_content and footer_content are not styled if classes are set
	$style_types = array(
		'overall',
		'code',
		'numbers',
		'header_content',
		'footer_content',
		'strings',
		'keyword_group1',
		'keyword_group2',
		'keyword_group3',
		'keyword_group4',
		'comments_line',
		'comments_multi'
	);

	foreach($style_types as $style_type) {

		if ( isset($config['style'][$style_type]) ) {

			$style = '';
			$set_extra_arg = '';
			foreach($config['style'][$style_type] as $key => $value) {
				$style .= $key . ': ' . $value . '; ';
			}

			if ( preg_match('/^keyword_group/', $style_type) ) {
				$set_extra_arg = substr($style_type, -1);
				$style_type = 'keyword_group';
			}

			if ( preg_match('/^comments_/', $style_type) ) {
				if ( substr($style_type, -1) == 'e' ) {
					$set_extra_arg = '1';
				} else {
					$set_extra_arg = 'MULTI';
				}
				$style_type = 'comments';
			}

			$geshi_set = 'set_' . $style_type . '_style';

			if ( empty($set_extra_arg) ) {
				$geshi->$geshi_set($style, true);
			} else {
				$geshi->$geshi_set($set_extra_arg, $style, true);
			}
		}
	}

	if ( isset($config['style']['line']) && isset($config['style']['fancy']) ) {
		$style1 = '';
		$style2 = '';
		foreach($config['style']['line'] as $key => $value) {
			$style1 .= $key . ': ' . $value . '; ';
		}
		foreach($config['style']['fancy'] as $key => $value) {
			$style2 .= $key . ': ' . $value . '; ';
		}
		$geshi->set_line_style($style1, $style2);
	} elseif ( isset($config['style']['line']) ) {
		$style = '';
		foreach($config['style']['line'] as $key => $value) {
			$style .= $key . ': ' . $value . '; ';
		}
		$geshi->set_line_style($style);
	}
}

if ( $geshi_php != 'geshi.php' ) {
	$geshi->set_language_path($config['main']['geshi'] . '/geshi');
}

// CONTAINER
if ( isset($config['main']['container']) ) {
	$i = strtoupper($config['main']['container']);
	if ( $i == 'PRE' || $i == 'DIV' || $i == 'PRE_VALID' || $i == 'PRE_TABLE' ) {
		$geshi->set_header_type('GESHI_HEADER_' . $i);
	}
else
	$geshi->set_header_type(GESHI_HEADER_NONE);
}

// NUMBERS
if ( isset($config['main']['numbers']) ) {
	$i = $config['main']['numbers'];
	if ( $i == 'normal' ) {
		$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
	} elseif ( $i == 'fancy' ) {
		$geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS, 2);
	}
}

// TABS
if ( isset($config['main']['tabs']) ) {
	$geshi->set_tab_width($config['main']['tabs']);
}

// HEADER
if ( isset($config['main']['header_content']) ) {
	$geshi->set_header_content($config['main']['header_content']);
}

// FOOTER
if ( isset($config['main']['footer_content']) ) {
	$geshi->set_footer_content($config['main']['footer_content']);
}

// ACTUAL PAGE
$i = $config['template']['header'];
if ( isset($i) && file_exists($i) ) {
	$template_header = file_get_contents($i);
} else {
	$template_header = '<html><head><title></title></head><body>';
}

if ( isset($config['classes']) ) {
	$geshi_style = '<style type="text/css"><!--';
	$geshi_style .= $geshi->get_stylesheet();
	$geshi_style .= '--></style>';
	$page['header'] = $template_header;
	$page['header'] = str_ireplace('<head>', "<head>$geshi_style", $page['header']);
	$page['header'] = str_ireplace('<title>', "<title>$title ", $page['header']);
	$div_title = '<div id="title">' . $title . '</div>';
	$page['header'] = str_ireplace('<body>', "<body>$div_title", $page['header']);
}

$page['geshi'] = $geshi->parse_code();

$script_time_end = microtime(true);
$script_time_total = $script_time_end - $script_time_start;
$page['content'] = '<!--Generated in ' . $script_time_total . ' seconds.-->';

$i = $config['template']['footer'];
if ( isset($i) && file_exists($i) ) {
	$page['footer'] = file_get_contents($i);
} else {
	$page['footer'] = '</body></html>';
}

foreach ( $page as $value ) {
	$page_rendered .= $value;
}

$file_str = file_name();
if ( isset($config['main']['directory']) ) {
	$file_str = $config['main']['directory'] . '/' . $file_str;
}
$file = $file_str . '.html';

if ( write_file($page_rendered, $file) ) {
	$location = $_SERVER[HTTP_HOST] . dirname($_SERVER[SCRIPT_NAME]);
	header("Location: http://$location/$file_str");
} else {
	die('Ops :-(');
}

?>
