<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Plugin\wisski_salz\Engine\WisskiSparql11Plugin.
 */

namespace Drupal\wisski_salz\Plugin\wisski_salz\Engine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_salz\EngineBase;

/**
 * Wiki implementation of an external entity storage client.
 *
 * @Engine(
 *   id = "sparql11",
 *   name = @Translation("Sparql 1.1"),
 *   description = @Translation("Provides access to a SPARQL endpoint that supports SPARQL 1.1")
 * )
 */
class Sparql11Engine extends EngineBase {

  protected $read_url;
  protected $write_url;
  
  /** Holds the EasyRDF sparql client instance that is used to
   * query the endpoint.
   * It is not set on construction.
   * Use getEndpoint() for direct access to the API.
   * 
   * However, the API should not be exposed outside this class, rather this
   * class provides directQuery() and directUpdate() for sending sparql queries
   * to the store.
   */ 
	protected $endpoint = NULL;
  
  
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'read_url' => '',
      'write_url' => '',
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    $this->read_url = $this->configuration['read_url'];
    $this->write_url = $this->configuration['write_url'];
    $this->store = NULL;
  }


  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [
      'read_url' => $this->read_url,
      'write_url' => $this->write_url
    ] + parent::getConfiguration();
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    
    $form['read_url'] = [
      '#type' => 'textfield',
      '#title' => 'Read URL',
      '#default_value' => $this->read_url,
      '#description' => 'bla.',
    ];
    $form['write_url'] = [
      '#type' => 'textfield',
      '#title' => 'Write URL',
      '#default_value' => $this->write_url,
      '#description' => 'bla.',
    ];
    
    return parent::buildConfigurationForm($form, $form_state) + $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::buildConfigurationForm($form, $form_state);
    $this->read_url = $form_state->getValue('read_url');
    $this->write_url = $form_state->getValue('write_url');
  }
  

  
  //*** Implementation of the EngineInterface methods ***//
  

  public function hasEntity($entity_id) {
    return FALSE;
  }

  
  /**
   * @deprecated
   * {@inheritdoc}
   */
  public function loadMultiple($entity_ids = NULL) {
    return array("bla", "blubb");
  }
  

  /**
   * {@inheritdoc}
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {
    return array(
      "foo" => array(
        'x-default' => array(
          'main' => 'abc',
          'value' => 'def',
        )
      )
    );
  }
  

  

  /**
   * {@inheritdoc}
   */
  public function loadPropertyValuesForField($field_id, array $property_ids, $entity_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {
    return array(
      "foo" => array(
        'x-default' => array(
          'main' => 'abc',
          'value' => 'def',
        )
      )
    );
  }





  //*** SPARQL 11 specific members and methods ***//


 	/** Return the API to connect to the sparql endpoint
	* 
	* This method should be called if you need an endpoint. It lazy loads the Easyrdf instance
  * which may save time.
  * 
  * @return Returns a EasyRdf_Sparql_Client instance (or a subclass) that is inited to
  * connect to the givensparql 1.1 endpoint.
	*/
	protected function getEndpoint() {
		
		if ($this->endpoint === NULL) {
      include_once(__DIR__ . '/WissKI_Sparql_Client.php');
			$this->endpoint = new WissKI_Sparql_Client($this->read_url, $this->write_url);
		}
		return $this->endpoint;

	}	
	

  // *** PUBLIC MEMBER FUNCTIONS *** //

	// 
  // Functions for direct access, firstly designed for test purposes	
	// 
		
	/** Can be used to directly access the easyrdf sparql interface
	*
	* If not necessary, don't use this interface. 
	* 
	* @return @see EasyRdf_Sparql_Client->query
	*/
	public function directQuery($query) {
		return $this->getEndpoint()->query($query);
	}

	/** Can be used to directly access the easyrdf sparql interface
	*
	* If not necessary, don't use this interface
	* 
	* @return @see EasyRdf_Sparql_Client->update
	*/
	public function directUpdate($query) {
		return $this->getEndpoint()->update($query);
	}



	public function getPathArray($path) {
		
		
		
	}
	

	
  /** Builds a sparql query from a given path and execute it.
	*
  * !This is thought to be a convenience function!
  *
	* For a documentation of the parameters see buildQuerySinglePath()
  *
  * @return Returns an EasyRDF result class depending on the query (should be
  *  EasyRdfSparqlResult though as the query verb is always SELECT)
  */
	public function execQuerySinglePath(array $path, array $options = array()) {
		
		if (empty($path)) {
			throw new InvalidArgumentException("Empty path given");
		}
		
		if (is_numeric($path)) {
			$path = $this->getPathArray($path);
		}

		if (!is_array($path) || empty($path)) {
			throw new InvalidArgumentException("Bad path given: " . serialize($path));
		}
		
		// prepare query
		$options['fields'] = FALSE;
		
		// build it
		$sparql = $this->buildQuerySinglePath($path, $options);
		
		// exec
		$result = $this->directQuery($sparql);
		
		// postprocess result?
		
		
		return $result;
			
	}
	

	
	/** This function returns a SPARQL 1.1 query for a given path.
  *
  * !This is thought to be a convenience function!
   *
   * @param path is an associative array that may contain
   * the following entries:
   * $key		| $value
   * ------------------------------------------------------------
   * 'path_array' 	| array of strings representing owl:ObjectProperties 
   *                    | and owl:Classes in alternating order
   * 'datatype_property'| string representing an owl:DatatypeProperty
   *
   * For the path_array, instead of strings, also arrays with more 
   * sophisticated options are supported. See code comments below for details.
   *
   * @param options is an associative array that may contain the following 
   * entries:
   * $key 		| $value
   * ------------------------------------------------------------
   * 'limit'		| int setting the SPARQL query LIMIT
   * 'offset'		| int setting the SPARQL query OFFSET
	 * 'vars' 		| array with the variables that should be returned.
   *            | the variable name must be preceeded with an '?'.
   *            | Defaults to all variables.
   * 'var_inst_prefix'		| SPARQL variable name prefix for the datatype value
   *                      | the prefix must be without leading '?' or '$'
   *                      | Defaults to 'x'.
   * 'var_offset'	| int offset for SPARQL variable names.
   *              | Variables will be constructed using the var_inst_prefix and
   *              | a number. Specify the offset here. Default is 0.
   * 'var_dt'		| SPARQL variable name for the datatype value. Default: 'out'
   * 'order'		| string containing 'ASC' or 'DESC' (or 'RAND')
   * 'qualifier'	| SPARQL data qualifier e.g. 'STR'
   * 'search_dt'		| a search struct. See _buildSearchFilter()
   * 'uris'		| array of strings representing owl:Individuals on which the
   *			| query is triggered OR
   *			| an assoc array of such arrays where the keys are the variable name
	 *			| that the uris shall be bound to
   * 'fields' | if set to TRUE, return the query parts as array
   *
   * @return the sparql query as a string or the query parts if option fields
   *          is TRUE
   */
	public function buildQuerySinglePath(array $path, array $options = []) {
		
		// variable naming
		$varInstPrefix = isset($options['var_inst_prefix']) ? $options['var_inst_prefix'] : 'x';
		$varOffset = isset($options['var_offset']) ? $options['var_offset'] : 0;
		$varDt = '?' . (isset($options['var_dt']) ? $options['var_dt'] : 'out');
				
		// vars for the query parts
    $head = "SELECT DISTINCT ";
    $vars = [];
   	$triples = '';
		$constraints = '';
		$order = '';
		$limit = '';
		
		$pathArray = $path['path_array'];
		if (empty($pathArray)) {
			throw new InvalidArgumentException('Path of length zero given.');
		}

		$uris = isset($options['uris']) ? $options['uris'] : [];

		$var = '';
		
		while (!empty($pathArray)) {
			
			// an individual
		  //
			// currently supported values:
			// - a string containing a single uri which is the name of the
			//  	this individual belongs to
			// - an array with the following supported keys:
			//   - constraints: an assoc array where the keys are properties
			//			and the value is an array of URIs for classes or indivs
			//      the constraints are or'ed

			$indiv = array_shift($pathArray);
			$var = "?$varInstPrefix$varOffset";
			$vars[$var] = $var;

			if (!is_array($indiv)) {
				$indiv = [
					'constraints' => [
						'a' => [$indiv],
					],
				];
			}
			
			// constrain possible uris
			if (isset($uris[$var])) {
				$constraints .= "VALUES $var {<" . implode('> <', $uris[$var]) . ">} .\n";
			}
			
			// further triplewise constraints
			foreach ($indiv['constraints'] as $prop => $vals) {
				foreach ($vals as $val) {
					$triples .= $var . ($prop == 'a' ? ' a ' : " <$prop> ") . "<$val> .\n";
				}
			}

			if (!empty($pathArray)) {
				// a property
				//
				// currently supported values:
				// - a string containing the uri of the property
				// - an array with the following supported keys:
				//   - uris: an assoc array where the keys are uris
				// 			and the value is either:
				//			1: normal direction
				//			2: inverse direction
				//			3: both directions (symmetric property)
				//   - expand inverses: if TRUE, expand the given uris to all inverses, too

				$prop = array_shift($pathArray);
				
				if (!is_array($elem)) {
					$prop = [
						'uris' => [$prop => 1],	// normal direction
            'expand inverses' => TRUE,
					];
				}
				
				if (empty($prop['uris'])) {
					throw new InvalidArgumentException('No URIs given for property.');
				} 

				// compute the inverse(s) if not given
				// TODO: magic numbers to constants
				if (!empty($prop['expand inverses'])) {
					foreach ($prop['uris'] as $uri => $direction) {
						if ($direction == 3) continue; // its own inverse => do nothing
						$inv = $this->getInverse($uri);
						if (!empty($inv)) {
							if (!isset($prop['uris'][$inv])) {
								// if prop does not exist, we add it with the opposite direction
								$prop['uris'][$this->getInverse($uri)] = $direction == 2 ? 1 : 2;
							} else {
								// if prop does exist, we or existing and new direction
								// making it possibly symmetric
								$prop['uris'][$this->getInverse($uri)] |= $direction;
							}
						}
					}
				}
				
				// variable for next indiv				
				$varPlus = "?$varInstPrefix" . ($varOffset + 1);
				$vars[$varPlus] = $varPlus;
	
				
				// generate triples for inverse and normal
				$tr = [];
				foreach ($prop['uris'] as $uri => $direction) {
					if ($direction & 1) {
						$tr[] = "$var <$uri> $varPlus . ";
					}
					if ($direction & 2) {
						$tr[] = "$varPlus <$uri> $var . ";
					}
				}
				if (count($tr) == 1) {
					$triples .= $tr[0];
				} else {
					$triples .= '{ { ' . join(' } UNION { ', $tr) . ' } }';
				}
				$triples .= "\n";
				
				// we update the last var here
				$var = $varPlus;

			}
			
			// we always increment the counter, even if a step defines its own name
			// this helps for more opacity
			$varOffset++;	

		} // end path while loop
		
		// add datatype property/ies if there
		if (isset($path['datatype_property'])) {
			
			$vars[$varDt] = $varDt;
			$props = $path['datatype_property'];
			
			if (!is_array($props)) {
				$props = [
					'uris' => [$props],
			 	];
			}

			// add the triple(s)
			$tr = [];
			foreach ($props['uris'] as $prop) {
				$tr[] = "$var <$prop> $varDt .";
			}
			if (count($tr) == 1) {
				$triples .= $tr[0];
			} else {
				$triples .= '{ { ' . join(' } UNION { ', $tr) . ' } }';
			}
			$triples .= "\n";

			if (isset($options['search_dt'])) {
				$constraints .= $this->_buildSearchFilter($options['search_dt'], $varDt) . "\n";
			}

		} // end datatype prop
	
		// set order: we either order by 
		// - the variable set in order_var (and it exists)
		// - or the datatype variable (if it exists)
		// otherwise we ignore order option
		if (isset($options['order']) && $options['order'] != 'RAND' &&
				((isset($options['order_var']) && isset($vars[$options['order_var']])) || isset($path['datatype_property']))
				) {
			$orderVar = (isset($options['order_var']) && isset($vars[$options['order_var']])) ? $options['order_var'] : $varDt;
			$order .= "ORDER BY";
			$order .= $options['order'] . '(';
			if (isset($options['qualifier'])) {
				$order .= $options['qualifier'] . "($orderVar)";
			} else {
				$order .= $orderVar;
			}
			$order .= ')';
		}
		
		// set limit and offset
		if (!empty($options['limit'])) $limit .= 'LIMIT ' . $options['limit'];
		if (!empty($options['offset'])) $limit .= 'OFFSET ' . $options['offset'];

		// filter out vars that we don't want to have
		if (isset($options['vars'])) {
			$vars = array_intersect($vars, $options['vars']);
		}
		
    // return either a complete query as string or its parts as an array
		return empty($options['fields']) ? 
			$head . join(' ', $vars) . ' WHERE { ' . $triples . $constraints . '} ' . $order . $limit
			: [
				'head' => $head,
				'vars' => $vars,
				'triples' => $triples,
				'constraints' => $constraints,
				'order' => $order,
				'limit' => $limit,
			];

	}

  
  /** Helper function that parses a search struct and builds a sparql filter
  * from it.
  *
  * The struct will be applied to exactly one variable. The variable must
  * contain a literal value. Search on URIs is not possible. See options[uris]
  * param of buildQuerySinglePath() if you need to restrict the URIs.
  *
  * @param search the search struct. It may be either
  *         a) an array list with possible values
  *         b) an assoc array with two keys
  *           'mode':   the logical or comparison operator to be used.
  *                     Currently supported: AND OR NOT = != < > CONTAINS REGEX
  *           'terms':  Applies to AND and OR. An array of search structs of
  *                     type b that is or'ed/and'ed
  *           'term':   Applies to all other operators. In case of a logical op
  *                     it is a search struct of type b that is applied to the
  *                     operator. In case of a comparison it is a string or
  *                     numeral that is compared to the variable.
  *
  * @param dtVar the name of the variable that is search upon.
  *         With leading '?'!
  *
  * @param depth internal parameter, should be omitted if called from outside
  *         this function
  *
  * @return a sparql statement, usually a FILTER statement
  */
	public function _buildSearchFilter(array $search, $dtVar, $depth = 0) {
		
		if (empty($search)) {

			return '';

		} elseif ($depth == 0 && isset($search['mode'])) {
			
			return "FILTER " . $this->_buildSearchFilter($search, $dtVar, 1);
				
		} elseif ($depth == 0 && !empty($search)) {

			// an easy case: we just search for a list of literals
			// we use the values construct as it may be faster and more readable
			$res = "VALUES $dtVar { ";
			foreach ($search as $t) {
				$res .= "'" . $this->_escapeSparqlLiteral($t) . "' ";
			}
			$res .= "}";
			return $res;

		} elseif (isset($search['mode'])) {
			
			$mode = strtoupper($search['mode']);
			switch ($mode) {
				case 'AND':
				case 'OR':
					$res = [];
					$terms = $search['terms'];
					foreach ($terms as $term) {
						$res[] = $this->_buildSearchFilter($term, $dtVar, $depth + 1);
					}
					return '(' . join(" $mode ", $res) . ')';

				case 'NOT':
					$res = $this->_buildSearchFilter($search['term'], $dtVar, $depth + 1);
					return "( NOT $res )";
				
				// comparison of strings and numbers
				case '=':
				case '!=':
				case '<':
				case '>':
					$term = $search['term'];
					if (is_numeric($term)) {
						// TODO: how to cast to a number type in sparql?
						return "($dtVar $mode '" . $this->escapeSparqlLiteral($term) . "')";
					} else {
						return "(STR($dtVar) $mode " . $this->escapeSparqlLiteral($term) . ')';
					}
				case 'CONTAINS':
					// contains behaves like regex but we also have to escape the special
					// regex chars
					$term = $search['term'];
					return "(REGEX(STR($dtVar), '" . $this->escapeSparqlRegex($term, TRUE) . "'))";
				case 'REGEX':
					$term = $search['term'];
					return "(REGEX(STR($dtVar), '" . $this->escapeSparqlLiteral($term) . "'))";

				default:	
					throw new InvalidArgumentException("Unknown search operator: $mode");
			}

		}

		return '';

	}
	
	
	/** Computes the inverse of a property
	*	@param prop the property
	* @return the inverse or NULL if there is none. 
	*   In case of a symmetric property the property itself is returned
	* @author Martin Scholz
	*/
	public function getInverse($prop) {
    // TODO
		return NULL;
	}


	/** Escapes a string according to http://www.w3.org/TR/rdf-sparql-query/#rSTRING_LITERAL.
	* @param literal the literal as a string
	* @param escape_backslash if FALSE, the pattern will not escape backslashes.
	*		This may be used to prevent double escapes
	* @return the escaped string
	* @author Martin Scholz
	*/
	public function escapeSparqlLiteral(string $literal, $escape_backslash = TRUE) {
	  $sic  = array("\\",   '"',   "'",   "\b",  "\f",  "\n",  "\r",  "\t");
	  $corr = array($escape_backslash ? "\\\\" : "\\", '\\"', "\\'", "\\b", "\\f", "\\n", "\\r", "\\t");
  	$literal = str_replace($sic, $corr, $literal);
  	return $literal;
	}


	/** Escapes the special characters for a sparql regex.
	* @param regex the pattern as a string
	* @param also_literal if TRUE, the pattern will also go through @see escapeSparqlLiteral
	* @return the escaped string
	* @author Martin Scholz
	*/
	public function escapeSparqlRegex(string $regex, $also_literal = FALSE) {
		//  $chars = "\\.*+?^$()[]{}|";
	  $sic = array('\\', '.', '*', '+', '?', '^', '$', '(', ')', '[', ']', '{', '}', '|');
  	$corr = array('\\\\', '\.', '\*', '\+', '\?', '\^', '\$', '\(', '\)', '\[', '\]', '\{', '\}', '\|');
  	$regex = str_replace($sic, $corr, $regex);
		return $also_literal ? $this->escapeSparqlLiteral($regex) : $regex;
	}
		
  




  

}
