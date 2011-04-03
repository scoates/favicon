<?php

// borrowed idea from Shiflett (inc/favicons.inc), but heavily modified

function do_404($reason = null)
{
	header('HTTP/1.1 404 Not Found');
	if ($reason) {
		header("X-fail-reason: $reason");
		echo $reason;
	}
	exit;
}

function add_found_header($where)
{
	header("X-found: $where");
}

function get_favicon($url)
{
	$md5url = md5($url);
	$tmp = sys_get_temp_dir();

	// "validate" URL
	$urlParts = parse_url($url);
	if (!$urlParts) {
		do_404('Unable to parse URL');
	}
	if (!isset($urlParts['scheme'])) {
		$urlParts['scheme'] = '';
	}
	$urlParts['scheme'] = strtolower($urlParts['scheme']);
	if ($urlParts['scheme'] !== 'http' && $urlParts['scheme'] !== 'https') {
		do_404('Non-HTTP URL');
	}

	// TODO: check localhost/local LAN
	// TODO: check cache
	
	$foundIcon = false;

	// set up base URL
	$remoteBaseUrl = $urlParts['scheme'] . '://';
	if (isset($urlParts['user'])) {
		$remoteBaseUrl .= $urlParts['user'];
		if (isset($urlParts['pass'])) {
			$remoteBaseUrl .= ':' . $urlParts['pass'];
		}
		$remoteBaseUrl .= '@';
	}
	$remoteBaseUrl .= $urlParts['host'];
	if (isset($urlParts['port'])) {
		$remoteBaseUrl .= ':' . $urlParts['port'];
	}

	// check root; do this first because it's preferred by browsers
	if ($fp = @fopen($remoteBaseUrl . '/favicon.ico', 'r')) {
		add_found_header('root');
		$foundIcon = $remoteBaseUrl . '/favicon.ico';
		fclose($fp);
	}

	// if that didn't work, we need to parse the passed URL's contents
	if (!$foundIcon) {
		$dom = new DOMDocument;
		libxml_use_internal_errors(true); // suppress errors
		$dom->preserveWhiteSpace = false;
		$dom->recover = true;
		$dom->strictErrorChecking = false;
		if (!@$dom->loadHTMLFile($url)) {
			do_404('Failed to load URL');
		}

		$xpath = new DOMXpath($dom);
	}

	if (!$foundIcon) {
		// try <link rel="shortcut icon" href="/icon.png" />
		$q = $xpath->query('//link[@rel="shortcut icon"]/@href');
		if ($q->length) {
			$foundIcon = $q->item(0)->value;
			add_found_header('link: shortcut icon');
		}
	}

	if (!$foundIcon) {
		// try <link rel="icon" type="image/png" href="/icon.png" />
		$q = $xpath->query('//link[@rel="icon"]/@href');
		if ($q->length) {
			$foundIcon = $q->item(0)->value;
			add_found_header('link: icon');
		}
	}

	if (!$foundIcon) {
		do_404("Could not determine icon from URL's content");
	}

	// we have an icon; ensure that it's absolute, not local or relative
	if (strpos($foundIcon, '//') === 0) {
		// schemaless URL; give it a schema:
		$foundIcon = $urlParts['sheme'] . ':' . $foundIcon;
	} else {
		// does not start with //
		$parsedIcon = parse_url($foundIcon);
		if (!isset($parsedIcon['scheme'])) {
			// if the URL already contains a scheme, it's already absolute
			// check to see if it's a local path (vs. relative)
			if ('/' === $foundIcon[0]) {
				// just add the remote base URL
				$foundIcon = $remoteBaseUrl . $foundIcon;
			} else {
				// does not start with /, so it must be relative
				// this can get complicated
				// assume that paths containing '.' in the last part are files
				$pathParts = explode('/', $urlParts['path']);
				if (strpos($pathParts[count($pathParts) - 1], '.') !== false) {
					// path points to a file
					array_pop($pathParts); // discard
				}
				// reconstruct
				$recIcon = $remoteBaseUrl . '/' . implode('/', $pathParts);
				if ($pathParts) {
					$recIcon .= '/'; // avoid double /
				}
				$recIcon .= $foundIcon;
				$foundIcon = $recIcon;
			}
		}
	}

	return $foundIcon;
}

$requestUri = false;
if (isset($_SERVER['REQUEST_URI'])) {
	// massage the script name's directory out of the path
	$requestUri = $_SERVER['REQUEST_URI'];
	$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
	if (0 === strpos($requestUri, $scriptDir)) {
		$requestUri = substr($requestUri, strlen($scriptDir));
	}
	if ('/' === $requestUri[0]) {
		$requestUri = substr($requestUri, 1);
	}
}

if ($requestUri && 'index.php' !== $requestUri && '/' !== $requestUri) {
	// change to 302
	$favIcon = get_favicon($requestUri);
	header("Location: $favIcon");
	exit;
}

// no URL passed, so explain:
header('Content-type: text/html;charset=UTF-8'); ?>
<p>Usage: <code>http://favicon.orchestra.io/http://example.com/blog</code></p>

<p>This would try to determine the icon of <code>http://example.com/blog</code>
and would return 404 if not found, and 302 to the new location if found.</p>
