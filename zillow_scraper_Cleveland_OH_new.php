<?php
	require 'vendor/autoload.php';
	use GuzzleHttp\Client;
	use Symfony\Component\DomCrawler\Crawler;

	$city_name = 'Cleveland-OH';

	$useragent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36';

	$cookie = 'abtest=3|DIm9i0szZUgW3IssQA; zguid=23|%24f3614f1d-7b88-4807-a63f-eddd50f22a45; G_ENABLED_IDPS=google; __gads=ID=b745f97c732537cc:T=1504801355:S=ALNI_MbiEiwOb4SG6SWhs-NO417BKXgvOA; optimizelyEndUserId=oeu1505058513052r0.6146984207161068; optimizelySegments=%7B%7D; optimizelyBuckets=%7B%7D; zrm-featured-code=%3BNSMLP:B%3BSAF:A%3BSPLF:A%3B; zrm-flag-featured-code=%3BFDVRA:0%3BIA:1%3B; __ssid=cd6a693f-28c4-41cb-a468-5047b564561a; _mkto_trk=id:324-WZA-498&token:_mch-zillow.com-1505633831051-55477; JSESSIONID=3A07AFF8F8E80716789A5C62F44C299F; _gat=1; _uetsid=_uet461aa991; _px=3VzZmeUiMFuVxOUyVquhQrXxQX/tMJ7yccivgLqfVN0W/nK1SzNnF2NUkia5zYXOfeuOuL6EL9TrQCvyhpYc+Q==:1000:CIp7ZTghIKBN9p06iU3JsWVpgQ22voooKXKzdlepNwlwLsNBA1myjMH/yYldtVzSDANsjPBJidmqTkCRL/W9xXxsUlYKfU0OR78bFshy4D26qAFsPP4DDm5NzK2dkV2U0AyRq0LjgkCPIF4cezGBd2CuMFEt5uuOGxL5OxLuJjRez3FEAB++vh+2lw0rqHeVI+730QfbITpPTWGA8t9BjH5GUNmkvp89GVCxZcUwcXy3c0gUbtOOuLtMqFAbuL5vggSdByRNqywlbc1RYglqYQ==; AWSALB=BKeLKMeiJ53vWfAQp0Cj0gqjQFzNOHQV0CPHhwjXQaHUJhVKcRGn+k5UqeiWxnGUMmMzwMSspkIZpn0EJEHicD0G0AH94fa1mUCYeNb43D169f3FRCDBxqOOyvaA; search=6|1508341170657%7Cregion%3DCleveland-OH%26rb%3Dcleveland-oh%26rect%3D41.604436%252C-81.532743%252C41.390628%252C-81.878975%26disp%3Dmap%26mdm%3Dauto%26fs%3D1%26fr%3D0%26mmm%3D1%26rs%3D0%26ah%3D0%26singlestory%3D0%09%01%0924115%09%09%092%090%09US_%09; _ga=GA1.2.1202115936.1504801324; _gid=GA1.2.1377730916.1505749134; _gaexp=GAX1.2.ZJoyFiHOQDeh9IFHEd35Ug.17495.4!ogc2S0lSSFSkch6wq1s5Pw.17468.1';

	$client = new Client(
		[
			'headers' => [
				'User-Agent' => $useragent,
				'Cookie' => $cookie
			]
		]
	);

	$response = $client->request('GET', 'https://www.zillow.com/homes/' . $city_name);

	$contents = (string) $response->getBody();

	preg_match("/resurrection\-page\-state\" class\=\"template hide\"\>\<\!\-\-(.*?)\-\-\>/", $contents, $m );

	$city_map_string_data = $m[1];

	if(isset($city_map_string_data)) {

		$city_map_json_data = json_decode(stripslashes($city_map_string_data));

		$sw_lat = $city_map_json_data->regionSelectionObject->boundingRect->sw->lat;
		$sw_lon = $city_map_json_data->regionSelectionObject->boundingRect->sw->lon;
		$ne_lat = $city_map_json_data->regionSelectionObject->boundingRect->ne->lat;
		$ne_lon = $city_map_json_data->regionSelectionObject->boundingRect->ne->lon;

		$rect_param_string = $sw_lon . ',' . $sw_lat . ',' . $ne_lon . ',' . $ne_lat;

		$city_properties_zpids_url = 'https://www.zillow.com/search/GetResults.htm?spt=homes&status=110001&lt=111101&ht=111111&pr=,&mp=,&bd=0%2C&ba=0%2C&sf=,&lot=0%2C&yr=,&singlestory=0&hoa=0%2C&pho=0&pets=0&parking=0&laundry=0&income-restricted=0&fr-bldg=0&pnd=0&red=0&zso=0&days=any&ds=all&pmf=1&pf=1&sch=100111&zoom=8&rect='. $rect_param_string .'&p=1&sort=globalrelevanceex&search=map&rid=24115&rt=6&listright=true&isMapSearch=true&zoom=8';

		$response = $client->request('GET', $city_properties_zpids_url);

		$city_properties_zpids_string = (string) $response->getBody();

		$city_properties_zpids_json = json_decode($city_properties_zpids_string);

		$zipcodes_json_array = $city_properties_zpids_json->map->properties;

		$agent_info_json_result = array();

		for ($i = 0; $i < count($zipcodes_json_array); $i ++) {
			$home_url = $zipcodes_json_array[$i][0];
			$property_url = 'https://www.zillow.com/homes/for_sale/' . $city_name . '/pmf,pf_pt/' . $home_url . '_zpid';
			$response = $client->request('GET', $property_url);

			$property_content = (string) $response->getBody();

			preg_match("/SimilarHomesHook\(\)\;asyncLoader\.load\(\{ajaxURL\:\"(.*?)\"\,jsModule\:\"z\-complaint\-manager\-async\-block\"\,phaseType\:\"scroll\"\,divId\:\"listing\-provided\-by\-module\"/", $property_content, $m );

			if(isset($m[1])) {
				$listing_provide_api_url = $m[1];

				$response = $client->request('GET', 'https://www.zillow.com' . $listing_provide_api_url);

				$listing_provide_string = (string) $response->getBody();

				$listing_provide_json = json_decode($listing_provide_string);

				$listing_provide_html = $listing_provide_json->html;

				if($listing_provide_html != ''){
					preg_match("/\"zsg\-media\-bd\\\"\> \<p \>(.*?)\,/", $listing_provide_html, $name );
					preg_match("/target\=\\\"\_blank\\\"\>(.*?)\</", $listing_provide_html, $source );
					preg_match("/a\>\, (.*?)\,/", $listing_provide_html, $phone );

					$agent = new stdClass();
					if(isset($name[1])){
						$agent->Full_Name = $name[1];
					}
					if(isset($source[1])){
						$agent->Source = $source[1];
					}
					if(isset($phone[1])){
						$agent->Phone_Number = $phone[1];
					}
					$agent_info_json_result[] = $agent;
				}
			} else {
				continue;
			}


		}
	} else {
		echo "Update Scraper!!!";
	}

	$json_agent_list = json_encode($agent_info_json_result, JSON_PRETTY_PRINT);

	header('Content-disposition: attachment; filename=agent_list.json');
	header('Content-type: application/json');
	echo $json_agent_list;

	// var_dump($agent_info_json_result);

?>