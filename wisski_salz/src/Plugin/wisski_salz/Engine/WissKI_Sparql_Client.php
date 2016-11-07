<?php

namespace Drupal\wisski_salz\Plugin\wisski_salz\Engine;

use EasyRdf_Sparql_Client;
use EasyRdf_Namespace;
use EasyRdf_Http;
use EasyRdf_Format;
use EasyRdf_Graph;
use EasyRdf_Exception;
use EasyRdf_Utils;
use EasyRdf_Sparql_Result;

/**
* This is a subclass of EasyRdf_Sparql_client that overrides
* the communication with the sparql endpoint.
* This is necessary for Sesame as the Sesame restful interface does not fully
* support the sparql 1.1 specification
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
    
    // Check for undefined prefixes
    $prefixes = '';
    // @TODO: Check - this should not happen every time I query something, this is very 
    // inefficient. Just check it in case of updates!
    foreach (EasyRdf_Namespace::namespaces() as $prefix => $uri) {
      if (strpos($query, "$prefix:") !== false and strpos($query, "PREFIX $prefix:") === false) {
        $prefixes .=  "PREFIX $prefix: <$uri>\n";
      }
    }
    
    $client = EasyRdf_Http::getDefaultHttpClient();
    $client->resetParameters();
    $client->setConfig(array(
        'maxredirects'    => 5,
        'useragent'       => 'EasyRdf_Http_Client',
        //we change the timeout from 10 secs since some of our requests will necessarily take much longer
        'timeout'         => 600,
    ));

    // Tell the server which response formats we can parse
    $accept = EasyRdf_Format::getHttpAcceptHeader(
    array(
      'application/sparql-results+json' => 1.0,
      'application/sparql-results+xml' => 0.8
    )
    );

		$client->setHeaders('Accept', $accept);

		if ($type == 'update') {

      // this is where Sesame differs
      // it does not accept POST directly as described in
      // https://www.w3.org/TR/2013/REC-sparql11-protocol-20130321/#query-operation
			$client->setMethod('POST');
			$client->setUri($this->getUpdateUri());
			$encodedQuery = 'update='.urlencode($prefixes . $query);
			$client->setRawData($encodedQuery);
			$client->setHeaders('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8');
	
		} elseif ($type == 'query') {
				// Use GET if the query is less than 2kB
				// 2046 = 2kB minus 1 for '?' and 1 for NULL-terminated string on server
				$encodedQuery = 'query='.urlencode($prefixes . $query);
				if (strlen($encodedQuery) + strlen($this->getQueryUri()) <= 2046) {
						$client->setMethod('GET');
						$client->setUri($this->getQueryUri().'?'.$encodedQuery);
				} else {
						// Fall back to POST instead (which is un-cacheable)
						$client->setMethod('POST');
						$client->setUri($this->getQueryUri());
						$client->setRawData($encodedQuery);
						$client->setHeaders('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8');
				}
		}
		$response = $client->request();
		//if ($type === 'update') dpm($response,$encodedQuery);
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
				return new EasyRdf_Graph($this->getQueryUri(), $response->getBody(), $type);
			}
		} else {
			$message = __METHOD__.' (line: '.__LINE__.') failed request '.$query. "\n\r---\n\r" . $response->getBody();
			//ddebug_backtrace();
			\Drupal::logger('wisski_sparql_client '.$type.' failed')->error('{message}',array('message'=>$message));
			throw new EasyRdf_Exception(
				"HTTP request for SPARQL query failed: ".$response->getBody()
			);
		}
		
	}



}


