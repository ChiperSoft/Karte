<?php 

class Karte {
	protected $routes_path;
	protected $filter_paired_arguments = true;
	protected $pair_all_arguments = false;

	protected $index_name = 'index';
	protected $catchall_name = '_catchall';
	protected $notfound_name = '404';

	public $original_url;
	public $original_chunks;
	
	public $map;
	public $chunks;
	
	public $route_name;
	public $route_file;
	public $original_route;
	
	public $arguments;
	
	
	/**
	 * Class constructor
	 *
	 * @param string $path optional Path to the routes folder
	 * @param string|true $url optional URL to immediately parse and execute. If passed `true`, will parse the page request URL instead.
	 */
	function __construct($path = null, $url = null) {
		if ($path !== null) $this->setRoutesPath($path);
		if ($url !== null) {
			if ($url === true) $this->parseCurrentRequest()->run();
			else $this->parseURL($url)->run();
		}
	}
	
	/**
	 * Static shortcut for initializing a new Karte object with chaining.
	 *
	 * @param string $path optional Path to the routes folder
	 * @param string|true $url optional URL to immediately parse and execute. If passed `true`, will parse the page request URL instead.
	 * @return Karte
	 */
	static function Execute($path = null, $url = null) {
		return new static($path, $url);
	}
	
	
	/**
	 * Sets the search path for finding route files
	 *
	 * @param string $path 
	 * @return Karte Current instance, for chaining
	 * @author Jarvis Badgley
	 */
	function setRoutesPath($path) {
		$this->routes_path = realpath($path).'/';
		return $this;
	}
	
	
	/**
	 * Disabled default Karte behavior of excluding any value paired arguments from the integer indexes when building the arguments array
	 *
	 * @param boolean $yes optional
	 * @return Karte Current instance, for chaining
	 */
	function indexPairedArguments($yes = true) {
		$this->filter_paired_arguments = !$yes;
		return $this;
	}
	
	
	/**
	 * Causes Karte to add every argument as a key on the arguments table, regardless of if a paired value is defined.
	 *
	 * @param boolean $yes optional
	 * @return Karte Current instance, for chaining
	 */
	function pairAllArguments($yes = true) {
		$this->pair_all_arguments = $yes;
		return $this;
	}
	
	
	/**
	 * Overwrites the default site index route ("index") with the supplied name.
	 *
	 * @param string $name 
	 * @return Karte Current instance, for chaining
	 */
	function setSiteIndex($name) {
		$this->index_name = $name;
		return $this;
	}


	/**
	 * Overwrites the default site page not found route ("404") with the supplied name.
	 *
	 * @param string $index 
	 * @return Karte Current instance, for chaining
	 */
	function setNotFound($name) {
		$this->notfound_name = $name;
		return $this;
	}


	/**
	 * Overwrites the default catchall route ("_catchall") with the supplied name.
	 *
	 * @param string $index 
	 * @return Karte Current instance, for chaining
	 */
	function setCatchAll($name) {
		$this->catchall_name = $name;
		return $this;
	}

	
	/**
	 * Parses the passed url and finds the relevant route file.
	 *
	 * @param string $url 
	 * @return Karte Current instance, for chaining
	 */
	public function parseURL($url) {
		if (!$this->routes_path || !file_exists($this->routes_path) || !is_dir($this->routes_path)) throw new Exception("Routes directory does not exist or is undefined.");
		
		$this->original_url = parse_url($url, PHP_URL_PATH);  //grab only the path argument, ignoring the domain, query or fragments
		
		$chunks = $this->original_chunks = array_splice(explode('/',$this->original_url),1); //split the path by the slashes and strip the first value (which is always empty)
		
		$named_args = array();
		$ordered_args = array();
		foreach ($chunks as $index => $chunk) {
			if ($chunk==='') continue;
			
			//if the chunk contains an equals sign, split it as a named argument and store the value.
			if (($eqpos = strpos($chunk,'=')) !== false) {
				$data = substr($chunk, $eqpos+1);
				$chunk = substr($chunk, 0, $eqpos);
				$named_args[$chunk] = $data;
			} elseif ($this->pair_all_arguments) {
				$named_args[$chunk] = '';
			}
			
			if ($chunk !== '') $this->map[$chunk] = $index;
			$ordered_args[] = $chunk;
		}
		
		//save the original list incase the developer needs it.
		$this->chunks = $ordered_args;
		
		//if the arguments array is empty, then this is a request to the site index
		if (!count(array_filter($ordered_args))) {
			$ordered_args = array($this->index_name);
		}			
		
		list($route_name, $arguments) = $this->findRoute($ordered_args);
		
		//if no route match was found, look for a catchall route. if no catchall, send to 404.
		if ($route_name === null && $arguments === null) {
			if (file_exists($this->routes_path . $this->catchall_name)) {
				$route_name = $this->catchall_name;
			} else {
				$route_name = $this->notfound_name;
			}
			$arguments = $ordered_args;
		}
		
		//remove any named arguments from the list
		if ($this->filter_paired_arguments) $arguments = array_diff($arguments, array_keys($named_args));
		
		//re-combine with named args to produce the indexed arguments collection
		$arguments = array_merge($arguments, $named_args);
		
		//url decode all argument values
		array_walk($arguments, function (&$item, $key) {$item = urldecode($item);});
		
		$this->route_name = $this->original_route = $route_name;
		$this->route_file = $this->routes_path . $route_name . '.php';
		$this->arguments = $arguments;
		
		return $this;
	}
	
	
	/**
	 * Parses the current page request URL and finds the relevant route file
	 *
	 * @return Karte Current instance, for chaining
	 */
	public function parseCurrentRequest() {
		return $this->parseURL($_SERVER['REQUEST_URI']);
	}
	
	
	/**
	 * Searches routes folder for a file that matches the named arguments list
	 *
	 * @param array $arguments 
	 * @return array Returns a tuple containing the route name and the remaining arguments.
	 */
	protected function findRoute($arguments) {
		//strip out any empty string arguments and re-sequence the array
		$arguments = array_filter($arguments, function ($item) {return $item!=='';});

		//work backwards through the list of arguments until we find a route file that matches
		$chunks = $arguments;
		$found = false;
		while (!empty($chunks)) {
			
			$route_name = implode('.',$chunks);

			if ($found = $this->checkRoute($route_name)) break;
		
			array_pop($chunks);
		}
		
		//separate the route name from the arguments list
		array_splice($arguments,0, count($chunks));
		
		//if we found a route, return it.  Otherwise return false.
		if ($found) return array($route_name, $arguments);
		else return false;
		
	}
	
	/**
	 * Internal function to test if a route exists.
	 *
	 * @param string $name Name of the route
	 * @return boolean
	 */
	protected function checkRoute($name) {
		return file_exists($this->routes_path . $name . '.php');
	}
	
	
	/**
	 * Runs the current route
	 *
	 * @return Karte Current instance, for chaining
	 */
	public function run() {
		if (!file_exists($this->route_file)) throw new Exception('Route file does not exist: '.$this->route_file);
		
		$closure = function ($route, $arguments) {
			include $route->route_file;
		};
		
		$closure($this, $this->arguments);		
		
		return $this;
	}
	
	
	/**
	 * Reroutes the request to the specified route.
	 *
	 * @param string $new_route Name of the new route
	 * @return Karte Current instance, for chaining
	 */
	public function reroute($new_route = null) {

		if ($new_route !== null) {
			$this->route_name = $new_route;
			$this->route_path = $this->routes_path . $new_route . '.php';
		}

		return $this->run();
		
	}
	
	
	/**
	 * Rewrites the original url using the named values passed in an array.
	 *
	 * @param array $values 
	 * @return string The new url
	 */
	public function rewriteURL($values) {
		
		$chunks = $this->original_chunks;
		
		foreach ($values as $key => $value) {
			
			//generate the new chunk. 
			if ($value === null) {
				//If value is null, the chunk is the key name
				$chunk = $key;
			} elseif ($value === false) {
				//if the value is false, the chunk is null so it is removed
				$chunk = null;
			} else {
				//otherwise, the chunk is the key name paired with the urlencoded value
				$chunk = $key."=".urlencode($value);
			}
		
			//if the key exists in the previous url, replace it with the new value.
			//otherwise append to the end of the url
			if (isset($this->map[$key])) {
				$chunks[ $this->map[$key] ] = $chunk;
			} else {
				$chunks[] = $chunk;
			}
			
		}
		
		//remove any null chunks
		$chunks = array_filter($chunks, function ($item) {return $item!==null && $item!=='';});
		
		return '/'.implode('/', $chunks);
		
	}
	
}

