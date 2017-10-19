<?php
	$agent_info_json_result = array();

	$first_url_scraper = curl_init();


	curl_setopt($first_url_scraper, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36");
	curl_setopt($first_url_scraper, CURLOPT_FAILONERROR, true);
	curl_setopt($first_url_scraper, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($first_url_scraper, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($first_url_scraper, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($first_url_scraper, CURLOPT_COOKIEFILE, 'cookie.txt');
	curl_setopt($first_url_scraper, CURLOPT_URL, 'https://www.zillow.com/search/GetResults.htm?spt=homes&status=100001&lt=111101&ht=111111&pr=,&mp=,&bd=0%2C&ba=0%2C&sf=,&lot=0%2C&yr=,&singlestory=0&hoa=0%2C&pho=0&pets=0&parking=0&laundry=0&income-restricted=0&fr-bldg=0&pnd=0&red=0&zso=0&days=any&ds=all&pmf=1&pf=1&sch=100111&zoom=7&rect=-83685608,41385051,-79730530,41611335&p=1&sort=globalrelevanceex&search=map&rid=24115&rt=6&listright=true&isMapSearch=true&zoom=7');

	$homes_info = curl_exec($first_url_scraper);

	$zipcodes_json_array = json_decode($homes_info);
	curl_close($first_url_scraper);

	$zipcodes_json_array = $zipcodes_json_array->map->properties;

	if($zipcodes_json_array){
		for($i = 0; $i < 100; $i ++ ) {
			
			$home_url = $zipcodes_json_array[$i][0];

			$page_scraper = curl_init();
			curl_setopt($page_scraper, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36");
			curl_setopt($page_scraper, CURLOPT_FAILONERROR, true);
			curl_setopt($page_scraper, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($page_scraper, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($page_scraper, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($page_scraper, CURLOPT_URL, 'https://www.zillow.com/homes/for_sale/Cleveland-OH/pmf,pf_pt/'.$home_url.'_zpid/24115_rid/globalrelevanceex_sort/41.611335,-79.73053,41.385051,-83.685608_rect/7_zm/');
			$page_content = curl_exec($page_scraper);
			curl_close($page_scraper);

			//agent api url
			preg_match("/SimilarHomesHook\(\)\;asyncLoader\.load\(\{ajaxURL\:\"(.*?)\"\,jsModule\:\"z\-complaint\-manager\-async\-block\"\,phaseType\:\"scroll\"\,divId\:\"listing\-provided\-by\-module\"/", $page_content, $m );
			

			if (isset($m[1])) {
				$url = $m[1];
				$agent_info_json_url = 'https://www.zillow.com' . $url;

				$agent_info = curl_init();
				curl_setopt($agent_info, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36");
				curl_setopt($agent_info, CURLOPT_FAILONERROR, true);
				curl_setopt($agent_info, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($agent_info, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($agent_info, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($agent_info, CURLOPT_URL, $agent_info_json_url);

				$agent_info_json = curl_exec($agent_info);
				curl_close($agent_info);

				$agent_info_json_array = json_decode($agent_info_json);

				if($agent_info_json_array) {
					$agent_info_html_text = $agent_info_json_array->html;
				} else {
					continue;
				}

				$agent_info_html = html_entity_decode($agent_info_html_text);

				if($agent_info_html) {
					$DOM = new DOMDocument;

					libxml_use_internal_errors(true);

					if (!$DOM->loadHTML($agent_info_html))
					{
						continue;
					}

					$xpath = new DOMXPath($DOM);

					$agent_info_text = $xpath->query('//div[@class="zsg-media-bd"]//p')->item(0);

					if($agent_info_text){

						$agent = new stdClass();

						$agent->info = $agent_info_text->nodeValue;
						
						$agent_info_json_result[] = $agent;
						
					} else {
						continue;
					}
				} else {
					continue;
				}

			} else {
				continue;
			}
		}
	} else {
		echo "Please Update Scraper! Maybe Site structure updated.";
	}
		

	$agent_info_json_result = json_encode($agent_info_json_result, JSON_PRETTY_PRINT);
	header('Content-disposition: attachment; filename=zillow_agent_list_1.json');
	header('Content-type: application/json');
	echo $agent_info_json_result;
	// var_dump($zipcodes_json_array);


?>