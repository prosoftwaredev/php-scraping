<?php
	// $page_scraper = curl_init();
	// curl_setopt($page_scraper, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36");
	// curl_setopt($page_scraper, CURLOPT_FAILONERROR, true);
	// curl_setopt($page_scraper, CURLOPT_SSL_VERIFYPEER, false);
	// curl_setopt($page_scraper, CURLOPT_RETURNTRANSFER, true);
	// curl_setopt($page_scraper, CURLOPT_FOLLOWLOCATION, true);
	// curl_setopt($page_scraper, CURLOPT_URL, 'https://www.zillow.com/homes/for_sale/31466513_zpid/44.022447,-77.450867,43.267206,-81.405945_rect/7_zm/1_fr/');
	// $page_content = curl_exec($page_scraper);
	// if(curl_errno($page_scraper)) // check for execution errors
	// {
	//     echo 'Scraper error: ' . curl_error($page_scraper);
	//     exit;
	// }
	// curl_close($page_scraper);

	// $DOM = new DOMDocument;

	// libxml_use_internal_errors(true);

	// if (!$DOM->loadHTML($page_content))
	// 	{
	// 		$errors="";
	// 	    foreach (libxml_get_errors() as $error)  {
	// 			$errors.=$error->message."<br/>"; 
	// 		}
	// 		libxml_clear_errors();
	// 		print "libxml errors:<br>$errors";
	// 		return;
	// 	}

	// $xpath = new DOMXPath($DOM);

	// //agent api url
	// $url_query = $xpath->query('//html')->item(0);
	// preg_match("/SimilarHomesHook\(\)\;asyncLoader\.load\(\{ajaxURL\:\"(.*?)\"/", $url_query->nodeValue, $m );
	// $url = $m[1];

	// if ($url) {
		$agent_info_url = 'https://www.rew.ca/properties/R2204108/602-4900-cartier-street-vancouver-bc?property_browse=vancouver-bc';

		$agent_info = curl_init();
		curl_setopt($agent_info, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36");
		curl_setopt($agent_info, CURLOPT_FAILONERROR, true);
		curl_setopt($agent_info, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($agent_info, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($agent_info, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($agent_info, CURLOPT_URL, $agent_info_url);


		$agent_info_html = curl_exec($agent_info);
		curl_close($agent_info);

		$DOM = new DOMDocument;

		libxml_use_internal_errors(true);

		if (!$DOM->loadHTML($agent_info_html))
			{
				$errors="";
			    foreach (libxml_get_errors() as $error)  {
					$errors.=$error->message."<br/>"; 
				}
				libxml_clear_errors();
				print "libxml errors:<br>$errors";
				return;
			}

		$xpath = new DOMXPath($DOM);

		$agent_info_text = $xpath->query('//div[@id="agentPhoneModal"]//div[@class="agentphones"]');

		$agent_info_json_result = array();

		for($i = 1; $i <= $agent_info_text->length; $i ++) {
			$agent_info = new stdClass();
			$agent_name = $xpath->query('//div[@id="agentPhoneModal"]//div[@class="agentphones"][' . $i . ']//div[@class="agentphones-name"]')->item(0)->nodeValue;

			$agent_info->Name = trim(preg_replace('/\n/', '', str_replace('Call', '', $agent_name)));
			
			$agent_phones = $xpath->query('//div[@id="agentPhoneModal"]//div[@class="agentphones"][' . $i . ']//dt');
			for($j = 0; $j < $agent_phones->length; $j ++) {
				$agent_phone_type = $xpath->query('//div[@id="agentPhoneModal"]//div[@class="agentphones"][' . $i . ']//dt')->item($j)->nodeValue;
				$agent_phone_number = $xpath->query('//div[@id="agentPhoneModal"]//div[@class="agentphones"][' . $i . ']//a')->item($j)->nodeValue;

				$agent_info->$agent_phone_type = $agent_phone_number;
			}

			$agent_info_json_result[] = $agent_info;
		}

		$agent_info_json_result = json_encode($agent_info_json_result, JSON_PRETTY_PRINT);

		header('Content-disposition: attachment; filename=REW.ca_agent_list.json');
		header('Content-type: application/json');
		echo $agent_info_json_result;
		
	// } else {
	// 	echo "Please Update Scraper! Maybe Site structure updated.";
	// }


?>