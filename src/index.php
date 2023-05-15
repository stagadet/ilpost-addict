<?php
	if (!isset($_SERVER['PHP_AUTH_USER'])) {
	//Reject unauthenticated requests (needed for podcastAddict to send username and password
        header('WWW-Authenticate: Basic realm="www.ilpost.it"');
	    header('HTTP/1.0 401 Unauthorized');
	    echo 'Authentication required';
	    exit;
	}
	//Set content type as xml so that if viewed in browser it is correctly formatted
	header('Content-Type: application/xml;charset=UTF-8');
	//Get the requested podcast name from request (defaults to "morning")
	$podcast = "morning";
	if (isset($_GET["podcast"])) {$podcast = $_GET["podcast"];}
	//Get username and password
	$username = $_SERVER["PHP_AUTH_USER"];
	$password = $_SERVER["PHP_AUTH_PW"];
	//Log the request
	$log  = "IP: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a").PHP_EOL.
		"User: ".$username." Podcast:  ".$podcast.PHP_EOL.
            	"-------------------------".PHP_EOL;
	file_put_contents('log/req.log', $log, FILE_APPEND);
	//Set variables
	$url_login = "https://www.ilpost.it/wp-login.php";
	$url_podcast = "https://www.ilpost.it/podcasts/".$podcast."/";
	$url_mp3 = "https://www.ilpost.it/wp-admin/admin-ajax.php";
	$url_feed = $url_podcast."feed/";
        $data_login = "log=".$username."&pwd=".$password."&wp-submit=Login&redirect_to=https%3A%2F%2Fwww.ilpost.it%2Fwp-admin%2F&testcookie=1";
	$cookie = "cookies/".preg_replace('/[^A-Za-z0-9_\-]/', '_', $username).".txt";
	//Log in to ilpost.it website, storing information on cookie file
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url_login);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIESESSION, true );
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie );
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie );
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_login);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	if (! $login_data = curl_exec($ch)) {
	};
	curl_close($ch);

	//Request podcast page to get podcast id
	$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_podcast);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_COOKIESESSION, false );
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie );
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$podcast_page = curl_exec($ch);
	//file_put_contents('log/podcast_page.html', $podcast_page.html);
 	curl_close($ch);

	$doc = new DOMDocument();
	$doc->loadHTML($podcast_page);
	$podcast_node = $doc->getElementById("ilpost-podcast-custom-js-extra");
	//file_put_contents('log/script_node.html', rtrim(ltrim($podcast_node->textContent, "var ilpostpodcast="), ";"));
	$podcast_json = json_decode(rtrim(ltrim($podcast_node->textContent, "var ilpostpodcast="), ";"));

	//Request podcast data to get mp3 files location
	$data_mp3 = "action=checkpodcast";
	$data_mp3 .= "&cookie=wordpress_logged_in_5750c61ce9761193028d298b19b5c708";
	$data_mp3 .= "&post_id=0";
	$data_mp3 .= "&podcast_id=".$podcast_json->podcast_id;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url_mp3);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIESESSION, false );
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie );
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_mp3);
	$private_data = curl_exec($ch);
	file_put_contents('log/private.html', $private_data);
	curl_close($ch);
	$result = json_decode($private_data);
	file_put_contents('log/req.log', "data->msg: ".$result->data->msg."\r\n", FILE_APPEND);
	file_put_contents('log/req.log', "data->podcastList[0].id: ".$result->data->postcastList[0]->id."\r\n", FILE_APPEND);
	foreach ($result->data->postcastList as $episode) {
		$mp3_array[$episode->id] = $episode->podcast_raw_url;
		//file_put_contents('log/req.log', $episode->id.": ".$episode->podcast_raw_url."\r\n", FILE_APPEND);
	}

	//Get the rss feed of the podcast (without mp3 enclosure)
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url_feed);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIESESSION, false );
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie );
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$feed_data = curl_exec($ch);
	file_put_contents('log/feed.html', $feed_data);
	curl_close($ch);
	//Create feed to be enriched with mp3 file enclosure
	$feed_dom = new DOMDocument();
	$feed_dom->preserveWhiteSpace = false;
	$feed_dom->loadXML($feed_data);
	$feed_dom->getElementsByTagName("rss")->item(0)->setAttribute("xmlns:itunes", "http://www.itunes.com/dtds/podcast-1.0.dtd");
	$feed_dom->formatOutput = true;
	$channel = $feed_dom->getElementsByTagName("rss")->item(0)->getElementsByTagName("channel")->item(0);
	//Add "itunes:block" to make the feed private
	$channel->insertBefore($feed_dom->createElement("itunes:block", "yes"), $channel->firstChild);
	//Add mp3 enclosure tag to podcast rss feed
	foreach($channel->getElementsByTagName("item") as $item) {
		$enclosure = $feed_dom->createElement("enclosure");
		$guid = $item->getElementsByTagName("guid")->item(0)->nodeValue;
		$id = substr($guid, stripos($guid, "p=") + 2);
		$enclosure->setAttribute("url", $mp3_array[$id]);
		$enclosure->setAttribute("type", "audio/mpeg");
		if ($mp3_array[$id] != null) {
			$item->appendChild($enclosure);
		}
	}
	//Remove temporary cookie
	unlink($cookie);
	//Return enriched rss feed
	echo $feed_dom->saveXML();

?>
