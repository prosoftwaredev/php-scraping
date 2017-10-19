<?php


function getHtml($url, $post_args = array(), $use_proxy = false) {

	if (stripos($url, 'https://www.trulia.com') !== 0) {
		$url = 'https://www.trulia.com' . $url;
	}

	$result = '';
	$tries = 0;
	
	// Retry requests up to 10x on failure

	while (!$result && $tries < 10) {


		$curl_connection = curl_init($url);


		if($use_proxy)
		{
			curl_setopt($curl_connection, CURLOPT_PROXY, 'http://zproxy.luminati.io:22225');
			curl_setopt($curl_connection, CURLOPT_PROXYUSERPWD, 'lum-customer-cityblast-zone-residential:586785c65cde');
		}
		

		curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl_connection, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
		curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);	


		
		if ($post_args) {
			$post_string = implode('&', $post_args);
			curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);
		}
	
		$result = curl_exec($curl_connection);
	
		curl_close($curl_connection);
		
		unset($curl_connection);
		gc_collect_cycles(); // Force garbage collection in an effort to conserve memory
		
		$tries++;

		if (!$result) sleep(1);
		
		sleep(DELAY_SECONDS);
	
	}
	
	return $result;

}

function save_agent($agent_name, $agent_phone, $agent_with, $city, $state, $country, $timezone)
{

	$name_parts = explode(" ", $agent_name);

	if (!$name_parts) return false;
	
	$first_name = $name_parts[0];
	$last_name = end($name_parts);
	
	$tmp_phone = preg_replace("/[^0-9,.]/", "", $agent_phone);

	$known_agent = KnownAgent::find_by_first_name_and_last_name_and_city_and_state($first_name, $last_name, $city, $state);

	if (!$known_agent) {

		$known_agent = new KnownAgent;
		$known_agent->first_name = $first_name;
		$known_agent->last_name = $last_name;

		Logger::log($agent_name . ' does not exist in database yet.');
	
		$known_agent->phone = $agent_phone;
		$known_agent->brokerage = $agent_with;
		$known_agent->city = $city;
		$known_agent->state = $state;
		$known_agent->country = $country;

		if ($agent_phone) {
			Logger::log('Saved agent.');
			$known_agent->save();
		}
		
	}

	$known_agent->checkPhoneType(); // Fetch phone type from Trulia if not already present

	return $known_agent->id ? $known_agent : null;

}

function parse_directory_page($html, $city, $state, $country, $timezone)
{
	global $counter;

	// <a href="https://www.trulia.com/profile/joyce-chasteen-agent-collierville-tn-zgc1fzjq/" onclick="o_track_ql_click('FindAnAgent|SRP|AgentName_link');">Joyce Chasteen</a>
	$regex1 = '/<h5(.*?)<\/a>/s';

	// <p class="mvn h7 pre_badges">(901) 235-8815</p> 
	$regex2 = '/mvn h7 pre_badges(.*?)p/';
	// <p class="mvn h7">(901) 531-9436</p>
	$regex4 = '/mvn h7\"(.*?)p/';

	// <p class="mvn h7">Agent with Crye-Leike, Realtors</p>
	$regex3 = '/>Agent with (.*?)<\/p>/s';

	//<span class="h3 headingDoubleInline typeDeemphasize mls txtM"> - 10 Results</span>
	$regex5 = '/headingDoubleInline typeDeemphasize mls txtM\"> - (.*?) Results<\/span>/s';


	$counter = 0;
	$csv_string = array();

	$agents = explode("<li class=\"hover prn\">", $text);

	foreach ($agents as $agent) {

		$known_agent = new KnownAgent;



		if ($counter < 1) {
			$counter++;
			continue;
		}

		if (preg_match($regex1, $agent, $list1)) {
			$agent_name = strip_tags($list1[0]);
		} else
			$agent_name = "";

		if (preg_match($regex2, $agent, $list2)) {

			$agent_phone_array = explode('>', $list2[0]);
			$agent_phone = strip_tags($agent_phone_array[1]);
		} else {
			if (preg_match($regex4, $agent, $list2)) {

				$agent_phone_array = explode('>', $list2[0]);
				$agent_phone = strip_tags($agent_phone_array[1]);
			} else
				$agent_phone = "";
		}




		if (preg_match($regex3, $agent, $list3)) {
			$agent_with_array = explode('Agent with ', $list3[0]);
			$agent_with = strip_tags($agent_with_array[1]);
		} else {
			$agent_with = "";
		}


		echo "Agent Name: " . $agent_name . "\n";
		echo "Phone Number: " . $agent_phone . "\n";
		echo "Agent With: " . $agent_with . "\n\n";


		//array_push($csv_string, $agent_name.",".$agent_phone.",".$agent_with);	
		save_agent($agent_name, $agent_phone, $agent_with, $city, $state, $country, $timezone);


		$counter++;
	}
}

