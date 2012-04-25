<?php
//Require twitter oauth
require_once('twitteroauth/twitteroauth.php');
require_once('config.php');

//Collect arguments
$screen_name = false;
$compile = true;

//Collect the username
if (!isset($argv[1])) {
	die("The first argument must be the screen name to get the list for\n");
} 
$screen_name = $argv[1];

//Array of ids
$ids = array();
$cursor = '-1';

//If we are compiling we need an array of hashes
$hashes = array();

//Create a TwitterOauth object with consumer/user tokens
$twitter = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

//Request params
$params = array(
	'screen_name' => $screen_name
);

//Request count
$request_count = 1;
$file_count = 1;

//Follower counts
$user = $twitter->get('users/show', $params);
$total_followers = $user->followers_count;
$total_requests = floor($total_followers/5000) + 1;

//Check if the user has enough requests
$rate_limit_status = $twitter->get('account/rate_limit_status');
$hourly_limit = $rate_limit_status->hourly_limit;
$limit_remaining = $rate_limit_status->remaining_hits;
$reset_time = $rate_limit_status->reset_time;
if ($hourly_limit < $total_requests) {
	die("Your hourly API request limit (" . $hourly_limit . "/hour) is not enough to grab all the followers (" . $total_requests . " requests)\n");
} else if ($limit_remaining < $total_requests) {
	die("Your remaining requests for this hour (" . $hourly_limit . " is not enough to grab all the followers (" . $total_requests . " requests)\nTry again after: " . $reset_time . "\n");
} else if ($limit_remaining < $total_requests) {
	die("Your remaining requests for this hour (" . $hourly_limit . " is not enough to grab all the followers (" . $total_requests . " requests)\n");
}

//Starting to fetch
echo "Starting to fetch...\n";

//Loop until the cursor is 0
while($cursor != '0') {
	if ($cursor != '-1') {
		$params['cursor'] = $cursor;
	}
	
	//If method is set change API call made. Test is called by default
	$json = (array)$twitter->get('followers/ids', $params);
	
	echo "Requesting (" . $request_count . "/" . $total_requests . ")";
	
	if (isset($json['ids'])) {
		foreach ($json['ids'] as $id){
			$ids[$id] = $id;
		}
		echo " (" . count($ids) . ")\n";
	} else {
		echo 'Hmmm... no ids: ' . print_r($json, true);
	}
	
	//Roughly 15 requests of 5000 results will create a file less than 1MB (max limit in api
	if (count($ids) > 0 && $compile && $request_count % 15 == 0) {
		$hashes[] = compileIds($ids);
		$file_count++;
	}


	//Check for a curso
	if (isset($json['next_cursor_str'])) {
		$cursor = $json['next_cursor_str'];
		$request_count++;
	}
}

//Compile the remainder
if (count($ids) > 0) {
	$hashes[] = compileIds($ids);
}
$final_hash = $hashes[0];

//Check if we need to combine hashes
if (count($hashes) > 1) {
	$streams = array();
	foreach($hashes as $hash) {
		$streams[] = 'stream "' . $hash . '"';
	}
	$final_hash = compileCsdl(implode(' OR ', $streams));
}

echo 'This is your DataSift hash: ' . $final_hash . "\n";


//Compile the ids to CSDL
function compileIds(&$ids) {
	
	$csdl = 'twitter.user.id IN [' . implode(',', array_keys($ids)) .']';
	
	//Empty ids
	$ids = array();
	
	//Get hash
	return compileCsdl($csdl);

}

function compileCsdl($csdl) {

	//Compile
	$post_body = 'csdl=' . urlencode($csdl);
	
	//Send to the DataSift API and return the hash
	$url = 'http://api.datasift.com/compile?username=' . DATASIFT_USERNAME . '&api_key=' . DATASIFT_APIKEY;
	
	//open connection
	$ch = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch,CURLOPT_URL,            $url);
	curl_setopt($ch,CURLOPT_POST,           1);
	curl_setopt($ch,CURLOPT_POSTFIELDS,     $post_body);

	//execute post
	$result = curl_exec($ch);

	//close connection
	curl_close($ch);
	
	//Convert json response
	$json = json_decode($result, true);
	
	//Check for errors
	if (isset($json['error'])) {
		echo "\nCSDL failed: " . $csdl . "\n\n";
		
		die("DataSift Error: " . $json['error'] . "\n");
	}
	
	//Return the new CSDL hash
	return $json['hash'];
	
}
