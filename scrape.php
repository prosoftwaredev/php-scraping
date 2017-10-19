<?php
	$page_scraper = curl_init();
	curl_setopt($page_scraper, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($page_scraper, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($page_scraper, CURLOPT_URL, 'https://www.trulia.com/property/3270529596-22-Gallatin-St-NE-D-Washington-DC-20011');
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

	//property id param
	$propertyId_query = $xpath->query('//a[@class="virtualTour"]/@href')->item(0);
	preg_match("/\&p_id=(.*?)\&/", $propertyId_query->nodeValue, $m );
	$propertyId = $m[1];


	//statecode param
	$stateCode_query = $xpath->query('//span[@class="miniHidden"]//a[@class="linkLowlight"]')->item(0);
	$stateCode = $stateCode_query->nodeValue;


	//user id param
	$user_id_query = $xpath->query('//div[@id="session"]')->item(0);
	$user_id = $user_id_query->nodeValue;

	if ($propertyId && $stateCode && $user_id) {
		$agent_info_json_url = 'https://www.trulia.com/_ajax/Conversion/LeadFormApi/form/?propertyId=' . $propertyId . '&searchType=for%20sale&stateCode=' . $stateCode . '&ab=LEAD_FORM_API&isBuilderCommunity=false&userId=' . $user_id . '&logged_in_user_id=0&source=www';

		$agent_info = curl_init();
		curl_setopt($agent_info, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($agent_info, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($agent_info, CURLOPT_URL, $agent_info_json_url);
		$agent_info_json = curl_exec($agent_info);
		curl_close($agent_info);

		$agent_info_json_array = json_decode($agent_info_json);

		$agent_list = array();

		foreach ($agent_info_json_array->contact_recipients as $key => $value) {
			
			$agent = new stdClass();
			$agent->Full_Name = $value->display_name;
			$agent->Badge_Type = $value->badge_type;
			$agent->Phone_Number = '(' . $value->phone->areacode . ') ' . $value->phone->prefix . '-' . $value->phone->number;
 			$agent_list[] = $agent;

		}

		$json_agent_list = json_encode($agent_list, JSON_PRETTY_PRINT);

		header('Content-disposition: attachment; filename=agent_list.json');
		header('Content-type: application/json');
		echo $json_agent_list;
		
		// var_dump($agent_info_json_array->contact_recipients);
	} else {
		echo "Please Update Scraper! Maybe Site structure updated.";
	}


?>