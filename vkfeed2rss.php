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

/*
 * Before reading the code I warn you that
 * there is a lot of monkey code
 * this is because the autor is noob
 * thank you
 */

define('BASE', 'https://api.vk.com/method/');
define('VKURL', 'https://vk.com/');
define('APIVERSION', '5.69');
// substr is because of one unnecessarily byte at the end
define('APIKEY', substr(file_get_contents("conf/vkapikey"), 0, -1));
define('VERSION', 'vkfeed2rss 0.4a2');

// page types
define('TGROUP', 1);
define('TUSER', 2);

// convert vk url (https://vk.com/apiclub) to vk domain (apiclub)
function vk_path_from_url(string $url) {
	// chinese code!!!
	// ACHTUNG: goto used!
	$tmp = parse_url($url);
	$gotoused = 0;
	re:
	if (($tmp['scheme'] == 'http' or $tmp['scheme'] == 'https')
	and $tmp['path'] != '/' and $tmp['path']) {
		return substr($tmp['path'], 1);
	} else {
		$tmp = parse_url('https://' . $url); // simulate try
		if ($gotoused >= 2) die("Bad URL");
		elseif ($gotoused == 1)
			$tmp = parse_url(VKURL . $url);
		$gotoused++;
		goto re;
	}
}

// short api decoding
function json_get_contents(string $str) {
	return json_decode(file_get_contents($str), true, 16, JSON_BIGINT_AS_STRING);
}

// resolve vk domain
function vk_resolve(string $domain) {
	// resolves type and id of domain
	$tmp = json_get_contents(BASE . 'utils.resolveScreenName?screen_name=' . $domain);
		
	if ($tmp['response'] == NULL)
		die("Domain resolve error");
	else switch($tmp['response']['type']) {
		case 'user': $type = TUSER; break;
		case 'group':
		case 'event':
		case 'page': $type = TGROUP; break;
		default: die("Not supported");
	}

	$id = $tmp['response']['object_id'];
	
	return array(
		'id' => $id,
		'type' => $type
	);
}

function vk_item_parse(array $item) {
	$ret = '<p>' . $item['text'] . '</p>';

	//attachments: images
	if (isset($item['attachments'])) {
		foreach ($item['attachments'] as $attachment) {
			if ($attachment['type'] == 'photo') {
				$ret .= '<p><img src="' . $attachment['photo']['photo_604'] . '"></p>';
			}
		}
	}

	return $ret;
}

// parse and print the RSS feed
function vk_parse(array $vk) {
	if ($vk == NULL) die("Unrecognized error");
	
	$infores = $vk['info']['response'][0];
	
	$xw = xmlwriter_open_memory();

	xmlwriter_set_indent($xw, true);
	xmlwriter_start_document($xw, '1.0', 'UTF-8');
	
	xmlwriter_start_element($xw, 'rss');

	xmlwriter_start_attribute($xw, 'version');
		xmlwriter_text($xw, '2.0');
	xmlwriter_end_attribute($xw);
	
	// main xml
	xmlwriter_start_element($xw, 'channel');
	
		// title
		xmlwriter_start_element($xw, 'title');
			if ($vk['type'] == TUSER)
				xmlwriter_text($xw, $infores['first_name'] . ' ' . $infores['last_name']);
			elseif ($vk['type'] == TGROUP)
				xmlwriter_text($xw, $infores['name']);
		xmlwriter_end_element($xw);
		
		// link
		xmlwriter_start_element($xw, 'link');
			xmlwriter_text($xw, VKURL . $infores['screen_name']);
		xmlwriter_end_element($xw);
		
		// description
		//for user, its status
		//for group, its description
		xmlwriter_start_element($xw, 'description');
			if ($vk['type'] == TUSER)
				xmlwriter_text($xw, $infores['status']);
			elseif ($vk['type'] == TGROUP)
				xmlwriter_text($xw, $infores['description']);
		xmlwriter_end_element($xw);
		
		// generator
		xmlwriter_start_element($xw, 'generator');
			xmlwriter_text($xw, VERSION);
		xmlwriter_end_element($xw);
		
		// docs
		xmlwriter_start_element($xw, 'docs');
			xmlwriter_text($xw, 'http://www.rssboard.org/rss-specification');
		xmlwriter_end_element($xw);
		
		// image
		xmlwriter_start_element($xw, 'image');
			xmlwriter_start_element($xw, 'url');
				if ($vk['type'] == TUSER)
					xmlwriter_text($xw, $infores['photo_max_orig']);
				elseif ($vk['type'] == TGROUP)
					xmlwriter_text($xw, $infores['photo_200']);
			xmlwriter_end_element($xw);
		xmlwriter_end_element($xw);
	
		// item
		// the hardest thing!
		foreach ($vk['posts']['response']['items'] as $item) {
			xmlwriter_start_element($xw, 'item');
			
			//title
			xmlwriter_start_element($xw, 'title');
				xmlwriter_text($xw, (string)$item['id']);
			xmlwriter_end_element($xw);
			
			//description
			xmlwriter_start_element($xw, 'description');
				// handle a simple post
				if ($item['text'] != '') 
					$desc = vk_item_parse($item);
				// handle a repost
				elseif ($item['text'] == '' and isset($item['copy_history']))
					$desc = vk_item_parse($item['copy_history'][0]);
				// something gone wrong
				else die("Unrecognized error on post parsing");
				xmlwriter_text($xw, $desc);
			xmlwriter_end_element($xw);
			
			//pubDate
			xmlwriter_start_element($xw, 'pubDate');
				xmlwriter_text($xw, date(DATE_RSS, $item['date']));
			xmlwriter_end_element($xw);
			
			//link
			xmlwriter_start_element($xw, 'link');
				xmlwriter_text($xw, VKURL . $infores['screen_name'] . '?w=wall-' . $infores['id'] . '_' . $item['id']);
			xmlwriter_end_element($xw);
			
			xmlwriter_end_element($xw);
		}
	
	xmlwriter_end_element($xw);
	
	xmlwriter_end_element($xw);
	
	xmlwriter_end_document($xw);
	
	echo xmlwriter_output_memory($xw);
	
	unset($xw);
}

function main() {
	// some functions needs vk api key
	if (!APIKEY) die("No api key");

	/*
	 * $vk is an array with page's ID and type (group, user)
	 * 	type can be TGROUP or TUSER
	 * $vk stores all that will be needed for vk_parse()
	 */
	if (isset($_GET['url'])) // detect domain from URL and work as bottom
		$vk = vk_resolve(vk_path_from_url($_GET['url']));
	elseif (isset($_GET['domain'])) // detect by domain
		$vk = vk_resolve($_GET['domain']);
	else
		die("url or domain has to be specified");

	// $vk can be extended with some other variables:

	// count of posts in future feed, maximum 100, minimum 1, on 0 default value of vk will be used
	if (isset($_GET['count'])) {
		if ((int)$_GET['count'] < 0 or (int)$_GET['count'] > 100)
			$vk['count'] = 100;
		else
			$vk['count'] = (int)$_GET['count'];
	}

	// filter for wall.get function
	if (isset($_GET['filter']))
		switch($_GET['filter']) {
			case 'all':
			case 'owner':
			case 'others': $vk['filter'] = $_GET['filter']; break;
			default: die("Only all, owner and others filters are supported");
		}

	// The feed generation is separated into 3 stages: info (information about feed), posts (posts in the feed), conversion (conversioning to RSS and printing)

	// User and group pages have different functions for info
	if ($vk['type'] == TUSER) {
		$vk['info'] = json_get_contents(BASE . 'users.get?fields=status,photo_max_orig,screen_name&user_ids=' . $vk['id'] . '&v=' . APIVERSION);
	}
	elseif ($vk['type'] == TGROUP)
		$vk['info'] = json_get_contents(BASE . 'groups.getById?fields=description,photo_big,type&group_id=' . $vk['id'] . '&v=' . APIVERSION);
	// Error handling
	if (isset($vk['info']['error']))
		die($vk['info']['error']['error_msg']);

	if ($vk['type'] == TUSER or $vk['type'] == TGROUP) {
		$req = BASE . 'wall.get';
		
		$req .= '?owner_id=';
		if ($vk['type'] == TGROUP)
			 $req .= '-';
		$req .= $vk['id'];
		
		if (isset($vk['count']))
			$req .= '&count=' . $vk['count'];

		if (isset($vk['filter']))
			$req .= '&filter=' . $vk['filter'];

		$req .= '&access_token=' . APIKEY;
		
		$req .= '&v=' . APIVERSION;
		
		$vk['posts'] = json_get_contents($req);

		// Error handling
		if (isset($vk['posts']['error']))
			die($vk['posts']['error']['error_msg']);
	}
	
	// Last step
	vk_parse($vk);
	
}

main();
?>
