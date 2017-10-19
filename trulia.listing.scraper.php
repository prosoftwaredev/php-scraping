<?php

require realpath(__DIR__ . '/../public/env.php');
require realpath(__DIR__ . '/../app/configs/environment.php');
require __DIR__ . '/trulia.common.php';

ini_set('max_execution_time',0);
ignore_user_abort(true);
libxml_use_internal_errors(true);

Logger::setEcho(true);
Logger::setHtml(false);
Logger::setLogTypes(Logger::LOG_TYPE_DEBUG);

define('DELAY_SECONDS', 0);
define('MAX_PROPERTIES', 50);

$property_url_regex = '|/property/[0-9A-Za-z\-]+|';
$agent_url_regex = '|/profile/[0-9A-Za-z\-]+|';
$new_only = false;
$now = new DateTime;

if ($last_listing = KnownListing::first(array('order' => 'created_at DESC'))) {
	$last_run = $last_listing->created_at;
	$days = $last_listing->created_at->diff($now)->format('%a');
	define('DAYS_SINCE_LAST_RUN', $days + 1);
	$new_only = true;
	Logger::log('Last run was ' . $days . ' day' . ($days == 1 ? '' : 's') . ' ago. Will store listings added to Trulia in the last ' . $days . ' day' . ($days == 1 ? '' : 's') . ' only.');
} else {
	Logger::log('First run. ' . MAX_PROPERTIES . ' listings per city will be retrieved.');
}

if (!isset($argv[1]) || !$argv[1]) {
	echo 'First argument must be a location in the format New York, NY, US';
	exit(1);
}

if (!isset($argv[2]) || !$argv[2]) {
	echo 'Second argument must be a timezone (EST, CST, MST or PST)';
	exit(1);
}

$location_parts = preg_split('/, ?/', $argv[1]);

if (count($location_parts) != 3) {
	echo 'First argument must be a location in the format New York, NY, US';
	exit(1);
}

$timezone = $argv[2];

list($city, $state, $country) = $location_parts;

$page = 1;
$properties_processed = 0;

$property_urls = array();

Logger::log('Finding properties for ' . $city . ', ' . $state);

while ($new_only || ($properties_processed < MAX_PROPERTIES)) {

	$url = 'https://www.trulia.com/' . $state . '/' . str_replace(' ', '_', $city) . ($page > 1 ? '/' . $page . '_p' : '');

	$html = getHtml($url);
	
	$property_url_matches = array();
	
	if (stristr($html, 'Your search does not match any homes')) break;

	preg_match_all($property_url_regex, $html, $property_url_matches);

	$property_urls = array_unique(array_shift($property_url_matches));

	Logger::log('Found ' . count($property_urls) . ' properties on page ' . $page);
	
	$property_urls = array_unique($property_urls);
	
	foreach ($property_urls as $property_url) {


echo $property_url . "\n";
//exit();
		
		if ($listing = process_property($property_url, $city, $state, $country, $timezone, $new_only)) {
			$properties_processed++;
		}
		if ($properties_processed > MAX_PROPERTIES) break;
	}
	
	$page++;

}

Logger::log('Processed ' . $properties_processed . ' unique properties total.');




function process_property($url, $city, $state, $country, $timezone, $new_only)
{

	$listing = KnownListing::find_by_source_url($url);
	
	if ($listing) {
		Logger::log('Listing already processed.');
		return false;
	}
		
	$property_html = getHtml($url);

	if (!$property_html) {
		Logger::log('Unable to get HTML for ' . $url);
		return false;
	}

	// Find listing agent
	
	$property_doc = new DomDocument;
	$property_doc->loadHTML($property_html);


echo $property_html . "\n\n";
exit();


	$property_xpath = new DomXPath($property_doc);
	$agent_nodes = $property_xpath->query("//*[@class='mvl ptm bts']");

	$agent_node_text = $agent_nodes->length ? $agent_nodes[0]->textContent : null;

	// Extract description
	
	$description_nodes = $property_xpath->query("//*[@id='corepropertydescription']");		
	$description = $description_nodes->length ? $description_nodes[0]->textContent : '';
	$description = trim(preg_replace('/\s+/', ' ', $description));
	$description = str_replace('Show more', '', $description);

	unset($property_doc);
	unset($property_xpath);
	unset($agent_nodes);
	unset($description_nodes);
	gc_collect_cycles(); // Force garbage collection in an effort to conserve memory
	
	if (!$agent_node_text) {
		Logger::log('Listing has no agent. Not processing.');
		return false;
	}
	
	$provided_by = str_replace('Listing Provided by' , '', $agent_node_text);
	$provided_by = trim(preg_replace('/\s+/', ' ', $provided_by));
	
	$provided_by_parts = explode(',', $provided_by);
	
	$phone = null;
	
	foreach ($provided_by_parts as $part) {
		if ($pos = stripos($part, 'Agent phone: ')) {
			$phone = substr($part, $pos + 13);
		}
	}
	
	$name = trim(array_shift($provided_by_parts));
	$brokerage = trim(array_shift($provided_by_parts));
	
	if ($name == 'Property Owner') {
		Logger::log('Listing is FSBO. Not processing.');
		return false;
	}

	if (!$phone) {
		Logger::log('No phone found for ' . $name . '. Not processing.');
		return false;
	}
	
	$agent = save_agent($name, $phone, $brokerage, $city, $state, $country, $timezone);


	// Extract property JSON

	preg_match_all('/\<script.+?\>(.+)\<\/script>/', $property_html, $script_matches);
	
	$jsonScript = '';
	
	foreach ($script_matches[1] as $script) {
		if (stristr($script, 'propertyJSON')) {
			$jsonScript = $script;
		}
	}

	$scriptlines = preg_split('/(?<!&[a-z]{3}|&[a-z]{4});/', $script); // Split by semicolon, except where it's part of an HTML entity
	
	$json = '';
	
	foreach ($scriptlines as $line) {
		if (stristr($line, 'propertyJSON')) {
			$json = preg_replace('/trulia\.pdp\.propertyJSON ?= ?/', '', $line);
		}
	}
	
	if (!$json) {
		Logger::log('No JSON found. Cannot process this listing.');
		return false;
	}
	
	$data = json_decode($json);
	
	if (!$data) {
		mail('shane@cityblast.com', 'Invalid JSON', $json);
		return false;
	}

	if ($data->isRental) {
		Logger::log('Listing is a rental. Not processing.');
		return false;
	}
	
	if (defined('DAYS_SINCE_LAST_RUN') && ($data->daysOnTrulia > DAYS_SINCE_LAST_RUN)) {
		Logger::log('Listing is older than last run. Not processing.');
		return false;
	}
	
	$street = $data->streetNumber . ' ' . $data->street;
	
	$listing = KnownListing::find_by_street_and_apartment_and_postal_code($street, $data->apartmentNumber, $data->zipCode);
	
	if ($listing) {
		Logger::log('Listing already present in database. Not processing.');
		return false;
	}
	
	$listing = new KnownListing;
	
	if ($agent) {
		$listing->known_agent_id = $agent->id;
		$listing->sales_agent_id = $agent->sales_agent_id;
	}
	
	$listing->description = $description;
	$listing->mls = $data->mlsID;
	$listing->community = $data->neighborhood;
	$listing->city = $data->city;
	$listing->state = $data->stateCode;
	$listing->latitude = $data->latitude;
	$listing->longitude = $data->longitude;
	$listing->street = $street;
	$listing->apartment = $data->apartmentNumber;
	$listing->property_type = $data->type;
	$listing->number_of_bedrooms = $data->numBedrooms;
	$listing->number_of_bathrooms = $data->numFullBathrooms + $data->numPartialBathrooms * .5;
	$listing->square_footage = $data->sqft;
	$listing->price = $data->price;
	$listing->postal_code = $data->zipCode;
	$listing->source_url = $url;
	$listing->save();
	
	Logger::log('Saved ' . $listing->street);
	
	return $listing;
	
}
