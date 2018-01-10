<?php
/*
Copyright (c) 2017 coyote03

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

define('BASE', 'https://api.vk.com/method/');
define('VKURL', 'https://vk.com/');
define('APIVERSION', '5.69');
define('VERSION', 'vkfeed2rss v0.5.4');
define('RSSVERSION', '2.0');

// page type
define('TGROUP', 0);
define('TUSER', 1);

// post type
define('PPOST', 0);
define('PREPOST', 1);

// call constants from strings:
// define('ANIMAL','turtles'); $constant='constant'; echo "I like {$constant('ANIMAL')}";
$constant = 'constant';

/*
 * convert vk url (https://vk.com/apiclub) to vk domain (apiclub)
 * Supported url forms:
 * "https://vk.com/id1"
 * "vk.com/id1"
 * "id1" (use of path is recommended)
 */
function vk_path_from_url(string $url) {
	for ($i = 0; $i < 3; $i++) { // try to parse for 3 tries
		switch ($i) { // tries
			case 0: $tmp = parse_url($url); break;
			case 1: $tmp = parse_url("https://{$url}"); break;
			case 2: $tmp = parse_url(VKURL . $url); break;
		}

		if (($tmp['scheme'] == 'http' or $tmp['scheme'] == 'https') // check
		and $tmp['path'] != '/' and $tmp['path'])
			return substr($tmp['path'], 1);
	}

	// if the function can't parse the url, die
	die("Bad url");
}

// short api calling&decoding
function json_get_contents(string $str) {
	return json_decode(file_get_contents($str), true, 16, JSON_BIGINT_AS_STRING);
}

// get some configuration variables
function config_get() {
	// some functions needs vk api key
	$tmp = json_get_contents("conf/vkfeed2rss.json");
	$config['apikey'] = $tmp['apikey'];
	if (!$config['apikey']) // if no apikey or file not found
		die("No api key");

	// path
	if (isset($_GET['path']))
		$config['path'] = $_GET['path'];
	elseif (isset($_GET['url']))
		$config['path'] = vk_path_from_url($_GET['url']);
	else
		die("url or path has to be specified");

	// count
	if (isset($_GET['count'])) {
		if ((int)$_GET['count'] < 0 or (int)$_GET['count'] > 100)
			die("Count limit is from 0 to 100");
		else
			$config['count'] = (int)$_GET['count'];
	}

	// filter
	if (isset($_GET['filter'])) {
		switch($_GET['filter']) {
			case 'all':
			case 'owner':
			case 'others': $config['filter'] = $_GET['filter']; break;
			default: die("Only all, owner and others filters are supported");
		}
	}

	// resolving page's id and type
	$tmp = json_get_contents("{$GLOBALS['constant']('BASE')}utils.resolveScreenName?screen_name={$config['path']}");
	if ($tmp['response'] == NULL)
		die("Page resolve error");
	else {
		// id
		$config['id'] = $tmp['response']['object_id'];
		// type
		switch($tmp['response']['type']) {
			case 'user': $config['type'] = TUSER; break;
			case 'group':
			case 'event':
			case 'page': $config['type'] = TGROUP; break;
			default: die("Only groups and users are supported");
		}
	}

	return $config;
}

// load info about a page
function load_info(array $config) {
	if ($config['type'] == TGROUP)
		$ret = json_get_contents("{$GLOBALS['constant']('BASE')}groups.getById?fields=description,photo_big,type&group_id={$config['id']}&v={$GLOBALS['constant']('APIVERSION')}");
	elseif ($config['type'] == TUSER)
		$ret = json_get_contents("{$GLOBALS['constant']('BASE')}users.get?fields=status,photo_max_orig,screen_name&user_ids={$config['id']}&v={$GLOBALS['constant']('APIVERSION')}");
	if (isset($ret['error']))
		die($ret['error']['error_msg']);
	elseif ($ret['response'][0]['is_closed'] == true)
		die("The group is closed");
	else
		return $ret;
}

// load posts from a page
function load_posts(array $config) {
	// make a request
	$req = "{$GLOBALS['constant']('BASE')}wall.get?owner_id=";
	// for groups minus is to be concatenated
	if ($config['type'] == TGROUP)
		$req .= '-';
	$req .= $config['id'];
	// we need extended info
	$req .= '&extended=1';
	// count and filter
	if (isset($config['count']))
		$req .= '&count=' . (string)$config['count'];
	if (isset($config['filter']))
		$req .= "&filter={$config['filter']}";
	// access_token
	$req .= "&access_token={$config['apikey']}";
	// api version
	$req .= "&v={$GLOBALS['constant']('APIVERSION')}";

	// do the request
	$ret = json_get_contents($req);
	if (isset($ret['error']))
		die($ret['error']['error_msg']);
	else
		return $ret;
}

// processes raw data
function process_raw(array $raw_info, array $raw_posts, array $config) {
	// parse vk item's <description>
	function item_parse(array $item) {
		// find [id1|Pawel Durow] form links
		$ret = preg_replace('/\[(\w+)\|(.+)\]/', "<a href='{$GLOBALS['constant']('VKURL')}$1'>$2</a>", $item['text']);
		// change all linebreak to HTML compatible <br />
		$ret = nl2br($ret);

		// attachments
		if (isset($item['attachments'])) {
			// level 1
			foreach ($item['attachments'] as $attachment) {
				// VK videos
				if ($attachment['type'] == 'video')
					$ret .= "\n<p><a href='{$GLOBALS['constant']('VKURL')}video{$attachment['video']['owner_id']}_{$attachment['video']['id']}'><img src='{$attachment['video']['photo_320']}' alt='{$attachment['video']['title']}'></a></p>";
				// VK audio
				elseif ($attachment['type'] == 'audio')
					// $attachment['audio']['url'] будет: https://vk.com/mp3/audio_api_unavailable.mp3
					$ret .= "\n<p><a href='{$attachment['audio']['url']}'>{$attachment['audio']['artist']} - {$attachment['audio']['title']}</a></p>";
				// any doc apart of gif
				elseif ($attachment['type'] == 'doc' and $attachment['doc']['ext'] != 'gif')
					$ret .= "\n<p><a href='{$attachment['doc']['url']}'>{$attachment['doc']['title']}</a></p>";
			}
			// level 2
			foreach ($item['attachments'] as $attachment) {
				// JPEG, PNG photos
				// GIF in vk is a document, so, not handled as photo
				if ($attachment['type'] == 'photo')
					$ret .= "\n<p><img src='{$attachment['photo']['photo_604']}'></p>";
				// GIF docs
				elseif ($attachment['type'] == 'doc' and $attachment['doc']['ext'] == 'gif')
					$ret .= "\n<p><img src='{$attachment['doc']['url']}'></p>";
				// links
				elseif ($attachment['type'] == 'link')
					$ret .= "\n<p><a href='{$attachment['link']['url']}'><img src='{$attachment['link']['photo']['photo_604']}' alt='{$attachment['link']['title']}'></a></p>";
			}
		}

		return $ret;
	}

	$rss = array();
	$infores = $raw_info['response'][0];

	// title
	if ($config['type'] == TGROUP)
		$rss['title'] = $infores['name'];
	elseif ($config['type'] == TUSER)
		$rss['title'] = "{$infores['first_name']} {$infores['last_name']}";

	// link
	$rss['link'] = "{$GLOBALS['constant']('VKURL')}{$infores['screen_name']}";

	// description
	//for group, its description used
	//for user, its status
	if ($config['type'] == TUSER)
		$rss['description'] = $infores['status'];
	elseif ($config['type'] == TGROUP)
		$rss['description'] = $infores['description'];

	// generator
	$rss['generator'] = VERSION;

	// docs
	$rss['docs'] = 'http://www.rssboard.org/rss-specification';

	// image
	if ($config['type'] == TGROUP)
		$rss['image']['url'] = $infores['photo_200'];
	elseif ($config['type'] == TUSER)
		$rss['image']['url'] = $infores['photo_max_orig'];

	// item
	// the hardest
	foreach ($raw_posts['response']['items'] as $i => $item) {
		// description
		// handle a repost (simple post is lower)
		if (isset($item['copy_history'])) {
			$desc = item_parse($item['copy_history'][0]);
			$rss['post'][$i]['type'] = PREPOST;
		}
		// a post
		else {
			$desc = item_parse($item);
			$rss['post'][$i]['type'] = PPOST;
		}
		$rss['post'][$i]['description'] = $desc;

		// title
		// it will be id of post
		if ($rss['post'][$i]['type'] == PPOST)
			$rss['post'][$i]['title'] = "Post {$item['id']}";
		elseif ($rss['post'][$i]['type'] == PREPOST)
			$rss['post'][$i]['title'] = "Repost {$item['id']}";

		// pubDate
		$rss['post'][$i]['pubDate'] = date(DATE_RSS, $item['date']);

		// link
		$rss['post'][$i]['link'] = "{$GLOBALS['constant']('VKURL')}{$infores['screen_name']}?w=wall-{$infores['id']}_{$item['id']}";
	}

	return $rss;
}

// output an rss array
function rss_output(array $rss) {
	$xw = new XMLWriter();
	$xw->openMemory();
	$xw->setIndent(true);
	$xw->startDocument('1.0', 'UTF-8');
		$xw->startElement('rss');
			$xw->startAttribute('version');
				$xw->text(RSSVERSION);
			$xw->endAttribute();
			$xw->startElement('channel');
				$xw->startElement('title');
					$xw->text(nl2br($rss['title']));
				$xw->endElement();
				$xw->startElement('link');
					$xw->text($rss['link']);
				$xw->endElement();
				$xw->startElement('description');
					$xw->text($rss['description']);
				$xw->endElement();
				$xw->startElement('generator');
					$xw->text($rss['generator']);
				$xw->endElement();
				$xw->startElement('docs');
					$xw->text($rss['docs']);
				$xw->endElement();
				$xw->startElement('image');
					$xw->startElement('url');
						$xw->text($rss['image']['url']);
					$xw->endElement();
				$xw->endElement();
				foreach ($rss['post'] as $item) {
					$xw->startElement('item');
						$xw->startElement('title');
							$xw->text($item['title']);
						$xw->endElement();
						$xw->startElement('description');
							$xw->text($item['description']);
						$xw->endElement();
						$xw->startElement('pubDate');
							$xw->text($item['pubDate']);
						$xw->endElement();
						$xw->startElement('link');
							$xw->text($item['link']);
						$xw->endElement();
					$xw->endElement();
				}
			$xw->endElement();
		$xw->endElement();
	$xw->endDocument();
	print $xw->outputMemory();
}

// start read from here

// configuration
$config = config_get();

// raw page info and posts
$raw_info = load_info($config);
$raw_posts = load_posts($config);

// processing raw data
// $rss is a RSS-style array
$rss = process_raw($raw_info, $raw_posts, $config);

// RSS header and use of UTF-8
header('Content-Type: application/xhtml+xml; charset=utf-8');

// outputting processed data
rss_output($rss);
?>
