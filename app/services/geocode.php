<?php
/**
 * Geocoding service — converts text addresses to latitude/longitude.
 *
 * Uses Nominatim (OpenStreetMap's free geocoding service).
 * https://nominatim.openstreetmap.org/
 *
 * Usage policy compliance:
 *   - Requires a valid HTTP User-Agent header (set below).
 *   - Rate limit: maximum 1 request per second (enforced via sleep).
 *   - Results are cached in a temporary file to avoid repeated requests.
 *
 * Privacy:
 *   - The full address is sent to Nominatim only when geocoding is requested.
 *   - Customers must be informed (consent checkbox on the form) before geocoding.
 *   - Full addresses are never logged. Only success/failure and coordinates.
 *   - Error messages do not include the address text.
 *
 * This service is ADDITIVE — it does not modify any existing files.
 * It provides a single function: geocode_address()
 */

declare(strict_types=1);

/**
 * Geocode a Malaysian address using Nominatim.
 *
 * @param string $line1    Street address (e.g., "1, Jalan SS 15/8a")
 * @param string $city     City (e.g., "Subang Jaya")
 * @param string $postcode 5-digit postcode (e.g., "47500")
 * @param string $state    State (e.g., "Selangor")
 * @return array|null      ['lat' => float, 'lng' => float] or null on failure
 */
function geocode_address(string $line1, string $city, string $postcode, string $state): ?array
{
	// Build the search query — combine address components.
	// Add "Malaysia" to improve accuracy for Malaysian addresses.
	$parts = array_filter([$line1, $city, $postcode, $state, 'Malaysia'], fn($p) => $p !== '' && $p !== '-');
	if (empty($parts)) return null;

	$query = implode(', ', $parts);

	// Build the Nominatim API URL.
	$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
		'q'              => $query,
		'format'         => 'json',
		'limit'          => 1,
		'countrycodes'   => 'my',  // Restrict to Malaysia
		'addressdetails' => 0,     // Don't need full address details back
	]);

	// Check the cache first — avoid repeated requests for the same address.
	$cached = geocode_cache_get($query);
	if ($cached !== null) return $cached;

	// Rate limiting: Nominatim requires max 1 request per second.
	// We sleep for 1.1 seconds before making the request.
	// This is conservative to respect the usage policy.
	usleep(1100000); // 1.1 seconds

	// Make the HTTP request using cURL.
	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 10,       // 10 second timeout
		CURLOPT_CONNECTTIMEOUT => 5,        // 5 second connect timeout
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 3,
		// Nominatim requires a User-Agent identifying the application.
		// https://operations.osmfoundation.org/policies/nominatim/
		CURLOPT_HTTPHEADER     => [
			'User-Agent: CafeDash/1.0 (student project; contact: admin@cafedash.com)',
			'Accept: application/json',
		],
	]);

	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curlError = curl_error($ch);
	curl_close($ch);

	// Handle cURL errors (network issues, timeout, etc.)
	if ($response === false || $curlError) {
		error_log('Geocoding: HTTP request failed (network error).');
		return null;
	}

	// Handle non-200 HTTP responses
	if ($httpCode !== 200) {
		error_log('Geocoding: Nominatim returned HTTP ' . $httpCode);
		return null;
	}

	// Parse the JSON response.
	$data = json_decode($response, true);
	if (!is_array($data) || empty($data)) {
		// No results found — the address could not be geocoded.
		// Cache the negative result to avoid retrying the same address.
		geocode_cache_set($query, null);
		return null;
	}

	// Extract the first result.
	$lat = isset($data[0]['lat']) ? (float) $data[0]['lat'] : null;
	$lng = isset($data[0]['lon']) ? (float) $data[0]['lon'] : null;

	// Validate coordinate ranges (Malaysia bounds: roughly 1-7°N, 100-119°E)
	if ($lat === null || $lng === null) {
		geocode_cache_set($query, null);
		return null;
	}

	if ($lat < 0.5 || $lat > 8.0 || $lng < 99.0 || $lng > 120.0) {
		// Coordinates are outside Malaysia — likely a bad geocode result.
		error_log('Geocoding: Result outside Malaysia bounds. lat=' . $lat . ' lng=' . $lng);
		geocode_cache_set($query, null);
		return null;
	}

	$result = ['lat' => $lat, 'lng' => $lng];

	// Cache the successful result.
	geocode_cache_set($query, $result);

	return $result;
}

/**
 * Get a cached geocoding result from the temporary file cache.
 * Cache entries expire after 7 days (coordinates rarely change).
 */
function geocode_cache_get(string $query): ?array
{
	$cacheFile = geocode_cache_file();
	if (!is_file($cacheFile)) return null;

	$cache = json_decode(file_get_contents($cacheFile), true);
	if (!is_array($cache)) return null;

	$key = md5($query);
	if (!isset($cache[$key])) return null;

	$entry = $cache[$key];
	// Check if the cache entry has expired (7 days = 604800 seconds)
	if (time() - $entry['time'] > 604800) {
		return null; // Expired — caller will make a fresh request
	}

	return $entry['result']; // May be null (cached negative result)
}

/**
 * Store a geocoding result in the temporary file cache.
 */
function geocode_cache_set(string $query, ?array $result): void
{
	$cacheFile = geocode_cache_file();
	$cache = [];

	if (is_file($cacheFile)) {
		$cache = json_decode(file_get_contents($cacheFile), true);
		if (!is_array($cache)) $cache = [];
	}

	$key = md5($query);
	$cache[$key] = ['time' => time(), 'result' => $result];

	// Limit cache to 500 entries to prevent unbounded growth
	if (count($cache) > 500) {
		// Remove the oldest 100 entries
		uasort($cache, fn($a, $b) => $a['time'] <=> $b['time']);
		$cache = array_slice($cache, 100, null, true);
	}

	file_put_contents($cacheFile, json_encode($cache));
}

/**
 * Get the path to the geocoding cache file.
 * Stored in the system temp directory to avoid polluting the project.
 */
function geocode_cache_file(): string
{
	return sys_get_temp_dir() . '/cafedash_geocode_cache.json';
}
