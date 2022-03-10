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
	$url_mp3 = "https://www.ilpost.it/podcasts/".$podcast."/";
	$url_feed = $url_mp3."feed/";
        $data_login = "log=".$username."&pwd=".$password."&wp-submit=Login";
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
    
    //Request podcast page to get mp3 files location
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_mp3);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_COOKIESESSION, false );
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie );
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$private_data = curl_exec($ch);
	curl_close($ch);
	//Get actual mp3 files location and store it in keyed array
	$doc = new DOMDocument();
	$doc->loadHTML($private_data);
	$nodes = $doc->getElementsByTagName("a");
	foreach ($nodes as $entry) {
		if ($entry->getAttribute("class") == "play") {
			$mp3_array[$entry->getAttribute("data-id")] = $entry->getAttribute("data-file");
		}
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
