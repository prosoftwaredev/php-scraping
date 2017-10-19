<?php
	$page_scraper = curl_init();
	curl_setopt($page_scraper, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36");
	curl_setopt($page_scraper, CURLOPT_FAILONERROR, true);
	curl_setopt($page_scraper, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($page_scraper, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($page_scraper, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($page_scraper, CURLOPT_URL, 'https://www.zillow.com/homes/for_sale/31466513_zpid/44.022447,-77.450867,43.267206,-81.405945_rect/7_zm/1_fr/');
	$page_content = curl_exec($page_scraper);
	if(curl_errno($page_scraper)) // check for execution errors
	{
	    echo 'Scraper error: ' . curl_error($page_scraper);
	    exit;
	}
	curl_close($page_scraper);

	$DOM = new DOMDocument;

	libxml_use_internal_errors(true);

	if (!$DOM->loadHTML($page_content))
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

	//agent api url
	$url_query = $xpath->query('//html')->item(0);
	preg_match("/SimilarHomesHook\(\)\;asyncLoader\.load\(\{ajaxURL\:\"(.*?)\"/", $url_query->nodeValue, $m );
	$url = $m[1];

	if ($url) {
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

		$agent_info_html_text = $agent_info_json_array->html;

		$agent_info_html = html_entity_decode($agent_info_html_text);

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

		$agent_info_text = $xpath->query('//div[@class="zsg-media-bd"]//p')->item(0);

		$agent_info_json_array = explode(",", $agent_info_text->nodeValue);

		$agent_info_json_result = array();

		$agent = new stdClass();

		$agent->Full_Name = $agent_info_json_array[0];
		$agent->Source = $agent_info_json_array[1];
		$agent->Phone_Number = $agent_info_json_array[2];

		$agent_info_json_result[] = $agent;

		$agent_info_json_result = json_encode($agent_info_json_result, JSON_PRETTY_PRINT);

		header('Content-disposition: attachment; filename=zillow_agent_list_1.json');
		header('Content-type: application/json');
		echo $agent_info_json_result;
		
	} else {
		echo "Please Update Scraper! Maybe Site structure updated.";
	}


?>