<?php
/*
Copyright (c) 2017-2018 SlackCoyote

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

// API key
const APIKEY = 'APIKEY';

const APIVERSION = '5.70';
const VERSION = 'vkfeed2rss v1.0';

// page type
const TGROUP = 0;
const TUSER = 1;
// post type
const PPOST = 0;
const PREPOST = 1;

/*
 * convert vk url (https://vk.com/apiclub) to path (apiclub)
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
			case 2: $tmp = parse_url("https://vk.com/{$url}"); break;
		}

		if (($tmp['scheme'] == 'http' or $tmp['scheme'] == 'https') // check
		and $tmp['path'] != '/' and $tmp['path'])
			return substr($tmp['path'], 1);
	}

	// if the function can't parse the url, die
	die("Bad URL");
}

// call VK API method
function vk_call(string $method, array $opts, string $apikey = CONFIG['apikey'], bool $use_apikey = true) {
	if ($use_apikey)
		$opts['access_token'] = $apikey;
	$opts['v'] = APIVERSION;

	$uncontext = http_build_query($opts);
	$result_raw = file_get_contents("https://api.vk.com/method/{$method}?{$uncontext}");
	if (is_bool($result_raw) and $result_raw == FALSE)
		die("Cannot access the server");

	$result = json_decode($result_raw, true);
	if ($result) {
		if (isset($result['error']))
			die($result['error']['error_msg']);
		else
			return $result;
	}
	else
		die("The json cannot be decoded or the encoded data is deeper than the recursion limit");
}

// load info about a page
function load_info($pageres) {
	switch ($pageres['type']) {
	case TGROUP:
		return vk_call('groups.getById', array(
			'fields' => 'description,photo_big,type',
			'group_id' => $pageres['id']
		));
	case TUSER:
		return vk_call('users.get', array(
			'fields' => 'status,photo_max_orig,screen_name',
			'user_ids' => $pageres['id']
		));
	}
}

// load posts from a page
function load_posts(array $pageres) {
	// TGROUPs need - at start
	switch ($pageres['type']) {
		case TGROUP: $opts['owner_id'] = '-'.(string)$pageres['id']; break;
		case TUSER:  $opts['owner_id'] = (string)$pageres['id']; break;
	}
	// extended posts
	$opts['extended'] = 1;
	// count
	if (isset(CONFIG['count']))
		$opts['count'] = (string)CONFIG['count'];
	// filter
	if (isset(CONFIG['filter']))
		$opts['filter'] = CONFIG['filter'];
	
	return vk_call('wall.get', $opts);
}

function resolve_page() {
	$tmp = vk_call('utils.resolveScreenName', array('screen_name' => CONFIG['path']));
	if (empty($tmp['response'])) {
		die("Error, not found");
	}
	// id
	$ret['id'] = $tmp['response']['object_id'];
	// type
	switch ($tmp['response']['type']) {
		case 'user': $ret['type'] = TUSER; break;
		case 'group':
		case 'event':
		case 'page': $ret['type'] = TGROUP; break;
		default: die("Only groups and users are supported");
	}
	return $ret;
}

// processes raw data
function process_raw(array $raw_info, array $raw_posts, array $pageres) {
	// parse vk item's <description>
	function item_parse(array $item) {
		// it's what we will return
		$ret = $item['text'];
		// html special characters convertion
		$ret = htmlentities($ret, ENT_QUOTES | ENT_HTML401);
		// change all linebreak to HTML compatible <br />
		$ret = nl2br($ret);
		// find URLs
		$ret = preg_replace('/((https?|ftp|gopher)\:\/\/[a-zA-Z0-9\-\.]+(:[a-zA-Z0-9]*)?\/?([\w\-\+\.\?\,\'\/&amp;%\$#\=~\x5C])*)/', "<a href='$1'>$1</a>", $ret);
		// find [id1|Pawel Durow] form links
		$ret = preg_replace('/\[(\w+)\|([^\]]+)\]/', "<a href='https://vk.com/$1'>$2</a>", $ret);

		// attachments
		if (isset($item['attachments'])) {
			// level 1
			foreach ($item['attachments'] as $attachment) {
				// VK videos
				if ($attachment['type'] == 'video') {
					$title = htmlentities($attachment['video']['title'], ENT_QUOTES | ENT_HTML401);
					$photo = htmlentities($attachment['video']['photo_320'], ENT_QUOTES | ENT_HTML401);
					$href = "https://vk.com/video{$attachment['video']['owner_id']}_{$attachment['video']['id']}";
					$ret .= "\n<p><a href='{$href}'><img src='{$photo}' alt='Video: {$title}'></a></p>";
				}
				// VK audio
				elseif ($attachment['type'] == 'audio') {
					$artist = htmlentities($attachment['audio']['artist'], ENT_QUOTES | ENT_HTML401);
					$title =  htmlentities($attachment['audio']['title'], ENT_QUOTES | ENT_HTML401);
					$ret .= "\n<p>Audio: {$artist} - {$title}</p>";
				}
				// any doc apart of gif
				elseif ($attachment['type'] == 'doc' and $attachment['doc']['ext'] != 'gif') {
					$doc_url = htmlentities($attachment['doc']['url'], ENT_QUOTES | ENT_HTML401);
					$title =   htmlentities($attachment['doc']['title'], ENT_QUOTES | ENT_HTML401);
					$ret .= "\n<p><a href='{$doc_url}'>{$title}</a></p>";
				}
			}
			// level 2
			foreach ($item['attachments'] as $attachment) {
				// JPEG, PNG photos
				// GIF in vk is a document, so, not handled as photo
				if ($attachment['type'] == 'photo') {
					$photo = htmlentities($attachment['photo']['photo_604'], ENT_QUOTES | ENT_HTML401);
					$text =  htmlentities($attachment['photo']['text'], ENT_QUOTES | ENT_HTML401);
					$ret .= "\n<p><img src='{$photo}' alt='{$text}'></p>";
				}
				// GIF docs
				elseif ($attachment['type'] == 'doc' and $attachment['doc']['ext'] == 'gif') {
					$url = htmlentities($attachment['doc']['url'], ENT_QUOTES | ENT_HTML401);
					$ret .= "\n<p><img src='{$url}'></p>";
				}
				// links
				elseif ($attachment['type'] == 'link') {
					$url =   htmlentities($attachment['link']['url'], ENT_QUOTES | ENT_HTML401);
					$title = htmlentities($attachment['link']['title'], ENT_QUOTES | ENT_HTML401);
					if (isset($attachment['link']['photo']['photo_604'])) {
						$photo = htmlentities($attachment['link']['photo']['photo_604'], ENT_QUOTES | ENT_HTML401);
						$ret .= "\n<p><a href='{$url}'><img src='{$photo}' alt='{$title}'></a></p>";
					}
					else
						$ret .= "\n<p><a href='{$url}'>{$title}</a></p>";
				}
				// notes
				elseif ($attachment['type'] == 'note') {
					$title = htmlentities($attachment['note']['title'], ENT_QUOTES | ENT_HTML401);
					$url =   htmlentities($attachment['note']['view_url'], ENT_QUOTES | ENT_HTML401);
					$ret .= "\n<p><a href='{$url}'>{$title}</a></p>";
				}
				// polls
				elseif ($attachment['type'] == 'poll') {
					$question = htmlentities($attachment['poll']['question'], ENT_QUOTES | ENT_HTML401);
					$vote_count = $attachment['poll']['votes'];
					$answers = $attachment['poll']['answers'];
					$ret .= "\n<p>Poll: {$question} ({$vote_count} votes)<br />";
					foreach ($answers as $answer) {
						$text =  htmlentities($answer['text'], ENT_QUOTES | ENT_HTML401);
						$votes = $answer['votes'];
						$rate =  $answer['rate'];
						$ret .= "* {$text}: {$votes} ({$rate}%)<br />";
					}
					$ret .= "</p>";
				}
			}
		}

		return $ret;
	}

	$infores = $raw_info['response'][0];

	// title
	switch ($pageres['type']) {
		case TGROUP: $rss['title'] = $infores['name']; break;
		case TUSER:  $rss['title'] = "{$infores['first_name']} {$infores['last_name']}"; break;
	}

	// link
	$rss['link'] = "https://vk.com/{$infores['screen_name']}";

	// description
	// for group, its description used
	// for user, its status
	switch ($pageres['type']) {
		case TGROUP: $rss['description'] = $infores['description']; break;
		case TUSER:  $rss['description'] = $infores['status']; break;
	}

	// generator
	$rss['generator'] = VERSION;

	// docs
	$rss['docs'] = 'http://www.rssboard.org/rss-specification';

	// image
	switch ($pageres['type']) {
		case TGROUP: $rss['image']['url'] = $infores['photo_200']; break;
		case TUSER:  $rss['image']['url'] = $infores['photo_max_orig']; break;
	}

	// item
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
		switch ($rss['post'][$i]['type']) {
			case PPOST:   $title = "Post: "; break;
			case PREPOST: $title = "Repost: "; break;
		}
		/*
		 * if a post was posted by a group, its name will be added in title,
		 * if by a[n] user, its name + familyname
		 */
		$poster = NULL;
		// all the groups are negative integer
		if ($item['from_id'] < 0) { // group
			foreach ($raw_posts['response']['groups'] as $g)
				if (($g['id'] * -1) == $item['from_id'])
					$poster = "{$g['name']}"; // equals to: (string)$g['name']
		} else { // user
			foreach ($raw_posts['response']['profiles'] as $u)
				if ($u['id'] == $item['from_id'])
					$poster = "{$u['first_name']} {$u['last_name']}";
		}
		if ($poster != NULL)
			$title .= $poster;
		else
			die("Unexpected error when generated title for an item");
		$rss['post'][$i]['title'] = $title;

		// pubDate
		$rss['post'][$i]['pubDate'] = date(DATE_RSS, $item['date']);

		// link
		$rss['post'][$i]['link'] = "https://vk.com/{$infores['screen_name']}?w=wall-{$infores['id']}_{$item['id']}";
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
				$xw->text('2.0');
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
							$xw->writeCData($item['description']);
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

function main() {
	// some functions needs vk api key
	if (isset($_GET['apikey']))
		$config['apikey'] = $_GET['apikey'];
	elseif (constant('APIKEY'))
		$config['apikey'] = APIKEY;
	else
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
			$config['count'] = $_GET['count']; // no need to convert to integer
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

	// configuration available fromeverywhere the program
	define('CONFIG', $config);
	unset($config);

	// resolving page's id and type
	$pageres = resolve_page();
	// raw page info and posts
	$raw_info = load_info($pageres);
	$raw_posts = load_posts($pageres);

	// processing raw data
	// $rss is a RSS-style array
	$rss = process_raw($raw_info, $raw_posts, $pageres);

	// RSS header and use of UTF-8
	header('Content-Type: application/xhtml+xml; charset=utf-8');

	// outputting processed data
	rss_output($rss);
}; main();
?>
