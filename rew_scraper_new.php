<?php

	require 'vendor/autoload.php';
	use GuzzleHttp\Client;
	use Symfony\Component\DomCrawler\Crawler;

	$client = new Client();

	$main_domain = 'https://www.rew.ca';

	$city_name = 'vancouver';

	$response = $client->request('POST', 'https://www.rew.ca/properties/search', [
	    'form_params' => [
	        'property_search[initial_search_method]' => 'single_field',
	        'property_search[query]' => $city_name
	    ]
	]);

	$content = (string) $response->getBody();

    // echo $content;

    $crawler = new Crawler($content);

	$agent_list = array();

	$page_property_id = $crawler->filterXPath('//div[@class="navbar--xsbottom-secondary"]//a')->attr('data-search-id');

	if(isset($page_property_id)) {
		for($i = 1; $i <= 25; $i ++) {
			$property_page_url = 'https://www.rew.ca/properties/search/' . $page_property_id . '/page/' . $i . '?ajax=true&property_search%5Binitial_search_method%5D=single_field&property_search%5Bquery%5D=' . $city_name;

			$response = $client->request('GET', $property_page_url);

			$crawler = new Crawler((string) $response->getBody());

			$nodeValues = $crawler->filterXPath('//article[@class="listing listing--branded"]//div[@class="listing-photo_container"]//a')->each(function (Crawler $node, $i) {

		    	$client = new Client();

		    	$main_domain_v = $GLOBALS['main_domain'];

			    $property_url = $main_domain_v . $node->attr('href');

			    $response = $client->request('GET', $property_url);

			    $property_content = (string) $response->getBody();

			    $crawler = new Crawler($property_content);

			    $agents_modal_contents = $crawler->filterXPath('//div[@id="agentPhoneModal"]//div[@class="agentphones"]')->each(function (Crawler $node, $i) {

			    	// echo str_replace('Call ', '', $node->text());

			    	$agent_info = new stdClass();

			    	$agent_info_text = str_replace('Call', '', $node->text());

			    	$agent_info->INFO = preg_replace('/\s+/', ' ', $agent_info_text);

			    	$GLOBALS["agent_list"][] = $agent_info;

				});

			    // echo $property_content;
			});

		}
		
	} else {
		echo "Site Updated! Please Update Scraper!";
	}

    

	$agent_info_json_result = json_encode($agent_list, JSON_PRETTY_PRINT);

	header('Content-disposition: attachment; filename=REW.ca_agent_list_new.json');
	header('Content-type: application/json');
	echo $agent_info_json_result;

	// echo $page_property_id;
?>