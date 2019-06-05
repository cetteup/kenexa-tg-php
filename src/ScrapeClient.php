<?php
namespace Diza\TGScraper;

use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;

class ScrapeClient {
    private $config;
    private $client;

    private $requests_made;

    const API_ROOT = 'https://krb-sjobs.brassring.com/TgNewUI/Search/Ajax/';

    public function __construct($tg_partner_id, $tg_site_id, $tg_question_name_map = [], $timeout = 6.0)
    {
        // Make sure required inputs are set
        if (!isset($tg_partner_id) || !isset($tg_site_id)) throw new ClientException('Missing TG client id/site id');

        // Save tg details to config
        $this->config = [
            'tg_partner_id' => $tg_partner_id,
            'tg_site_id' => $tg_site_id,
            'tg_question_name_map' => $tg_question_name_map
        ];

        // Init client
        $this->client = new Client(['base_uri' => self::API_ROOT, 'cookies' => true, 'timeout' => $timeout]);

        // Init request counter
        $this->requests_made = 0;
    }

    /**
    * Fetch session cookie required as "authorization" by getting the tg home page
    */
    public function fetchAutoCookie()
    {
        // Request home page (partner_id and site_id will be added by send_request)
        $this->send_request('GET', 'https://krb-sjobs.brassring.com/TGnewUI/Search/Home/Home', 'query');
    }

    /**
    * Retrieves all available power search filters for current tg
    */
    public function getPowerSearchFilters()
    {
        $search_filters = $this->send_request('POST', 'PowerSearch', 'json');

        return $search_filters;
    }

    /**
    * Extracts all autocomplete filters from a search filters ResponseArray
    * @param array ResponseArray of search filters
    * @return array Array of autocomplete filters
    */
    function extractAutocompleteFilters($search_filters)
    {
        $autocomplete_filters = array_filter((array) $search_filters, function ($search_filter) {
            return $search_filter['IsAutoComplete'];
        });

        return $autocomplete_filters;
    }

    /**
    * Retrieve a set of filter/question options via autocomplete
    * @param int $question_id Id of filter/question
    * @param int $parent_question_id Id of parent filter/question
    * @return array Filter/question options (max. 10)
    */
    private function fetchAutoCompleteOptions($question_id, $parent_question_id, $search_string, $known_options = [])
    {
        $options = $this->send_request('POST', 'PowerSearchAutoComplete', 'form_params', [
            'partnerId' => $this->config['tg_partner_id'],
            'siteId' => $this->config['tg_site_id'],
            'questionId' => $question_id,
            'parentQuestionId' => $parent_question_id,
            'searchString' => $search_string,
            'selectedQuestionCodes' => implode('#@#', $known_options)
        ]);

        return $options;
    }

    /**
    * Restructure options array into [OptionName] => OptionValue format
    */
    private function restructureOptionsArray($options_array)
    {
    	$restructured_array = [];

    	foreach ($options_array as $option) {
    		if (!array_key_exists($option['OptionName'], $restructured_array)) $restructured_array[$option['OptionName']] = $option['OptionValue'];
    	}

    	return $restructured_array;
    }

    /**
    * Get a single page of search results
    */
    function performAdvancedSearch($page_number, $search_options = []) {
		$jobs = $this->send_request('POST', 'ProcessSortAndShowMoreJobs', 'json', [
			'partnerId' => $this->config['tg_partner_id'],
			'siteId' => $this->config['tg_site_id'],
			'pageNumber' => $page_number,
			'SortType' => 'JobTitle',
			'Latitude' => 0,
			'Longitude' => 0,
			'keyword' => '',
			'keywordCustomSolrFields' => 'FORMTEXT23,JobTitle,AutoReq',
			'location' => '',
			'turnOffHttps' => false,
			'facetfilterfields' => [
				'Facet' => []
			],
			'powersearchoptions' => [
				'PowerSearchOption' => $search_options
			]
		]);

    	return $jobs;
    }

    /**
    * Job job details for a specified job id
    */
    public function fetchJobDetails($jobId) {
        $jobDetails = $this->send_request('POST', 'JobDetails', 'json', [
            'configMode' => '',
            'jobSiteId' => $this->config['tg_site_id'],
            'jobid' => $jobId,
            'partnerId' => $this->config['tg_partner_id'],
            'siteId' => $this->config['tg_site_id']
        ]);

        return $jobDetails;
    }

    private function send_request($method, $endpoint, $param_type, $params = [], $return_response = true)
    {
        // Add tg partner and site id to params
        $params = array_merge(['partnerId' => $this->config['tg_partner_id'], 'siteId' => $this->config['tg_site_id']], $params);

        try {
            $response = $this->client->request($method, $endpoint, [$param_type => $params]);
            $this->requests_made++;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                throw new ClientException($response->getStatusCode().' '.$response->getReasonPhrase());
            } else {
                throw new ClientException($e->getMessage());
            }
        }

        return new ResponseArray(
            $response->getHeaders(),
            json_decode($response->getBody(),true)
        );
    }
}
