<?php

/**
* This is a subclass of EasyRdf_Sparql_client that overrides
* the communication with the sparql endpoint.
* This is necessary as the Sesame restful interface differs
* from the sparql 1.1 specification
*/
class WissKI_Sparql_Client extends EasyRdf_Sparql_Client {

  /**
  * Internal function to make an HTTP request to SPARQL endpoint
  * copy from original EasyRdf_Client with Sesame-specific overrides
  * this is NOT the function to be called from outside @see requestSPARQL
  *
  * @ignore
  */
  protected function request($type, $query) {

//    $log = fopen(dirname(__FILE__).'/wisski_log.txt','a');
//    fwrite($log,time()." - ".$type."\n".$query."\n\n");
//    fwrite($log, serialize(debug_backtrace()));
    // Check for undefined prefixes
    $prefixes = '';
    // @TODO: Check - this should not happen every time I query something, this is very 
    // inefficient. Just check it in case of updates!
//    $this->updateNamespaces();
    foreach (EasyRdf_Namespace::namespaces() as $prefix => $uri) {
      if (strpos($query, "$prefix:") !== false and strpos($query, "PREFIX $prefix:") === false) {
        $prefixes .=  "PREFIX $prefix: <$uri>\n";
      }
    }
    
    $client = EasyRdf_Http::getDefaultHttpClient();
    $client->resetParameters();

    // Tell the server which response formats we can parse
    $accept = EasyRdf_Format::getHttpAcceptHeader(
    array(
      'application/sparql-results+json' => 1.0,
      'application/sparql-results+xml' => 0.8
    )
    );

		$client->setHeaders('Accept', $accept);

		if ($type == 'update') {

			$client->setMethod('POST');
			$client->setUri($this->getUpdateUri());
			$encodedQuery = 'update='.urlencode($prefixes . $query);
			$client->setRawData($encodedQuery);
			$client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
	
		} elseif ($type == 'query') {
				// Use GET if the query is less than 2kB
				// 2046 = 2kB minus 1 for '?' and 1 for NULL-terminated string on server
				$encodedQuery = 'query='.urlencode($prefixes . $query);
				if (strlen($encodedQuery) + strlen($this->getQueryUri()) <= 2046) {
						$client->setMethod('GET');
						$client->setUri($this->getQueryUri().'?'.$encodedQuery);
				} else {
//                dpm(array('query' => $query, 'encoded' => $this->settings['query_endpoint'].'?'.$encodedQuery));
//                trigger_error('Query size > 2048. Switch to POST mode',E_USER_NOTICE);
						// Fall back to POST instead (which is un-cacheable)
						$client->setMethod('POST');
						$client->setUri($this->getQueryUri());
						$client->setRawData($encodedQuery);
						$client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
				}
		}
		//dpm((array)$client,'client');
//        watchdog('wisski_SPARQL_request_uri',$client->getUri());
		$response = $client->request();
		if ($response->getStatus() == 204) {
			// No content
			return $response;
		} elseif ($response->isSuccessful()) {
			list($type, $params) = EasyRdf_Utils::parseMimeType(
				$response->getHeader('Content-Type')
			);
			if (strpos($type, 'application/sparql-results') === 0) {
				return new EasyRdf_Sparql_Result($response->getBody(), $type);
			} else {
				return new EasyRdf_Graph($this->getQueryUri, $response->getBody(), $type);
			}
		} else {
			echo __METHOD__.' (line: '.__LINE__.') failed request '.htmlentities($query)."\n\r";
			throw new EasyRdf_Exception(
				"HTTP request for SPARQL query failed: ".$response->getBody()
			);
		}
		
	}




}
