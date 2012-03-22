<?php 

namespace Chipersoft\Karte;

class CachedRouter extends Router {
	
	protected $routes = false;
	protected $apc_key = false;
	protected $cache_duration = 60;
	
	/**
	 * Sets the search path for finding route files
	 *
	 * @param string $path 
	 * @return Karte Current instance, for chaining
	 * @author Jarvis Badgley
	 */
	function setRoutesPath($path) {
		parent::setRoutesPath($path);
		if (function_exists('apc_exists')) {
			$this->apc_key = $_SERVER['HTTP_HOST'].':KarteCache:'.$path;
			$this->routes = apc_fetch($this->apc_key);
		}
		if ($this->routes === false) {
			$this->apc_key = false;
			$this->loadRoutes();
		}
		return $this;
	}
	
	
	protected function loadRoutes() {
		$this->routes = array();
		if ($dh = opendir($this->routes_path)) {
			while (($file = readdir($dh)) !== false) if ($file != "." && $file != "..") {
				$pinfo = pathinfo($file);
				if ($pinfo['extension'] == 'php') {
					$this->routes[ $pinfo['filename'] ] = true;
				}
			}
			closedir($dh);
		}
		
		if ($this->apc_key) {
			apc_store($this->apc_key, $this->routes, $this->cache_duration);
		}
	}
	
	/**
	 * Internal function to test if a route exists.
	 *
	 * @param string $name Name of the route
	 * @return boolean
	 */
	protected function checkRoute($name) {
		return isset($this->routes[$name]);
	}
	
	
}
