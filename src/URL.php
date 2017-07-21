<?php
/**
 * URL parsing and translation
 */
namespace Lyte\url;
class URL {
	/**
	 * The various components of a URL
	 */
	private $_components = null;

	/**
	 * String representation of the URL
	 */
	private $_url = null;
	
	/**
	 * Create a URL object
	 *
	 * @param string|URL $url
	 * @param array $mods components to change in the URL, e.g to change the
	 *     query string: array('query' => 'new query string')
	 */
	public function __construct($url, $mods = null) {
		if ($url instanceof URL) {
			$this->_url = $url->_url;
			$this->_components = $this->_components;
		} else if (is_string($url) && !empty($url)) {
			$this->_url = $url;
		} else {
			throw new \InvalidArgumentException("url must be a non-empty string or existing URL object");
		}

		if ($this->_components === null) {
			$this->_components = array_merge(array(
				'scheme' => '',
				'host' => '',
				'port' => '',
				'path' => '',
				'query' => '',
				'fragment' => '',
			), parse_url($url));
		}

		if ($mods !== null) {
			$this->_components = array_merge($this->_components, $mods);
			$this->_url = null;
		}
	} // __construct()

	/**
	 * Convert this URL object in to a string URL
	 */
	public function __toString() {
		if ($this->_url === null) {
			$this->_url = 
				$this->_components['scheme']
				.'://'
				.$this->_components['host']
				.(
					$this->_components['port'] === ''
					? ''
					: ':' . $this->_components['port']
				)
				.$this->_components['path']
				.(
					$this->_components['query'] === ''
					? ''
					: '?' . $this->_components['query']
				)
				.(
					$this->_components['fragment'] === ''
					? ''
					: '#' . $this->_components['fragment']
				);
		}

		return $this->_url;
	}

	public function getScheme() {
		return $this->_components['scheme'];
	}

	public function getHost() {
		return $this->_components['host'];
	}

	public function getPort() {
		return $this->_components['port'];
	}

	public function getPath() {
		return $this->_components['path'];
	}

	/**
	 * Just get the basename of the current url
	 * e.g. "asdf.jpg" for "http://example.com/asdf.jpg"
	 */
	public function getBasename() {
		$info = pathinfo($this->getPath());
		return $info['basename'];
	}

	/**
	 * Get the query component
	 */
	public function getQuery() {
		return $this->_components['query'];
	}

	/**
	 * Get the fragment component
	 */
	public function getFragment() {
		return $this->_components['fragment'];
	}

	/**
	 * shortcut to convert a string to an objectified URL
	 */
	public static function &coerce($url) {
		if (!($url instanceof URL))
			$url = new URL($url);
		
		return $url;
	}
	/**
	 * Takes a href (or src etc) value from this page and returns the correct url to be put back
	 *
	 * After discovering a few bugs in my implementation, switched to this version.
	 * Originally sourced from: http://nashruddin.com/PHP_Script_for_Converting_Relative_to_Absolute_URL
	 *
	 * Some patches done to:
	 *  - ignore $rel's that are only an anchor spec
	 *  - add a trailing slash on urls like 'http://example.net'
	 *
	 * Because this proved so brittle to fixes some unit tests were added for it under scripts/tests
	 *
	 * @return string
	 */
	public function translate($relative) {
		// trim any whitespace up front
		$relative = trim($relative);

		// don't translate empty links
		if (empty($relative)) return '';

		//    "http://#FragmentSpecHere" instead of "#FragmentSpecHere"
		// it's a unique enough failure that we can just clean it up when it's found:
		$relative = preg_replace('%^[a-z0-9]+://#%i', '#', $relative);
		
		/* queries and anchors */
		if ($relative[0]=='#') return $relative;
		if ($relative[0]=='?') {
			$mods = array(
				'query' => substr($relative, 1)
			);
			return (string)new URL($this, $mods);
		}

		// Special case back slashes, we want to ensure they're handled like
		// Chrome (i.e. silently translated to '/') ... but not in the query or fragment
		if (preg_match('%^([^#\\?]+)((#|\\?|$).*)$%', $relative, $match)) {
			$prefix = $match[1]; // everything before either a query or fragment string (e.g. scheme, host, port, path)
			$suffix = $match[2]; // query and fragment (if they exist)
			$prefix = str_replace('\\', '/', $prefix);
			$relative = $prefix.$suffix;
		}

		// if we are a scheme relative link, i.e starting with //
		if (substr($relative, 0, 2) == '//')
			$relative = $this->getScheme().':'.$relative;
		
		// parse url using php where possible, use array_merge() to fill empty elements
		$url = array_merge(
			array(
				'scheme' => NULL,
				'host' => NULL,
				'port' => NULL,
				'user' => NULL,
				'pass' => NULL,
				'path' => NULL,
				'query' => NULL,
				'fragment' => NULL,
			),
			$this->parseHREF($relative)
		);

		// don't translate mailto or javascript links
		$schemes = array('mailto', 'javascript', 'tel', 'sms', 'data');
		if (in_array($url['scheme'], $schemes)) {
			return $relative;
		}
		
		$base = array_merge(
			array(
				'scheme' => NULL,
				'host' => NULL,
				'port' => NULL,
				'user' => NULL,
				'pass' => NULL,
				'path' => NULL,
				'query' => NULL,
				'fragment' => NULL,
			),
			parse_url($this->_url)
		);

		// Do string translations before merging with base
		foreach (array('path', 'query') as $component) {
			$url[$component] = strtr($url[$component], array(
				// encode spaces to %20 in path component and no urlencode() and 
				// rawurlencode() are not useful here because they convert
				' ' => '%20',
				// normalise $ as otherwise when we save links inside attributes
				// with DOMDocument they'll get converted there breaking
				// relinking
				'$' => '%24',
			));
		}

		// if scheme is set we have a full url, so we just need to clean up a little
		if (isset($url['scheme'])) {
			// protocols and hostnames should be lower case
			$url['host'] = strtolower($url['host']);
			$url['scheme'] = strtolower($url['scheme']);
		} else {
			// if destination path is not site relative
			if ($url['path'][0] != '/') {
				/* remove non-directory element from path */
				$base['path'] = preg_replace('#/[^/]*$#', '', $base['path']);
				
				$url['path'] = $base['path'].'/'.$url['path'];
			}
			
			$url['scheme'] = $base['scheme'];
			$url['host'] = $base['host'];
		}


		$re = array(
			// path components that refer to the current directory: /./
			'#(/\.?/)|(/\.$)#',
			// path components that collapse their parent: /foo/../
			'#/(?!\.\.)[^/]+/\.\./#',
			// path components that go up a directory when there's nowhere to go up: ^/../
			'#^/\.\.#',
		);
		for($n=1; $n>0; $url['path'] = preg_replace($re, '/', $url['path'], -1, $n)) {}

		// decorate some elements that need it
		if (!empty($url['port'])) $url['port'] = ':'.$url['port'];
		if (!empty($url['query'])) $url['query'] = '?'.$url['query'];
		if (!empty($url['fragment'])) $url['fragment'] = '#'.$url['fragment'];

		// if the path is empty, best to correct it to '/'
		if (empty($url['path'])) $url['path'] = '/';

		// return absolute url
		return
			$url['scheme'].'://'
			.$url['host']
			.$url['port']
			.$url['path']
			.$url['query']
			.$url['fragment'];
	}

	/**
	 * Parse a HREF and return its components as an array
	 *
	 * Alternative to PHP's inbuilt parse_url()
	 *
	 * We can't use parse_url() for a couple of reasons, here's one:
	 * https://bugs.php.net/bug.php?id=68296
	 */
	public static function parseHREF($url) {
		$parts = array();

		$remainder = $url;

		// look for the scheme first
		if (preg_match('!^([a-z0-9]+):/+!msixS', $remainder, $match)) {
			$parts['scheme'] = $match[1];
			$remainder = substr($remainder, strlen($match[0]));
			// if we have a scheme, try for a host:port
			if (preg_match('!^[^:/]+!msixS', $remainder, $match)) {
				$parts['host'] = $match[0];
				$remainder = substr($remainder, strlen($match[0]));
			}
			if (preg_match('!^:([0-9]+)!msixS', $remainder, $match)) {
				$parts['port'] = $match[1];
				$remainder = substr($remainder, strlen($match[0]));
			}
		}
		// try for a javascript: or mailto: style scheme
		else if (preg_match('!^(javascript|mailto|tel|sms|data):?!msixS', $remainder, $match)) {
			$parts['scheme'] = $match[1];
			$remainder = substr($remainder, strlen($match[0]));
		}
		// check for path?query#fragment
		if (preg_match('!^[^\?]+!msixS', $remainder, $match)) {
			// strip new lines from the path as that's what the browser does
			$parts['path'] = str_replace("\n", "", $match[0]);
			$remainder = substr($remainder, strlen($match[0]));
		}
		if (preg_match('!^\?([^\#]+)!msixS', $remainder, $match)) {
			$parts['query'] = $match[1];
			$remainder = substr($remainder, strlen($match[0]));
		}
		if (preg_match('!^\#(.+)$!msixS', $remainder, $match)) {
			$parts['fragment'] = $match[1];
			$remainder = substr($remainder, strlen($match[0]));
		}

		return $parts;
	}
}
