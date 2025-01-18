<?php
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="www.ilpost.it"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required';
    exit;
}

header('Content-Type: application/xml;charset=UTF-8');

$podcast = "morning";
if (isset($_GET["podcast"])) {
    $podcast = $_GET["podcast"];
}

$username = $_SERVER["PHP_AUTH_USER"];
$password = $_SERVER["PHP_AUTH_PW"];

$log = "IP: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
       "User: " . $username . " Podcast:  " . $podcast . PHP_EOL .
       "-------------------------" . PHP_EOL;
file_put_contents('log/req.log', $log, FILE_APPEND);

// Normalize titles
function normalizeTitle($title) {
    // Convert HTML in chars
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = trim($title);
    return $title;
}

$url_login = "https://www.ilpost.it/wp-login.php";
$url_podcast = "https://www.ilpost.it/podcasts/" . $podcast . "/";
$url_feed = $url_podcast . "feed/";
$cookie = "cookies/" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $username) . ".txt";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_login);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_COOKIESESSION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_POST, true);
$data_login = "log=" . $username . "&pwd=" . $password . "&wp-submit=Login&redirect_to=https%3A%2F%2Fwww.ilpost.it%2Fwp-admin%2F&testcookie=1";
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_login);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_exec($ch);
curl_close($ch);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_podcast);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_COOKIESESSION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$podcast_page = curl_exec($ch);
file_put_contents('log/podcast_page.html', $podcast_page);
curl_close($ch);

$doc = new DOMDocument();
$doc->loadHTML($podcast_page);

// Find the image in the div with class "_podcast-header__image_1asv1_29"
$xpath = new DOMXPath($doc);
$div = $xpath->query('//div[contains(@class, "_podcast-header__image_1asv1_29")]')->item(0);
$imageUrl = null;
if ($div) {
    $img = $div->getElementsByTagName('img')->item(0);
    if ($img) {
        $imageUrl = $img->getAttribute('src');
        file_put_contents('log/req.log', "Image URL: ".$imageUrl.PHP_EOL, FILE_APPEND);
    }
}

// Extract NEXT_DATA JSON and parse episodes
$mp3_array = [];
file_put_contents('log/req.log', "\nParsing NEXT_DATA section:\n", FILE_APPEND);

if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $podcast_page, $matches)) {
    $json_data = json_decode($matches[1], true);

    if (isset($json_data['props']['pageProps']['data']['data']['episodes']['data'])) {
        $episodes = $json_data['props']['pageProps']['data']['data']['episodes']['data'];

        file_put_contents('log/req.log', "Found " . count($episodes) . " episodes in JSON data\n", FILE_APPEND);

        foreach ($episodes as $episode) {
            if (isset($episode['title']) && isset($episode['episode_raw_url'])) {
                $normalized_title = normalizeTitle($episode['title']);
                $mp3_array[$normalized_title] = $episode['episode_raw_url'];
                file_put_contents('log/req.log', "Added to mp3_array: Original='{$episode['title']}' Normalized='$normalized_title' -> {$episode['episode_raw_url']}\n", FILE_APPEND);
            }
        }
    }
}

// Log the final mp3_array
file_put_contents('log/req.log', "\nFinal mp3_array contains " . count($mp3_array) . " items:\n", FILE_APPEND);
foreach ($mp3_array as $title => $url) {
    file_put_contents('log/req.log', "mp3_array['" . $title . "'] = " . $url . "\n", FILE_APPEND);
}

// Request the RSS feed and modify it
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_feed);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_COOKIESESSION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$feed_data = curl_exec($ch);
$feed_data = trim($feed_data);
file_put_contents('log/feed_' . $podcast . '.xml', $feed_data);
curl_close($ch);

$feed_dom = new DOMDocument();
$feed_dom->preserveWhiteSpace = false;
$feed_dom->loadXML($feed_data);
$feed_dom->getElementsByTagName("rss")->item(0)->setAttribute("xmlns:itunes", "http://www.itunes.com/dtds/podcast-1.0.dtd");
$feed_dom->formatOutput = true;
$channel = $feed_dom->getElementsByTagName("rss")->item(0)->getElementsByTagName("channel")->item(0);

// Update the image URL
if ($imageUrl) {
    $channel->getElementsByTagName("image")->item(0)->getElementsByTagName("url")->item(0)->nodeValue = $imageUrl;
}

// Add "itunes:block" to make the feed private
$channel->insertBefore($feed_dom->createElement("itunes:block", "yes"), $channel->firstChild);

// Log RSS feed items
file_put_contents('log/req.log', "\nProcessing RSS feed items:\n", FILE_APPEND);

// Add MP3 enclosure tag to podcast RSS feed
foreach ($channel->getElementsByTagName("item") as $item) {
    $title = $item->getElementsByTagName("title")->item(0)->nodeValue;
    $normalized_title = normalizeTitle($title);

    file_put_contents('log/req.log', "Processing RSS item: Original='$title' Normalized='$normalized_title'\n", FILE_APPEND);

    if (isset($mp3_array[$normalized_title])) {
        $enclosure = $feed_dom->createElement("enclosure");
        $enclosure->setAttribute("url", $mp3_array[$normalized_title]);
        $enclosure->setAttribute("type", "audio/mpeg");
        $item->appendChild($enclosure);
        file_put_contents('log/req.log', "SUCCESS: Added enclosure for '$normalized_title' with URL '{$mp3_array[$normalized_title]}'\n", FILE_APPEND);
    } else {
        file_put_contents('log/req.log', "WARNING: No matching MP3 URL found for title '$normalized_title'\n", FILE_APPEND);
    }
}

// Remove temporary cookie
unlink($cookie);

// Return enriched RSS feed
echo $feed_dom->saveXML();
?>
