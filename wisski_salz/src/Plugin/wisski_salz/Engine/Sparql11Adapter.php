<?php

/** @TODO This is the legacy adapter for sparql 11
* It must be eventually converted into a plugin/module/entity [type|bundle]/whatever
* 
* This class wraps a sparql endpoint client and provides
* the functionality and api necessary for WissKI to read and write data
* to a triple store
*/

class SPARQL11Adapter {
# currently we don't inherit from the interface
# this will be subject to chance anyway!
  
#class SPARQL11Adapter implements AdapterInterface {

  // *** MEMBER VARIABLES *** //
  

  /**
  * The following settings are currently supported:
  *
  * query_endpoint: The URL to connect to for read operations
  * update_endpoint: The URL to connect to for write operations
  */
  protected $settings = [];

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
	
  
  
  // *** CONSTRUCTORS *** //

  public function __construct(array $settings) {
    
		$this->setSettings($settings);

  }
	


  // *** PROTECTED MEMBER FUNCTIONS *** //


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
			$this->endpoint = new WissKI_Sparql_Client($this->settings['query_endpoint'], $this->settings['update_endpoint']);
#			$this->endpoint = new EasyRdf_Sparql_Client($this->settings['query_endpoint'], $this->settings['update_endpoint']);
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
	


	// 
  // getters, setters, and administrative stuff	
	// 
		
	
	/** Defined in AdapterInterface
  */
  public function getName() {
    return get_class($this);
  }

	/** Defined in AdapterInterface
  */
  public function getType() {
    return "SPARQL 1.1";
  }

  
  /** Either update one setting or replace all settings
  * @param name either a string or integer representing to name of the setting
  *         to be updated 
  *         OR an array with the new settings
  * @param when updating a single setting, the value of this setting
  */
  public function setSettings($name, $value = NULL) {
dpm(func_get_args()+array('this'=>$this),__FUNCTION__);
    if (is_array($name)) {
      $this->settings = $name;
    } elseif (is_string($name) || is_integer($name)) {
      $this->settings[$name] = $value;
    }
    if (isset($this->settings['query_endpoint']) && !isset($this->settings['update_endpoint'])) {
      // if update url is not given, suppose it is identical with query url
      $this->settings['update_endpoint'] = $this->settings['query_endpoint'];
    }
  }

  
  /** Return one or all settings
  * @param name the name of the setting to be returned
  * @return the value of the setting or an array of all settings if name is 
  *           NULL
  */
  public function getSettings($name = NULL) {
    if ($name === NULL) return $this->settings;
    return $this->settings[$name];
  }
  

  /** Magic getter */
  public function __get($name) {
    
    return isset($this->settings[$name]) ? $this->settings[$name] : FALSE;
  }
  

  /** magic setter */
  public function __set($name,$value) {
    // use setSettings() to handle special treatment
    $this->setSettings($name, $value);
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
		
  



		


  // *** LEGACY/UNPORTED MEMBER FUNCTIONS, DO WE NEED THEM? *** //

	



  /**
  * Performs a SPARQL 1.1 query or update.
  * The SPARQL query endpoint must be set in $this->settings['query_endpoint']
  * @param $query The SPARQL 1.1 query or update as a string
  * @return list($ok,$results)
  * returns a list consisting of
  * a boolean value $ok that is true iff the query was correctly performed
  * and the result list $results as an assocative array containing result rows as arrays keyed by the variable from the query
  * @author domerz
  */
  private function requestSPARQL($type,$query = NULL) {

    $ok = FALSE;
    $results = array();
    try {
      if (TRUE) {#easyrdf()) {
//        wisski_core_tick('SPARQLAdapter: begin query '.$requestcount);
        $results = $this->request($type,$query);
//        wisski_core_tick('SPARQLAdapter: end query '.$requestcount);
        if (get_class($results) == 'EasyRdf_Sparql_Result' && $results->numRows() > 0) {
          //this is as expected
          return array(TRUE, $results);
        } elseif (get_class($results) == 'EasyRdf_Graph') {
          return array(TRUE, $results);
        } else {
          return array(TRUE,array());
        }
        $ok = TRUE;
      } else trigger_error("EasyRdf is not installed",E_USER_ERROR);
    } catch (Exception $e) {
//      watchdog('wisski_SPARQL_'.$type.'_fail',"Request: ".htmlentities($query)."\nError Message: ".get_class($e)."\n".$e->getMessage());
      if (TRUE) {#variable_get('wisski_throw_exceptions',FALSE)) {
        $trace = debug_backtrace();
        $i = 0;
        $subtrace = array();
        while (!empty($trace) && $i < 5) {
          $tr = array_shift($trace);
          if ($i > 1) $subtrace[] = $tr;
          $i++;
        }
echo "Hallo";
echo $e->getMessage();
echo ($e->getTraceAsString());
//        dpm($subtrace);
//        throw $e;
      }
    }
//    drupal_set_message("SPARQL1.1 $type request successfull.<br>Query was '".htmlentities($query)."'");
    
    return array($ok,$results);
  }
  
  private function updateNamespaces() {
    $spaces_set = &drupal_static(__FUNCTION__);
    if (!isset($spaces_set) || !$spaces_set) {
//      trigger_error("updating Namespaces",E_USER_NOTICE);
      $spaces_set = FALSE;
      $db_spaces = db_select('wisski_salz_sparql11_ontology_namespaces','ns')
                ->fields('ns')
                ->execute()
                ->fetchAllAssoc('short_name');
      foreach ($db_spaces as $space) {
        EasyRdf_Namespace::set($space->short_name,$space->long_name);
      }

      $spaces_set = TRUE;
    }
  }
  
  private function putNamespace($short_name,$long_name) {
    $result = db_select('wisski_salz_sparql11_ontology_namespaces','ns')
                ->fields('ns')
                ->condition('short_name',$short_name,'=')
                ->execute()
                ->fetchAssoc();
    if (empty($result)) {
      db_insert('wisski_salz_sparql11_ontology_namespaces')
        ->fields(array('short_name' => $short_name,'long_name' => $long_name))
        ->execute();
    } else {
      if(!empty($short_name) && !empty($long_name)) {
        //ddebug_backtrace();
        db_update('wisski_salz_sparql11_ontology_namespaces')
          ->fields(array('long_name' => $long_name))
          ->condition('short_name', $short_name, '=')
          ->execute();
        drupal_set_message('Warning: Namespace '.$short_name.' already exists in DB','warning');
      }
    }
  }
  
  public function getNamespaces() {
    $ns = array();
    $db_spaces = db_select('wisski_salz_sparql11_ontology_namespaces','ns')
                ->fields('ns')
                ->execute()
                ->fetchAllAssoc('short_name');
    foreach ($db_spaces as $space) {
      $ns[$space->short_name] = $space->long_name;
    }
    
    return $ns;
  }


  /**
   * @param starting_concept string representing an concept, common start for all given paths
   *
   * @param paths is an array of associative arrays that may contain
   * the following entries:
   * $key		| $value
   * ------------------------------------------------------------
   * 'path_array' 	| array of strings representing owl:ObjectProperties 
   *                    | and owl:Classes in alternating order
   * 'datatype_property'| string representing an owl:DatatypeProperty
   * 'required'		| boolean, TRUE if the path must have to return
   *			| at least one data result
   * 'maximum'		| int specifying the maximum number of returned
   *			| data values for this path, unlimited if unspecified
   * 'id'		| ID of the path, matching the key of the result data
   *			| in the output array e.g. 'value' for the 'value'-key
   *			| in a text-field-info-array
   *
   * @param options is an associative array that may contain the following 
   * entries:
   * $key 		| $value
   * ------------------------------------------------------------
   * 'limit'		| int setting the SPARQL query LIMIT
   * 'offset'		| int setting the SPARQL query OFFSET
   * 'order'		| string containing 'ASC' or 'DESC' (or 'RAND')
   * 'qualifier'	| SPARQL data qualifier e.g. 'STR'
   * 'matches'		| array of strings, at least one of the data must match
   * 'uris'		| array of strings representing owl:Individuals on which the
   *			| query is triggered
   * 'count'            | int indicating the concept which should be counted -> query 
   *                    | is a count query for paging.
   */
  public function query($starting_concept,array $paths,array $options = array()) {  
  
//    dpm(func_get_args(),__FUNCTION__);

    // if there is no starting concepst - die.
    if (empty($starting_concept)) {
      //ddebug_backtrace();
      throw new InvalidArgumentException('you must specify a starting concept');
    }
    
    // everything starts with select distinct
    $query = "SELECT DISTINCT";
    
    // @TODO: why !isset here? I don't get it.
    // if there are not any uris in the options or if there are uris, 
    // then we are searching for an individual
		// Martin's explanation: the complement of the if test is the
		// case where we have exactly one uri given. i.e. we can leave out
		// ?ind as it can be directly bound!
    if (!isset($options['uris']) || count($options['uris']) > 1) $query .= " ?ind";
        
    // array for the data parts
    $datas = array();
    
    // if count is in the path we make a count query now.
    if (isset($options['count'])) {
      $query .= " count(?cnt)";
    }
    
    // add per path in the paths array an element for ?data and for ?target
    for ($i = 0; $i < count($paths); $i++) {
      $datas[] = "?data$i";
      $query .= " ?data$i";
      $query .= " ?tar$i";
    }
    
    // add a where
    $query .= " WHERE {";
    
    $ind = "?ind";
    $single_ind = FALSE;
    
    // if there are any uris
    if (!empty($options['uris'])) {
      // if there are more than one we add all values to the query
      if(count($options['uris']) > 1) $query .= " VALUES ?ind {<".implode('> <',$options['uris']).">}";
      // if there is exactly one we add it
      else {
        $ind = current($options['uris']);
        // if the one is an empty uri we die here.
        if (trim($ind) === '') {
          //ddebug_backtrace();
          throw new InvalidArgumentException('empty URI given');
        }
        // now we know that we just search for one uri
        $single_ind = TRUE;
      }
    }
    
    // add the individual as a starting concept
    $query .= " <$ind> rdf:type <$starting_concept> .";
    $i = 0;
    $ids = array();

    // go through the parts of $paths
    foreach($paths as $key => $path) {
    
      // if there is an array of ids in it, we get them
      if (isset($path['id'])) $ids[$i] = $path['id'];
      //$this->settings['query_endpoint'] @TODO this seems to be a little bit optimistic - shouldn't we check for numeric?
      // if there is no id in it, it is probably the key number of a path.
      else $ids[$i] = $key;
      
      // if there is no path array we die - however the empty array is ok?!
      // @TODO this should be documented above!!!!
      if (!isset($path['path_array']) || !is_array($path['path_array'])) {
        dpm($path,'wrong path');
        //ddebug_backtrace();
        throw new Exception('path_array');
      }
      
      // we get the path array
      $path_array = $path['path_array'];

      // we get the datatype-property
      $datatype_property = $path['datatype_property'];
      // @TODO if there is no datatype property we also should die.
      
      $count = 0;

      // we get the disamb for this path if there is no disamb we take the whole thing
      // @TODO: This is probably false. We should not automatically disamb on the last step of the path
      $disamb = isset($path['disamb']) && $path['disamb'] > 0 ? $path['disamb'] : count($path_array);
      $disamb = floor($disamb/2);
      
      // if there are several paths or if there is a maximum add an optional
      // @TODO: I don't get this either... check for correctness!
      if (count($paths) > 1 || (isset($path['maximum']) && $path['maximum'] > 0)) {
        $query .= " OPTIONAL {SELECT DISTINCT ".($single_ind ? '' : "?ind")." ?data$i ?tar$i WHERE {";
      }
      
      // shouldn't that be stopped by the code above?      
      // if there is no path array
      if (empty($path_array)) {
        // but there is a datatype property
        if (!empty($datatype_property)) {
          // why this binding? 
          $query .= " BIND($ind AS ?tar$i)";
          $query .= " <$ind> <$datatype_property> ?data$i .";
        }
      // if there is a path
      } else {
        $switch = FALSE;
        
        // if there are some paths
        // @TODO: never destroy something you got from the outside... this is stupid re-use of code
        // change!
        while(!empty($path_array)) {
          // what the fucking hell ?!
          // @TODO: Explain Mark what this should do... I don't get it.
          if ($switch = !$switch) {
            $query .= ($count == 0) ? "<$ind> " : "?p".$i."c$count ";
            $query .= "<" . array_shift($path_array) . ">";
            $count++;
            $query .= " ?p".$i."c$count. ";
          } else {
            $concept = array_shift($path_array);
//          $query .= " ?$count rdf:type/rdfs:subClassOf* ".array_shift($path_array).". ";
            $query .= " ?p".$i."c$count rdf:type <".$concept."> . ";
            if ($count == $disamb) {
              $query .= " BIND(?p".$i."c$count AS ?tar$i)";
            }
          }
        }
        
        // if it is a count query
        
        if(isset($options['count'])) {
          $query .= " BIND(?p".$i."c" . $options['count'] . " AS ?cnt)";
        }

        // if there is a datatype property
        if (!empty($datatype_property)) {
          // add it
          $query .= " ?p".$i."c$count <$datatype_property> ?data$i .";  
        } else {
          // if not the searched data is the last step
          // this also is a stupid assumption.
          $query .= " BIND(?p".$i."c$count AS ?data$i)";
        }
      }
      
      // if there are several paths we have to close the brackets because there will be more coming
      if (count($paths) > 1 || (isset($path['maximum']) && $path['maximum'] > 0)) {
        $query .= "}";
				if (isset($path['maximum']) && $path['maximum'] > 0) $query .=  " LIMIT ".$path['maximum'];
        $query .= " }";//close sub-SELECT
        if (isset($path['required']) && $path['required']) $query .= " FILTER(BOUND(?data$i))";
      }
      $i++;
    }
    if (isset($options['matches'])) {
      foreach ($options['matches'] as $match) {
        if (!empty($datas)) {
          $contains = array();
          foreach ($datas as $data) {
            $contains[] = "CONTAINS($data,\"$match\")";
          }
          $filter = " FILTER(".implode(' || ',$contains).")";
//          dpm($filter,'FILTER');
          $query .= $filter;
        }
      }
    }
    $query .= " }"; // close WHERE
    if (isset($options['order']) && !empty($datas)) {
      $query .= " ORDER BY";
      foreach($datas as $data) {
        $query .= " DESC(BOUND($data)) ";
        if (isset($options['qualifier'])) $data = $options['qualifier']."($data)";
        $query .= $options['order']."($data)";
      }
    }
    if (isset($options['limit']) && $options['limit'] > 0) {
      $query .= " LIMIT ".$options['limit'];
    }    
    if (isset($options['offset'])) {
      $query .= " OFFSET ".$options['offset'];
    }
//    wisski_core_tick('start PB query');
//    throw new Exception('STOP');
    list($ok,$result) = $this->querySPARQL($query);
//    wisski_core_tick('end PB query');
    if ($ok) {
      //generate output array, key order is:
      //[individual uri][target uri][data][0..?]
      $out = array();      
      foreach ($result as $obj) {
        if (!$single_ind) $ind = $obj->ind->dumpValue('text');
        if (!isset($out[$ind])) {
          $out[$ind] = array();
        }
        //make sure we have a numerically keyed output array for every target individual
        $deltas = array();
        for ($i = 0; $i < count($paths); $i++) {
          if(property_exists($obj,'tar'.$i)) {
            $target_uri = $obj->{'tar'.$i}->dumpValue('text');
            $key = array_search($target_uri,$deltas);
            if ($key === FALSE) {
              $key = count($deltas);
              $deltas[] = $target_uri;
            }
            if (!isset($out[$ind][$key])) $out[$ind][$key] = array();
            if (property_exists($obj,'data'.$i)) {
              $obj_data = $obj->{'data'.$i};
              if (method_exists($obj_data,'getValue')) $value = $obj_data->getValue();
              else $value = $obj_data->dumpValue('text');
//WATCH OUT:	here we assume to have at most one entry per target individual
              $out[$ind][$key][$ids[$i]] = $value;
              //hack to have the target_uri in the answer set
              //remember we cannot save this as the key, because drupal needs
              //numerically keyed $delta
              $out[$ind][$key]['target_uri'] = $target_uri;
            }
          }
        }
      }
//      dpm(func_get_args()+array('query' => $query,'result' => $out),__FUNCTION__);
      return $out;
    }
    return FALSE;
  }


	
  /**
   * Function for counting and paging
   * @params  $starting_concept starting concept of the path
   *          $path_array if we count through a path we use that 
   *          $datatype_property exactly that
   *          $countposition the position in the array that should be used for counting
   * @author Mark Fichtner
   */
  public function pbQueryCount($starting_concept, $path_array, $datatype_property, $countposition) {

    $settings = array();
    $settings['count'] = $countposition;
      
    $path = array();
    $path['datatype_property'] = $datatype_property;
    $path['path_array'] = $path_array;
       
    return $this->pbQuery($starting_concept, $path, $settings);
  }
  
  public function pbQuerySingle($individual_uri,$starting_concept,$path_array,$datatype_property,$limit = NULL,$offset = 0,$order = FALSE,$asc = TRUE,$qualifier = 'STR') {

    $path = array();
    if (isset($path_array) || isset($datatype_property)) {
      $path = array(
        array(
          'path_array' => $path_array,
          'datatype_property' => $datatype_property,
        ),
      );
    }
    $settings = array();
    if (isset($limit)) $settings['limit'] = $limit;
    if ($offset > 0) $settings['offset'] = $offset;
    if ($order) {
      $settings['order'] = $asc ? 'ASC' : 'DESC';
    }
    $settings['qualifier'] = $qualifier;
    $settings['uris'] = array($individual_uri);
    return $this->pbQuery($starting_concept,$path,$settings);
  }

  public function pbQueryAll($starting_concept,$path_array,$datatype_property,$limit = NULL,$offset = 0,$order = FALSE,$asc = TRUE,$qualifier = 'STR') {

    $path = array();
    if (isset($path_array) || isset($datatype_property)) {
      $path = array(
        array(
          'path_array' => $path_array,
          'datatype_property' => $datatype_property,
        ),
      );
    }
    $settings = array();
    if (isset($limit)) $settings['limit'] = $limit;
    if ($offset > 0) $settings['offset'] = $offset;
    if ($order) {
      $settings['order'] = $asc ? 'ASC' : 'DESC';
    }
    $settings['qualifier'] = $qualifier;
    return $this->pbQuery($starting_concept,$path,$settings);
  }

  public function pbQuerySingleMultipath($individual_uri,$starting_concept,$paths) {
    $settings['uris'] = array($individual_uri);
    return $this->pbQuery($starting_concept,$paths,$settings);
  }
  
  public function pbQueryMultiPath($starting_concept,$paths,$limit = NULL,$offset = 0,$order = FALSE,$asc = TRUE,$qualifier = 'STR',$contained_strings = array()) {
  
    $settings = array();
    if (isset($limit)) $settings['limit'] = $limit;
    if ($offset > 0) $settings['offset'] = $offset;
    if ($order) {
      $settings['order'] = $asc ? 'ASC' : 'DESC';
    }
    $settings['qualifier'] = $qualifier;
    if(!empty($contained_strings)) $settings['matches'] = $contained_strings;    
    return $this->pbQuery($starting_concept,$paths,$settings);
  }


  public function pbTitleQuery($uris,$starting_concept,$paths) {

    $settings = array('uris' => $uris);
    return $this->pbQuery($starting_concept,$paths,$settings);
  }

  public function pbMultiQuery(array $individual_uris,$starting_concept,$path_array,$datatype_property,$single_result = FALSE) {
    
    $path = array();
    if (isset($path_array) || isset($datatype_property)) {
      $path = array(
        array(
          'path_array' => $path_array,
          'datatype_property' => $datatype_property,
        ),
      );
    }
    if ($single_result) $path['maximum'] = 1;
    $settings = array('uris' => $individual_uris);
    return $this->pbQuery($starting_concept,$path,$settings);
  }


  /**
   * generates and executes a SPARQL query to ensure the existence of a path
   * of the given path template structure ($path_array) going from $starting_individual_uri
   * to $target_individual_uri. The $disamb parameter states where to cut the
   * path_array, i.e. holds the $path_array index of the target_individuals class
   * @return an array holding the disamb class and the rest of the path array
   * to be used in another pbQuery or pbUpdate, or FALSE if the path between
   * the uris does not exist
   */
  private function pbTwoEndsQuery($starting_individual_uri,$starting_concept,$path_array,$disamb,$target_individual_uri) {
  
    $query = "ASK { <$starting_individual_uri> a <$starting_concept> .";
    $count = 0;
    $pos = 0;
    $switch = FALSE;
    while ($pos + 1 < $disamb && !empty($path_array)) {
      
      $pos++;
    }
    if (empty($path_array)) return FALSE;
    $query .= (($count > 0) ? " ?c$count " : " <$starting_individual_uri> ").array_shift($path_array)." <$target_individual_uri> .";
    if (empty($path_array)) return FALSE;
    $target_concept = array_shift($path_array);
    $query .= " <$target_individual_uri> a <$target_concept> .";
    $query .= "}";
    list($ok,$result) = $this->querySPARQL($query);
    $dump = array('query'=>$query);
    if ($ok) {
      var_dump($result);
//      $dump += array('OK' => 'TRUE','result' => $result->getBoolean() ? 'TRUE' : 'FALSE');
    } else $dump += array('OK' => 'FALSE');
    dpm($dump,__METHOD__);
    if ($ok) {
      return array($target_concept,$path_array);
    }
  }

  private function getGraphName($type = '') {
    
    $graph_name = &drupal_static(__METHOD__.$type);    
    if (!empty($graph_name)) return $graph_name;
    global $base_url;
    $graph_name = variable_get('wisski_graph_name'.$type,$base_url.'/wisski_graph'.$type);

    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE{ GRAPH <$graph_name> {?s ?p ?o}} LIMIT 1");
    if ($ok) {
      if (empty($result)) {
        $this->updateSPARQL("CREATE GRAPH <$graph_name>");
        variable_set('wisski_graph_name'.$type,$graph_name);
      }
    }

    return $graph_name;
  }

  /**
   * this function generates a fully new path for the given template
   * i.e. it generates new owl:Individuals for every concepts on the path
   * and connects them via the given properties
   * @return a list of all newly introduced individual uris grouped by class
   * and the uri of the last individual on the path stated specially
   */
  private function pbGenerate($individual_uri,$starting_concept,$path_array,$target_uri=NULL) {
    
//    dpm(func_get_args(),__METHOD__);
    
    $graph_name = $this->getGraphName();
    // check and if neccessary insert individual information
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { GRAPH ?g { <$individual_uri> rdf:type/rdfs:subClassOf* <$starting_concept>. } }");
    if ($ok && empty($result)) {
      $query = "INSERT{"
        ."GRAPH <$graph_name> {"
          ." <$individual_uri> rdf:type <$starting_concept> ."
          ." <$individual_uri> rdf:type owl:Individual ."
        ."}"
      ."} WHERE {?s ?p ?o .}";
      list($ok,$result) = $this->updateSPARQL($query);
      if (!$ok) return FALSE;
    }
    $new_individuals = array();
    $switch = FALSE;
    $individual = $individual_uri;
    $class = $starting_concept;
    // we introduce a new owl:Individual for every individual on the path
    while(!empty($path_array)) {
      if ($switch = !$switch) {
        //even steps are properties
        $property = array_shift($path_array);
        if (!empty($target_uri) && count($path_array) === 1) {
          $new_individual = $target_uri;
        } else {
          $new_individual = $this->createNewIndividual(strstr($property,':'));
        }
        list($ok,$result) = $this->updateSPARQL("INSERT {GRAPH <$graph_name> { <$individual> <$property> <$new_individual> .}} WHERE { ?s ?p ?o .}");
        if(!$ok) {
          trigger_error("Errors while inserting data: ",E_USER_ERROR);
          return FALSE;
        } else $individual = $new_individual;
      } else {
        //odd steps are classes
        $class = array_shift($path_array);
        list($ok,$result) = $this->updateSPARQL("INSERT {GRAPH <$graph_name> { <$individual> rdf:type <$class> . <$individual> rdf:type owl:Individual .}} WHERE {?s ?p ?o .}");
        if(!$ok) {
          trigger_error("Errors while inserting data: ",E_USER_ERROR);
          return FALSE;
        } else $new_individuals[$class][] = $individual;
      }
    } //END while(!empty($path_array))
    //returns set of newly introduced uris
    return array($new_individuals,$individual);
  }
  
  /**
   * this updates the triple store according the given path template, disamb
   * and data
   * we assume the class and individual at the disamb position in the path
   * array to be the last point to keep and cut the path behind that class
   * negative disambs are allowed and are then taken from the end of the template
   * i.e. $disamb = -1 means we take the last concept in the line as disamb
   * $disamb = FALSE overrides everything i.e. the starting concept is the
   * disambiguation point
   */
  public function pbUpdate($individual_uri,$individual_name,$starting_concept,$path_array,$datatype_property,$disamb,$new_data,$delete_old,$target_uri=NULL) {
  
    variable_set('wisski_throw_exceptions',TRUE);
//    dpm(func_get_args(),__METHOD__);
    
    if (is_array($new_data)) {
      $inds = array();
      foreach($new_data as $nd) {
        $new_inds = $this->pbUpdate($individual_uri,$individual_name,$starting_concept,$path_array,$datatype_property,$disamb,$nd,$delete_old,$target_uri);
        if ($new_inds !== FALSE)
          $inds += $new_inds;
        else return FALSE;
      }
      return $inds;
    }
    
    $graph_name = $this->getGraphName();
    
    // check and if neccessary insert individual information
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { GRAPH ?g { <$individual_uri> rdf:type/rdfs:subClassOf* <$starting_concept>. } }");
    if (!$ok) {
      trigger_error("Errors while inserting data: ",E_USER_ERROR);
    }
    elseif (empty($result)) {
      $query = "INSERT{"
        ."GRAPH <$graph_name> {"
          ." <$individual_uri> rdf:type <$starting_concept> ."
          ." <$individual_uri> rdf:type owl:Individual ."
        ."}"
      ."} WHERE {?s ?p ?o .}";
      list($ok,$result) = $this->updateSPARQL($query);
      if (!$ok) return FALSE;
    }

    if (!empty($disamb)) {
      if ($disamb < 0) $disamb = count($path_array) + $disamb;
      // we walk through the path template until we reach the disambiguation point
      // or end up earlier in the path
      $switch = FALSE;
      $individuals = array($individual_uri);
      $concept = $starting_concept;
      $count = 0;
      $pos = 0;
      while(!empty($path_array)) {
        $property = array_shift($path_array);
        if (!empty($path_array)) {
          $pos++;
          //for the rollback
          $old_concept = $concept;
          $concept = array_shift($path_array);
        } else {
          trigger_error("Errors while inserting data: ",E_USER_ERROR);
        }
        $query = "SELECT DISTINCT ?target_uri WHERE {"
          ."VALUES ?ind {".implode(' ',$individuals)."} "
          ."?ind <$property> ?target_uri. "
          ."?target_uri a <$concept> . "
        ."}";
        list($ok,$result) = $this->querySPARQL($query);
        if ($ok) {
          if (!empty($result)) {
            $individuals = array();
            foreach ($result as $obj) {
              $individuals[] = $obj->target_uri->dumpValue('text');
            }
          } else {
            //there has been no value for the path up to now,
            //=> rollback
            array_unshift($path_array,$concept);
            array_unshift($path_array,$property);
            $concept = $old_concept;
            break;
          }
        } else {
          trigger_error("Errors while inserting data: ",E_USER_ERROR);
        }
        if ($pos == $disamb) break;
        $pos++;
      }
//      dpm($individuals,'computed targets');
      $starting_uri = $individuals[0];
      
      // we now have $concept to be the class at the disambiguation point
      // and $target_uri the last valid uri in the path
      // additionally $path_array only holds the rest of the input $path_array
      // that must now be built new
      
      list($new_individuals,$individual) = $this->pbGenerate($starting_uri,$concept,$path_array,$target_uri);    
    } else {//i.e. $disamb === NULL || $disamb === FALSE || $disamb === 0
      list($new_individuals,$individual) = $this->pbGenerate($individual_uri,$starting_concept,$path_array,$target_uri);
    }
    
    if (isset($datatype_property)) {
      if ($delete_old) {
      list($ok,$result) = $this->updateSPARQL("DELETE WHERE {GRAPH <$graph_name> { <$individual> <$datatype_property> ?data.}}");
        if (!$ok) {
          trigger_error("Errors while inserting data: ",E_USER_ERROR);
          return FALSE;
        }
      }
      $insertion = $this->escape_sparql_literal($new_data);
      $insertion = utf8_decode($insertion);
      list($ok,$result) = $this->updateSPARQL("INSERT {GRAPH <$graph_name> { <$individual> <$datatype_property> \"$insertion\" .}} WHERE {?s ?p ?o .}");
      if (!$ok) {
        trigger_error("Errors while inserting data: ",E_USER_ERROR);
        return FALSE;
      }
    }
    //returns set of newly introduced uris
    variable_del('wisski_throw_exceptions');
    return $new_individuals;
  }
 
 
  /**
   * This function inserts individual data
   * @param $entity_uri an absolute non-namespaced entity uri
   * @param $bundle_uri an absolute non-namespaced bundle uri
   * @param $comment an rdfs:comment
   *
   * The entity_uris are absolute uris... they are not wrapped in <>!
   * so we have to wrap them here.
   */
  public function insertIndividual($entity_uri,$bundle_uri,$comment = FALSE) {
    
    $graph_name = $this->getGraphName();
    // with <>
    $insert_string = "<$entity_uri> a <$bundle_uri> .";
    $insert_string .= "<$entity_uri> a owl:Individual .";
    
    // escape comment and go through it - check for any special characters which should not be there
    // @TODO: @CHECK: escape!
    $insert_string .= $comment ? "<$entity_uri> rdfs:comment '$comment' ." : '';
    drupal_set_message(htmlentities("INSERT {GRAPH <$graph_name> { $insert_string }} WHERE {?s ?p ?o.}"));
    list($ok,$result) = $this->updateSPARQL("INSERT {GRAPH <$graph_name> { $insert_string }} WHERE {?s ?p ?o.}");
    return $ok;
  }

  // works only with absolute uris!
  public function uriExists($uri) {
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE {{ <$uri> ?p ?o .} UNION {?s ?p <$uri> .}} LIMIT 1");
    return $ok && !empty($result);
  }

  public function createNewIndividual($name_part = '', $placeholders = array()) {

    global $base_url;    
    $prefix = variable_get('wisski_core_lod_prefix', $base_url);
    $templates = variable_get('wisski_core_lod_uri_templates', array('' => $base_url . '/inst/%{bundle}/%{hash}'));
    $template = isset($templates[$name_part]) ? $templates[$name_part] : $templates[''];
    $name_part = trim(substr(preg_replace('/[^a-zA-Z0-9_]+/u','_',$name_part),0,32),'_');
    

    //ensure uniqueness
    $placeholders['number'] = 0;
    do {
      $placeholders['hash'] = md5(time() . rand());
      $placeholders['number']++;
      $ind_uri = $template;
      $ind_uri = wisski_core_fill_template_string($template, $placeholders);
#      $ind_uri = '<'.wisski_core_fill_template_string($template, $placeholders).'>';
//      $ind_uri = '<'.$prefix . '/inst/' . $name_part . '_' . $hash.'>';
    } while ($this->uriExists($ind_uri));
    watchdog('wisski sushi mako uri', 'input: '.htmlentities(implode(', ',func_get_args())).'<br>output: '.htmlentities($ind_uri));

//    wisski_core_lod_register_redirects($ind_uri);

    return $ind_uri;
  }

  // handle uris absolute!
  public function deleteAllTriples($uri) {
  
    $graph_name = $this->getGraphName();
    
    list($ok,$result) = $this->updateSPARQL("DELETE WHERE {GRAPH <$graph_name> {{ <$uri> ?p1 ?o1. } UNION {?s1 ?p2 <$uri> . } UNION {?s2 <$uri> ?o2}}");
    if (!$ok) {
      trigger_error("Errors while inserting data: ",E_USER_ERROR);
      return FALSE;
    }
    return $ok;
  }

  public function nextClasses($property,$property_after = NULL) {
  
    $query = 
      "SELECT DISTINCT ?class "
      ."WHERE { GRAPH ?g { "
        ."<$property> rdfs:subPropertyOf* ?r_super_prop. "
        ."?r_super_prop rdfs:range ?r_super_class. "
        ."FILTER NOT EXISTS { "
          ."?r_sub_prop rdfs:subPropertyOf+ ?r_super_prop. "
          ."<$property> rdfs:subPropertyOf* ?r_sub_prop. "
          ."?r_sub_prop rdfs:range ?r_any_class. "
        ."} "
        ."?class rdfs:subClassOf* ?r_super_class. ";
    if (isset($property_after)) {
      $query .= "<$property_after> rdfs:subPropertyOf* ?d_super_prop. "
        ."?d_super_prop rdfs:domain ?d_super_class. "
        ."FILTER NOT EXISTS { "
          ."?d_sub_prop rdfs:subPropertyOf+ ?d_super_prop. "
          ."<$property_after> rdfs:subPropertyOf* ?d_sub_prop. "
          ."?d_sub_prop rdfs:domain ?d_any_class. "
        ."} "
        ."?class rdfs:subClassOf* ?d_super_class. ";
    }
    $query .= "} }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $class = $obj->class->dumpValue('text');
        $output[$class] = $class;
      }
      natsort($output);
//      dpm($output,$query);
      return $output;   
    }
    return array();
  }
  
  private function simpleNextClasses($property,$property_after) {
    
  }
  
  public function nextPropertiesHierarchy($class,$class_after = NULL) {
    //old name, but no hierarchy anymore
    $query = 
      "SELECT DISTINCT ?property ?d_superclass "
      ."WHERE { GRAPH ?g { "
        ."?property a owl:ObjectProperty. "
        ."?property rdfs:domain ?d_superclass. "
        ."<$class> rdfs:subClassOf* ?d_superclass. "
      ;
    if (isset($class_after)) {
      $query .= 
        "?property rdfs:range ?r_superclass. "
        ."<$class_after> rdfs:subClassOf* ?r_superclass. "
      ;
    }
    $query .= "} }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      if (empty($result)) return array();
      $output = array();
      foreach ($result as $obj) {
        $prop = $obj->property->dumpValue('text');
        $superclass = $obj->d_superclass->dumpValue('text');
        if (isset($output[$prop])) {
          if (!in_array($superclass,$output[$prop])) {
            $output[$prop][] = $superclass;
          }
        } else {
          $output[$prop] = array($superclass);
        }
      }
      array_walk($output,'sparql11_adapter_makestring');
      uksort($output,'strnatcasecmp');
      return $output;
    }
    return array();
  }
  
  
  public function OLD_nextPropertiesHierarchy($class,$class_after = NULL) {
    
    $query = 
      "SELECT DISTINCT ?property ?d_superclass "
      ."WHERE { GRAPH ?g { "
        ."?property a owl:ObjectProperty. "
        ."?d_super_prop rdfs:domain ?d_superclass. "
        ."?property rdfs:subPropertyOf* ?d_super_prop. "
        ."$class rdfs:subClassOf* ?d_superclass. "
        ."FILTER NOT EXISTS { "
          ."?d_sub_prop rdfs:subPropertyOf+ ?d_super_prop. "
          ."?property rdfs:subPropertyOf* ?d_sub_prop. "
          ."?d_sub_prop rdfs:domain ?d_any_class. "
        ."} ";
    if (isset($class_after)) {
      $query .= 
        "?r_super_prop rdfs:range ?r_superclass. "
        ."?property rdfs:subPropertyOf* ?r_super_prop. "
        ."$class_after rdfs:subClassOf* ?r_superclass. "
        ."FILTER NOT EXISTS { "
          ."?r_sub_prop rdfs:subPropertyOf+ ?r_super_prop. "
          ."?property rdfs:subPropertyOf* ?r_sub_prop. "
          ."?r_sub_prop rdfs:range ?r_any_class. "
        ."} ";
    }
    $query .= "} }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      if (empty($result)) return array();
      $output = array();
      foreach ($result as $obj) {
        $prop = $obj->property->dumpValue('text');
        $output[$obj->d_superclass->dumpValue('text')][$prop] = $prop;
      }
      $keys = array_keys($output);
      if (!in_array($class,$keys)) $keys[] = $class.' ('.t('empty').')';
      natsort($keys);
      $real_output = array_fill_keys($keys,array());
      foreach($output as $key => $props) {
        natsort($props);
        $real_output[$key] = $props;
      }
//      dpm($real_output,$query);
      return $real_output;
    }
    return array();
  }
  
  public function nextDatatypeProperties($class) {
    
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT ?property "
      ."WHERE { GRAPH ?g { "
        ."?property a owl:DatatypeProperty. "
        ."?property rdfs:subPropertyOf*/rdfs:domain/(^rdfs:subClassOf)* <$class>. "
      ."} }"
    );
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $prop = $obj->property->dumpValue('text');
        $output[$prop] = $prop;
      }
      natsort($output);
//      dpm($output,'queried datatype props for '.$class);
      return $output;
    }
    return array();
  }
  
  public function nextSteps($node,$node_after = NULL) {
    
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT ?type "
      ."WHERE { GRAPH ?g {"
        ."<$node> a ?type."
        .(isset($node_after) ? " <$node_after> a ?type." : '')
      ."} }"
    );
    if ($ok) {
      foreach($result as $obj) {
        if ($obj->type->dumpValue('text') == 'owl:Class') return $this->nextPropertiesHierarchy($node,$node_after);
        if ($obj->type->dumpValue('text') == 'owl:ObjectProperty') return $this->nextClasses($node,$node_after);
      }
    }
    return array();
  }

  public function getClassesAndComments($entity_uri) {
  
    return array($this->getClasses($entity_uri),$this->getComments($entity_uri));
  }

  public function getAllTriplesForURI($uris) {
    
    if (is_array($uris)) {
      $uris = implode('> <',$uris);
    }
    $uris = '<' . $uris . '>';
    
    $query = "CONSTRUCT { ?s ?p ?o . } WHERE {
      VALUES ?x { $uris }
      {?x ?p ?o. BIND(?x AS ?s)}
      UNION {?s ?x ?o. BIND(?x AS ?p)}
      UNION {?s ?p ?x. BIND(?x AS ?o)}
      }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      return $result;
    } else return FALSE;
  }
  
  public function getComments($entity_uri) {
    
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { GRAPH ?g { <$entity_uri> rdfs:comment ?comment .} }");
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $out[] = $obj->comment->dumpValue('text');
      }
      return $out;
    }
    return FALSE;
  }
  
  public function getClasses($entity_uri=NULL) {
  
    if (isset($entity_uri)) {
      $query = "SELECT DISTINCT ?class WHERE { GRAPH ?g { <$entity_uri> rdf:type ?class .} }";
    } else {
      $query = "SELECT DISTINCT ?class WHERE { GRAPH ?g {"
        ." ?class a owl:Class."
      ."} }";  
    }
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      if (count($result) > 0) {
        $out = array();
        foreach ($result as $obj) {
          $class = $obj->class->dumpValue('text');
          $out[$class] = $class;
        }
        uksort($out,'strnatcasecmp');
        return $out;
      }
    }
    return FALSE;
  }

  public function getClassHierarchy($indexed_by_superclasses=FALSE) {
  
    $query = "SELECT DISTINCT * WHERE { GRAPH ?g {"
      ." ?sub rdfs:subClassOf ?super."
      ." ?super a owl:Class."
      ." FILTER NOT EXISTS {"
        ." ?sub rdfs:subClassOf ?subsuper."
        ." ?subsuper rdfs:subClassOf ?super."
      ." }"
    ."} }"
    ;
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok && !empty($result)) {
      $out = array();
      foreach ($result as $obj) {
        if ($indexed_by_superclasses) {
          $super = $obj->super->dumpValue('text');
          if (!isset($out[$super])) $out[$super] = array();
          $sub = $obj->sub->dumpValue('text');
          $out[$super][] = $sub;
        } else {
          $sub = $obj->sub->dumpValue('text');
          if (!isset($out[$sub])) $out[$sub] = array();
          $super = $obj->super->dumpValue('text');
          $out[$sub][] = $super;
        }
      }
      return $out;
    }
    return FALSE;
  }
  
  public function getIndsWithComments($class_uri) {
  
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { GRAPH ?g { ?ind rdf:type <$class_uri> . OPTIONAL {?ind rdfs:comment ?comment. }}}");
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $ind_uri = $obj->ind->dumpValue('text');
        if (!isset($out[$ind_uri])) $out[$ind_uri] = array();
        if (property_exists($obj,'comment')) $out[$ind_uri][] = $obj->comment->dumpValue('text');
      }
      return $out;
    }
    return FALSE;
  }
  
  public function getIndividuals($class_uri) {
    
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { GRAPH ?g { ?ind rdf:type <$class_uri> .} }");
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $out[] = $obj->ind->dumpValue('text');
      }
      return $out;
    }
    return FALSE;
  }
  
  public function doesClassExist($class_uri) {
    list($ok,$result) = $this->querySPARQL(
      "ASK {"
      ." { ?ind a <$class_uri> .}"
      ." UNION { <$class_uri> a ?class. ?class rdfs:subClassOf* owl:CLass.}"
      ."}"
    );
    if ($ok) {
      return $result[0]->getValue();
    }
    return FALSE;
  }
  
  public function getIndCount($class_uri = NULL) {
    
    if (empty($class_uri)) {
      list($ok,$result) = $this->querySPARQL("SELECT DISTINCT (COUNT(?ind) AS ?count) WHERE { GRAPH ?g { ?ind a/a ?type } }");
    } else {
      list($ok,$result) = $this->querySPARQL("SELECT DISTINCT (COUNT(?ind) AS ?count) WHERE { GRAPH ?g { ?ind a <$class_uri> .} }");
    }
    if ($ok) return current($result)->count->getValue();
    return FALSE;
  }
  
  public function getClassesWithIndCount() {
        
    list($ok,$result) = $this->querySPARQL(
      "SELECT ?class (COUNT(?ind) as ?count)"
      ." WHERE {"
        ."SELECT DISTINCT ?class ?ind "
        ."WHERE { GRAPH ?g {"
          ."?class rdfs:subClassOf ?topclass."
          ."?ind a ?class."
        ."} }"
      ."}"  
      ." GROUP BY ?class"
    ); 
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        if (!empty($obj->class)) $out[$obj->class->dumpValue('text')] = $obj->count->getValue();
      }
      return $out;
    }  
    return FALSE;
  }
  
  public function getMatchingInds($match,$limit=0) {
  
    $query = "SELECT DISTINCT ?ind ?class"
      ." WHERE { GRAPH ?g {"
        ." ?ind a ?class."
        ." ?class a ?type."
        ." FILTER(CONTAINS(STR(?ind),\"$match\"))"
      ."} }";
    if ($limit > 0) $query .= " LIMIT $limit";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      $out = array();
      foreach($result as $obj) {
        $uri = $obj->ind->dumpValue('text');
        $out[$obj->class->dumpValue('text')][$uri] = $uri;
      }
//      dpm(func_get_args()+array('query'=>$query,'result'=>$out),__FUNCTION__);
      return $out;
    }
    return FALSE;
  }                                                                              
  
  public function createEntitiesForBundle($bundle) {
    
    drupal_set_message("Creating entities for bundle");
    $bundle_label = $bundle->label;
    $bundle_uri = $bundle->uri;
    $now = time();
    $result = array();
    list($ok,$result) 
      = $this->querySPARQL(
        "SELECT DISTINCT ?ind "
        ."WHERE "
        ."{ GRAPH ?g {"
          ."<$bundle_uri> a owl:Class. "
          ."?ind a <$bundle_uri>. "
          ."FILTER NOT EXISTS { "
            ."?n (rdfs:subClassOf)+ <$bundle_uri>. "
            ."?ind a ?n. "
         ."} "
      ."} }"
//  	."LIMIT 1 "
    );
    $then = time();
    trigger_error("Query took ".$this->makeTimeString($then-$now)." seconds",E_USER_NOTICE);
    $now = time();
    if ($ok) {
      foreach($result as $obj) {
        if(isset($obj->ind)) {
          $ind = $obj->ind->dumpValue('text');
          $ind_pref = substr(strstr($bundle_label,':'),1,4);
          $ind_label = $ind;
          $ind_name = $this->makeDrupalName($ind_pref.$ind_label,'ind_');
          $query = new EntityFieldQuery();
          $query->entityCondition('entity_type', 'wisski_individual');
          $query->propertyCondition('name',$ind_name,'=');
          $results = $query->execute();            
          if (!empty($results)) continue;
          if (in_array($ind_name,$results)) continue;
          $info = array(
            'type' => $bundle->type,
            'name' => $ind_name,  
            'title' => $ind_label,
            'same_individuals' => array($ind_name),
            'timestamp' => 0, //ensures update on first view
          );
          $entity = entity_create('wisski_individual',$info);
          entity_save('wisski_individual',$entity);
        }
      }
    }
    $then = time();
    trigger_error("Rest of Setup took ".$this->makeTimeString($then-$now),E_USER_NOTICE);
  }
  
  public function createEntities() {
//dpm(func_get_args(),__METHOD__);    
    $now = time();
    $result = array();
    list($ok,$result) 
      = $this->querySPARQL(
        "SELECT DISTINCT ?ind ?class "
        ."WHERE "
        ."{ GRAPH ?g { "
          ."?class rdfs:subClassOf ?topclass. "
          ."?ind a ?class. "
          ."FILTER NOT EXISTS { "
            ."?n (rdfs:subClassOf)+ ?class. "
            ."?ind a ?n. "
         ."} "
      ."} }"
//  	."LIMIT 1 "
    );
    $then = time();
    trigger_error("Query took ".$this->makeTimeString($then-$now)." seconds",E_USER_NOTICE);
    $now = time();
    if ($ok) {
      $individuals = array();
      foreach($result as $obj) {
        if(isset($obj->ind) && isset($obj->class)) {
          $individuals[$obj->ind->dumpValue('text')][] = $obj->class->dumpValue('text');
        }
      }
      foreach ($individuals as $ind => $classes) {
        $same_inds = array();
        /*foreach ($classes as $class) {
          $class_label = $class;
          $ind_pref = substr(strstr($class_label,':'),1,4);
          $ind_label = $ind;
          $ind_name = $this->makeDrupalName($ind_pref.$ind_label,'ind_');
          $same_inds[] = $ind_name;
        }*/
        foreach ($classes as $class) {
          $class_label = $class;
          $class_name = $this->makeDrupalName($class_label,'');
          $ind_pref = substr(strstr($class_label,':'),1,4);
          $ind_label = $ind;
          $ind_name = $this->makeDrupalName($ind_pref.$ind_label,'ind_');
          $info = array(
            'type' => $class_name,
            'name' => $ind_name,  
            'title' => $ind_label,
            'uri' => $this->createNewIndividual(),
            'timestamp' => 0, //ensures update on first view
          );
          $entity = entity_create('wisski_individual',$info);
          entity_save('wisski_individual',$entity);
        if(count($same_inds) > 1) dpm($entity);
        }//foreach ... $class
      }//foreach ... $ind
    }
    $then = time();
    trigger_error("Rest of Setup took ".$this->makeTimeString($then-$now),E_USER_NOTICE);
  }
  

  public function updateEntityInfo(&$entity) {
  
    $ind_uri = $entity->uri;
    $entity_name = $entity->name;
    $gather = array();
    $result = array();
    list($ok,$result)
      = $this->querySPARQL(
        "SELECT DISTINCT ?property ?target "
        ."WHERE "
        ."{ GRAPH ?g { "
          ."?class rdfs:subClassOf ?topclass. "
          ."<$ind_uri> a ?class. "
          ."?property a owl:ObjectProperty. "
          ."<$ind_uri> ?property ?target. "
        ."} } "
      );
    if ($ok) {
      foreach($result as $obj) {
        if(isset($obj->property) && isset($obj->target))
          $gather['object'][$obj->property->dumpValue('text')][] = $obj->target->dumpValue('text');
      }
    }
    $result = array();
    list($ok,$result)
      = $this->querySPARQL(
        "SELECT DISTINCT ?property ?data "
        ."WHERE "
        ."{ GRAPH ?g { "
          ."?class rdfs:subClassOf ?topclass. "
          ."<$ind_uri> a ?class. "
          ."?property a owl:DatatypeProperty. "
          ."<$ind_uri> ?property ?data. "
        ."} } "
      );
    if ($ok) {
      foreach($result as $obj) {
        if(isset($obj->property) && isset($obj->data)) {
          $data = $obj->data->dumpValue('text');
          $data = preg_replace('/["\']/','',substr($data,0,strpos($data,'^^')));
          if (strlen($data) > 255) $data = substr($data,0,251)." ...";
          $gather['data'][$obj->property->dumpValue('text')][] = $data;
        }
      }
    }
    $wrapper = entity_metadata_wrapper('wisski_individual',$entity);
/*
        if (isset($gather['object'])) {
          foreach($gather['object'] as $prop => $values) {
            $object_property_name = $this->makeDrupalName($prop,'wsk_');
            foreach($values as $target) {
              $target_name = $this->makeDrupalName($ind_pref.$target,'ind_');
              $wrapper->$object_property_name->set($target_name);
            }
          }
        }
*/        
    if (isset($gather['data'])) {
      foreach($gather['data'] as $prop => $values) {
        $data_property_name = $this->makeDrupalName($prop,'wsk_');
        $type = $wrapper->$data_property_name->type();
        if($type == 'list<text>') {
          $wrapper->$data_property_name->set($values);  
        } elseif ($type == 'text') {
          $wrapper->$data_property_name->set(current($values));
        } else  trigger_error("Wrong metadata type $type for $data_property_name in $entity_name",E_USER_WARNING);
      }
    }
    $wrapper->save();
    $entity->timestamp = time();
    $entity->save();
  }

  public function updateClassInfo(&$class) {
/*  
    $fields = array();
    $instances = array();
    $class_name = $class->type;
    $class_label = $class->label;
    
    $now = time();
    list($ok,$result) = $this->querySPARQL(
      "SELECT ?property ?target "
      ."WHERE {"
        ."?property a owl:ObjectProperty. "
        ."{"
          ."{"																		//Object Properties with our class as a domain
            ."?property rdfs:domain $class_label. "	//may also be specified via their inverse having
            ."?property rdfs:range ?target. "			//it as range
          ."}"
          ." UNION "
          ."{"
            ."?p owl:inverseOf ?property. "
            ."?p rdfs:range $class_label. "
            ."?p rdfs:domain ?target. "
          ."}"
        ."}"
        ." UNION "
        ."{"												//here we ensure that forgotten range specifications
          ."?ind a $class_label. "		//are taken into account, too
          ."?ind ?property ?p2. "
        ."}"
      ."}"
    );
    $then = time();
    $query_time = $then - $now;
    if ($ok) {
      foreach($result as $obj) {
        if (isset($obj->target) && isset($obj->property)) {
          $target_label = $obj->target->dumpValue('text');
          $target_name = $this->makeDrupalName($target_label,'');
          $field_label = $obj->property->dumpvalue('text');
          $field_name = $this->makeDrupalName($field_label,'wsk_');
          if (!array_key_exists($field_name,$fields)) {
            $fields[$field_name] = array(
              'field_name' => $field_name,
              'type' => 'entityreference',
              'cardinality' => -1,
              'entity_types' => array('wisski_individual'),
              'settings' => array(
                'target_type' => 'wisski_individual',
                'handler_settings' => array(
                  'target_bundles' => array($target_name => $target_name),
                ),
              ),
            );
          } else {
            if (!array_key_exists($target_name,$fields[$field_name]['settings']['handler_settings']['target_bundles'])) {
              $fields[$field_name]['settings']['handler_settings']['target_bundles'][$target_name] = $target_name;
            }
          }
          if(!array_key_exists($class_name,$instances) || !array_key_exists($field_name,$instances[$class_name])) {
            $instances[$class_name][$field_name] = array(
              'field_name' => $field_name,
              'label' => t($field_label),
              'bundle' => $class_name,
              'entity_type' => 'wisski_individual',
              'widget' => array(
                'type' => 'options_select',
                'module' => 'options',
              ),
              'display' => array(
                'default' => array(
                  'label' => 'inline',
                  'type' => 'entityreference_label',
                  'module' => 'entityreference',
                  'settings' => array(
                    'link' => TRUE,
                  ),
                ),
              ),
            );
          }
        }//if(isset...
      }//foreach...
    }//if($ok)
    $now = time();
    $setup_time = $now - $then;
    list($ok,$result) = $this->querySPARQL(
      "SELECT ?property "
      ."WHERE {"
        ."?data a owl:DatatypeProperty."
        ."{"
        ."?data rdfs:domain/(^rdfs:subClassOf)* $class_label."
        ."}"
        ." UNION "
        ."{"
          ."?ind a $class_label. "
          ."?ind ?property ?p. "
        ."}"
      ."}"
    );
    $then = time();
    $query_time += $then - $now;
    if ($ok) {
      foreach($result as $obj) {
        if (isset($obj->property)) {
          $field_label = $obj->property->dumpValue('text');
          $field_name = $this->makeDrupalName($field_label,'wsk_');
          if (!array_key_exists($field_name,$fields)) {
            $fields[$field_name] = array(
              'field_name' => $field_name,
              'type' => 'text',
              'cardinality' => -1,
              'entity_types' => array('wisski_individual'),
              'settings' => array(),
            );
          }
          if(!array_key_exists($class_name,$instances) || !array_key_exists($field_name,$instances[$class_name])) {
            $instances[$class_name][$field_name] = array(
              'field_name' => $field_name,
              'label' => t($field_label),
              'bundle' => $class_name,
              'entity_type' => 'wisski_individual',
              'widget' => array(
                'type' => 'text_textfield',
                'module' => 'text',
              ),
              'display' => array(
                'default' => array(
                  'label' => 'inline',
                  'type' => 'text_default',
                  'module' => 'text',
                  'settings' => array(
                  ),
                ),
              ),
            );
          }
        }//if(isset...
      }//foreach...
    }//if($ok)
    drupal_set_message("Fields: ".count($fields)."<br>Instances: ".count($instances));
    foreach($fields as $field) {
      if (field_info_field($field['field_name']) == NULL) {
          field_create_field($field);
      } else {
          field_update_field($field);
      }
    }
    foreach($instances as $bundle_name => $inst_class) {
      foreach($inst_class as $instance) {
        if (field_info_instance('wisski_individual',$instance['field_name'],$bundle_name) == NULL) {
          field_create_instance($instance);
        } else {
          field_update_instance($instance);    
        }
      }
    }
    $class->timestamp = time();
    $class->save();
*/
    $this->createEntitiesForBundle($class);
/*    $now = time();
    $setup_time += $now - $then;
    drupal_set_message("Class Update including Entity Creation took ".$this->makeTimeString($query_time)." of query time and ".$this->makeTimeString($setup_time)." for the rest of the setup");
*/
  }

  public function loadClasses() {
   
    //$this->addOntologies();
    $now = time();
    list($ok,$result) 
      = $this->querySPARQL(
        "SELECT DISTINCT ?class"
          ." WHERE { GRAPH ?g { "
            ."?class (rdfs:subClassOf)+ ?super."
          ."} }"
//        ." LIMIT 20"
      );
    $then = time();
    trigger_error("Query took ".$this->makeTimeString($then-$now)." seconds",E_USER_NOTICE);
    $now = time();
    $classes = array();
    if ($ok) {
      $errors = array();
      foreach($result as $obj) {
        $class_label = $obj->class->dumpValue('text');
        $class_name = $this->makeDrupalName($class_label,'');
        $class_title = substr($class_label,strpos($class_label,':')+1);
        if (!array_key_exists($class_name,$classes)) {
          $classes[$class_name] = array(
            'type' => $class_name,
            'label' => $class_label,
            'uri' => wisski_salz_ensure_long_namespace($class_label),
            'title' => $class_title,
            'weight' => 0,
            'description' => 'retrieved from ontology',
            'timestamp' => 0, //ensures update on first view
            'rdf_mapping' => array(),
          );
        }
      }
      uksort($classes,'strnatcasecmp');
      foreach($classes as $class) {
        try {
          $class['bundle of'] = 'wisski_individual';
          $entity = entity_create('wisski_core_bundle',$class);
          entity_save('wisski_core_bundle',$entity);
        } catch (PDOException $ex) {
          $errors['PDOException'][] = $ex->getMessage();
        }
      }
    }
    $then = time();
    trigger_error("Rest of Setup took ".$this->makeTimeString($then-$now),E_USER_NOTICE);
    if (!empty($errors)) {
      $out = '';
      // $err is still an array.
      foreach($errors as $err) $out .= $err[0] ."<br>";
      trigger_error('There were exceptions during the setup: '.$out,E_USER_ERROR);

    } else {
      $count = count($classes);
      trigger_error("Loaded $count classes",E_USER_NOTICE);
    }
  }

  private function loadOntologyInfo() {
    
    $this->settings['ontologies_loaded'] = array();
    $this->settings['ontologies_pending'] = array();
    $result = db_select('wisski_salz_ontologies','ont')
                ->fields('ont')
                ->condition('sid',$this->settings['sid'],'=')
                ->execute()->fetchAllAssoc('oid');
    foreach ($result as $row) {
      $ont = (array) $row;
      if ($ont['added'] == 1) $this->settings['ontologies_loaded'][$ont['oid']] = $ont;
      elseif ($ont['pending'] == 1) $this->settings['ontologies_pending'][$ont['oid']] = $ont;
    }
  }  

  public function addOntologies($iri = NULL) {
/*
    if ($iri != NULL) {
      db_insert('wisski_salz_ontologies')->fields(array('sid' => $this->settings['sid'],'iri' => $iri,'pending' => 1,))->execute(); 
    }
    
    global $base_url;
    $tmpgraph = '<'.$base_url.'/tmp/wisski/add_ontology>';
    $this->loadOntologyInfo();
    list($ok,$result) = $this->querySPARQL('SELECT DISTINCT ?g WHERE {GRAPH ?g {?s ?p ?o}}');
    $knowngraphs = array();
    if (!$ok) {
      foreach($result as $row) $knowngraphs[] = $row->g;
    }
    //if the dummy does not exist, we create it
   # if (!in_array($tmpgraph)) $this->updateSPARQL("CREATE GRAPH $tmpgraph");
    while (isset($this->settings['ontologies_pending']) && !empty($this->settings['ontologies_pending'])) {
      
      $o = array_shift($this->settings['ontologies_pending']);
      $load_iri = $o['iri'];
      
      list($ok,$result) = $this->querySPARQL("ASK {<$load_iri> a owl:Ontology}");
      if (!$ok || $result->isFalse()) {
        drupal_set_message("$load_iri is not an ontology");
        continue;
      }
      drupal_set_message("Adding ontology $load_iri");      
      db_update('wisski_salz_ontologies')
        ->fields(array('pending' => 0))
        ->condition('oid',$o['oid'],'=')
        ->execute();
        
      //quick and easy test if ontology is loaded already. this does not detect all loaded onts
      if (isset($this->settings['ontologies_loaded'][$o['oid']])) continue;

      // OWL2 conformant import: support ontology versions and cyclic imports
      // implements CP 1,2 from http://www.w3.org/TR/owl2-syntax/#Ontology_IRI_and_Version_IRI

      // we first load the file into a dummy graph to inspect it...
      list($ok, $errors) = $this->updateSPARQL("DROP GRAPH $tmpgraph");
      
      list($ok, $errors) = $this->updateSPARQL("LOAD <$load_iri> INTO GRAPH $tmpgraph");

      // get ontology and version uri
      $results = $this->getOntologies($tmpgraph);

      if (empty($results)) {
        continue;
      }

      $result = current($results);

//      dpm($result->ont);

      $iri = property_exists($result->iri) ? $result->iri : $result->ont;
      $ver = property_exists($result->ver) ? $result->ver : '';

      // check if it was loaded already and if there are version clashes
      if (isset($this->settings['ontologies_loaded'][$iri])) {
        $loaded = $this->settings['ontologies_loaded'][$iri];
        if ($loaded['version'] == $ver) {
          continue;
        } else {
          drupal_set_message(t('Error importing ontology %iri from %ont: Import version %vernew differs from imported version %verold.', array('%iri' => $iri, '%ont' => $o, '%vernew' => $ver, '%verold' => $loaded['version'])), 'error');
          break;
        }
      }

      //import it: move it from temporal graph to ontology iri
      list($ok, $errors) = $this->updateSPARQL("MOVE GRAPH $tmpgraph TO GRAPH <$iri>");
*/
/*
      $this->settings['ontologies_loaded'][$iri] = array(
        'iri' => $iri,
        'version' => $ver,
        'source' => $o,
      );

      unset($this->settings['ontologies_pending'][$o]);
*/
/*
      db_update('wisski_salz_ontologies')
        ->fields(array('added' => 1, 'version' => $ver))
        ->condition('oid',$o['oid'],'=')
        ->execute();
 
      // get imports
      // get ontology and version uri
      $query = "SELECT DISTINCT ?ont FROM <$iri> WHERE { ?s a owl:Ontology . ?s owl:imports ?ont . }";
      list($ok, $results) = $this->querySPARQL($query);

      if (!$ok) {
        foreach ($results as $err) {
          drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
        }
      }

      foreach ($results as $result) {
//        $this->settings['ontologies_pending'][] = $result['ont'];
        $isin = db_select('wisski_salz_ontologies','onto')
                  ->fields('onto')
                  ->condition('iri',$o['iri'],'=')
                  ->execute();
        if (empty($isin)) {
          db_insert('wisski_salz_ontologies')
            ->fields(array('pending' => 1,'iri' => $result['ont'], 'source' => $o['oid'], 'sid' => $this->settings['sid']))
            ->execute();
        } else {
          db_update('wisski_salz_ontologies')
            ->fields(array('pending' => 1,'iri' => $result['ont'], 'source' => $o['oid'], 'sid' => $this->settings['sid']))
            ->condition('oid',$o['oid'],'=')
            ->execute();
        }
       }
      $this->loadOntologyInfo();
    }
*/

    if (empty($iri)) {
      //load all ontologies
      $query = "SELECT ?ont WHERE {?ont a owl:Ontology}";
      list($ok,$result) = $this->querySPARQL($query);
      if ($ok) {
        foreach ($result as $obj) {
          $this->addOntologies(strval($obj->ont));
        }
      } else {
        foreach ($result as $err) {
          drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
        }
      }
      return;
    }

    // check if the Ontology is already there
    list($ok,$result) = $this->querySPARQL("ASK {<$iri> a owl:Ontology}");

    if (!$ok) { // we've got something weired.
      drupal_set_message("Store is not requestable.", 'error');
      return;
    } else if(!empty($result)){ // if it is not false it is already there
      drupal_set_message("$iri is already loaded.", 'error');
      return;
    }

    // if we get here we may load the ontology
    $query = "LOAD <$iri> INTO GRAPH <$iri>";
    list($ok, $result) = $this->updateSPARQL($query);

    // everything worked?  
    if (!$ok) {
      foreach ($result as $err) {
        drupal_set_message(t('An error occured while loading the Ontology: ' . serialize($err)),'error');
      }
    } else { // or it worked
      drupal_set_message("Successfully loaded $iri into the Triplestore.");
    }
  
    // look for imported ontologies
    $query = "SELECT DISTINCT ?ont FROM <$iri> WHERE { GRAPH ?g { ?s a owl:Ontology . ?s owl:imports ?ont . } }";
    list($ok, $results) = $this->querySPARQL($query);

    // if there was nothing something is weired again.
    if (!$ok) {
      foreach ($results as $err) {
        drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
      }
    } else { // if there are some we have to load them
      foreach ($results as $to_load) {
        $this->addOntologies(strval($to_load->ont));
      }
    }

    // load the ontology info in internal parameters    
    // $this->loadOntologyInfo();
    
    // add namespaces to table
    $file = file_get_contents($iri);
    $format = EasyRdf_Format::guessFormat($file, $iri);

    if(empty($format)) {
      drupal_set_message("Could not initialize namespaces.", 'error');
    } else {
      if(stripos($format->getName(), 'xml') !== FALSE) {
        preg_match('/RDF[^>]*>/i', $file, $nse);
        
        preg_match_all('/xmlns:[^=]*="[^"]*"/i', $nse[0], $nsarray);
        
        $ns = array();
        $toStore = array();
        foreach($nsarray[0] as $newns) {
          preg_match('/xmlns:[^=]*=/', $newns, $front);
          $front = substr($front[0], 6, strlen($front[0])-7);
          preg_match('/"[^"]*"/', $newns, $end);
          $end = substr($end[0], 1, strlen($end[0])-2);
          $ns[$front] = $end;
        }
                
	preg_match_all('/xmlns="[^"]*"/i', $nse[0], $toStore);
	
	foreach($toStore[0] as $itemGot) {
          $i=0;
	  $key = 'base';
	
	  preg_match('/"[^"]*"/', $itemGot, $item);
	  $item	= substr($item[0], 1, strlen($item[0])-2);
	  
	  if(!array_key_exists($key, $ns)) {
	    if(substr($item, strlen($item)-1, 1) != '#')
	      $ns[$key] = $item . '#';
	    else
	      $ns[$key] = $item;
          } else {
	      $newkey = $key . $i;
	      while(array_key_exists($newkey, $ns)) {
		$i++;
		$newkey = $key . $i;
	      }
	      if(substr($item, strlen($item)-1, 1) != '#')
	 	$ns[$newkey] = $item . '#';
	      else
		$ns[$newkey] = $item;
          }
	}
	
	foreach($ns as $key => $value) {
  	  $this->putNamespace($key, $value);
  	} 
  	
  	global $base_url;
  	// @TODO: check if it is already in the ontolog.
  	$this->putNamespace("local", $base_url . '/');
  	$this->putNamespace("data", $base_url . '/inst/');
      }
      
      
    }    
    
    // return the result
    return $result;   

  }
  
  public function getOntologies($graph = NULL) {
    // get ontology and version uri
    if(!empty($graph)) {
      $query = "SELECT DISTINCT ?ont ?iri ?ver FROM <$graph> WHERE { GRAPH ?g { ?ont a owl:Ontology . OPTIONAL { ?ont owl:ontologyIRI ?iri. ?ont owl:versionIRI ?ver . } } }";
    } else
      $query = "SELECT DISTINCT ?ont (COALESCE(?niri, 'none') as ?iri) (COALESCE(?nver, 'none') as ?ver) (COALESCE(?ngraph, 'default') as ?graph) WHERE { GRAPH ?g { ?ont a owl:Ontology . OPTIONAL { GRAPH ?ngraph { ?ont a owl:Ontology } } . OPTIONAL { ?ont owl:ontologyIRI ?niri. ?ont owl:versionIRI ?nver . } } }";

    list($ok, $results) = $this->querySPARQL($query);
    
    if (!$ok) {
      foreach ($results as $err) {
        drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
      }
    }
    drupal_set_message("RES:");
    dvm($results);
    return $results;   
  }
  
  public function deleteOntology($graph, $type = "graph") {

    // get ontology and version uri
    if($type == "graph") {
      $query = "WITH <$graph> DELETE { ?s ?p ?o } WHERE { ?s ?p ?o }";
    } else
      $query = "DELETE { ?s ?p ?o } WHERE { ?s ?p ?o . FILTER ( STRSTARTS(STR(?s), '$graph')) }";

    list($ok, $results) = $this->updateSPARQL($query);
    
    if (!$ok) {
      // some useful error message :P~
      drupal_set_message('some error encountered:' . serialize($results), 'error');
    }
    
    return $results;   
  }

  public function getExternalLinkURL($uri) {
    //TODO  
  }
  
  private function makeTimeString($time_in_secs) {
    
    if ($time_in_secs < 60) return $time_in_secs.' second(s)';
    $secs = $time_in_secs % 60;
    $sec_string = $secs < 10 ? '0'.$secs : $secs;
    $mins = ($time_in_secs - $secs) / 60;
    if ($mins < 60) return $mins.':'.$sec_string.' minutes';
    $sub_mins = $mins % 60;
    $min_string = $sub_mins < 10 ? '0'.$sub_mins : $sub_mins;
    $hours = ($mins - $sub_mins) / 60;
    return $hours.':'.$min_string.':'.$sec_string.' hours';
  }

  public function simpleInferences() {
    
    drupal_set_message('Start reasoning');
    $this->inferClasses();
    $this->inferClassHierarchy();
    drupal_set_message('Inferred class hierarchy');
    $this->inferPropertyHierarchy();
    drupal_set_message('Inferred property hierarchy');
    $this->inferDomains();
    $this->inferRanges();
    $this->inferClearDomainsAndRanges();
    drupal_set_message('Inferred domains and ranges');
    drupal_set_message('Stop reasoning');
  }
  
  public function inferClasses() {
    $query = "SELECT DISTINCT ?class WHERE { GRAPH ?g {"
      ." ?class rdfs:subClassOf owl:Thing ."
      ." FILTER NOT EXISTS {"
        ." ?class a owl:Class."
      ."}"
    ."} }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok && !empty($result) && $result->numRows() > 0) {
      $graph_name = $this->getGraphName('inference');
      foreach ($result as $obj) {
        $class = $obj->class->getUri();
        $this->updateSPARQL("INSERT DATA {GRAPH <$graph_name> {"
          ."<$class> a owl:Class."
        ."}}");
      }
    }
  }
  
  public function inferClassHierarchy() {
  
    $query = "SELECT DISTINCT ?class ?supersuper WHERE { GRAPH ?g {"
      ." ?class rdfs:subClassOf ?super."
      ." ?super rdfs:subClassOf ?supersuper."
      ." ?supersuper a owl:Class. "
      ." FILTER NOT EXISTS {"
        ." ?class rdfs:subClassOf ?supersuper."
      ."}"
    ."} }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok && !empty($result) && $result->numRows() > 0) {
      $graph_name = $this->getGraphName('inference');
      foreach ($result as $obj) {
        $class = $obj->class->getUri();
        $supersuper = $obj->supersuper->getUri();
        $this->updateSPARQL("INSERT DATA {GRAPH <$graph_name> {"
          ."<$class> rdfs:subClassOf <$supersuper>."
        ."}}");
      }
      //this probably were not the only missing links
      $this->inferClassHierarchy();
    }
  }
    
  public function inferPropertyHierarchy() {
  
    $query = "SELECT DISTINCT ?property ?supersuper WHERE { GRAPH ?g {"
      ." ?property rdfs:subPropertyOf ?super."
      ." ?super rdfs:subPropertyOf ?supersuper."
      ." ?supersuper a owl:ObjectProperty"
      ." FILTER NOT EXISTS {"
        ." ?property rdfs:subPropertyOf ?supersuper."
      ."}"
    ."} }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok && !empty($result) && $result->numRows() > 0) {
      $graph_name = $this->getGraphName('inference');
      foreach ($result as $obj) {
        $property = $obj->property->getUri();
        $supersuper = $obj->supersuper->getUri();
        $this->updateSPARQL("INSERT DATA {GRAPH <$graph_name> {"
          ."<$property> rdfs:subPropertyOf <$supersuper>."
        ."}}");
      }
      //this probably were not the only missing links
      $this->inferPropertyHierarchy();
    }
  }
  
  public function inferDomains() {
  
    $query = "SELECT DISTINCT ?property ?domain WHERE { GRAPH ?g {"
      ."{"
        ." ?property rdfs:subPropertyOf ?super."
        ." ?super rdfs:domain ?domain."
      ."}"
      ."UNION"
      ."{"
        ." ?property owl:inverseOf ?inverse."
        ." ?inverse rdfs:range ?domain."
      ."}"
      ." FILTER NOT EXISTS {"
        ." ?property rdfs:domain ?any_class."
      ."}"
    ."} }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok && !empty($result) && $result->numRows() > 0) {
      $graph_name = $this->getGraphName('inference');
      foreach($result as $obj) {
        $property = $obj->property->getUri();
        $domain = $obj->domain->getUri();
        $this->updateSPARQL("INSERT DATA {GRAPH <$graph_name> {"
          ."<$property> rdfs:domain <$domain>."
        ."}}");
      }
      $this->inferDomains();
    }
  }
  
  public function inferRanges() {
    
    $query = "SELECT DISTINCT ?property ?range WHERE { GRAPH ?g {"
      ."{"
        ." ?property rdfs:subPropertyOf ?super."
        ." ?super rdfs:range ?range."
      ."}"
      ."UNION"
      ."{"
        ." ?property owl:inverseOf ?inverse."
        ." ?inverse rdfs:domain ?range."
      ."}"
      ." FILTER NOT EXISTS {"
        ." ?property rdfs:range ?any_class."
      ."}"
    ."} }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok && !empty($result) && $result->numRows() > 0) {
      $graph_name = $this->getGraphName('inference');
      foreach($result as $obj) {
        $property = $obj->property->getUri();
        $range = $obj->range->getUri();
        $this->updateSPARQL("INSERT DATA {GRAPH <$graph_name> {"
          ."<$property> rdfs:range <$range>."
        ."}}");
      }
      $this->inferRanges();
    }
  }
  
  public function inferClearDomainsAndRanges() {
    
    $graph_name = $this->getGraphName('inference');
    $update = "WITH <$graph_name>"
      ." DELETE {?property rdfs:domain ?superclass.}"
      ." WHERE {"
        ." ?property rdfs:domain ?class."
        ." ?class rdfs:subClassOf+ ?superclass."
      ."}"
    ;
    $this->updateSPARQL($update);
    $update = "WITH <$graph_name>"
      ." DELETE {?property rdfs:range ?superclass.}"
      ." WHERE {"
        ." ?property rdfs:range ?class."
        ." ?class rdfs:subClassOf+ ?superclass."
      ."}"
    ;
    $this->updateSPARQL($update);
  }
}

function sparql11_adapter_makestring(&$value,$key) {

  //$prefix = preg_replace('/^\w+:\w+\d+i?_/u','',$key);
  $prefix = str_replace('_',' ',$key);
  $value = $prefix.' ('.implode(', ',$value).')';
}
