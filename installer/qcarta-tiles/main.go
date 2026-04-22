package main

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"image"
	"image/png"
	"io"
	"log"
	"math"
	"net/http"
	"net/http/httptest"
	"net/url"
	"os"
	"path"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"sync/atomic"
	"time"
)

const (
	// QGIS Server endpoint
	envQgisServerURL = "QGIS_SERVER_URL"

	// Default cache directory (override with QCARTA_CACHE_DIR)
	defaultCacheDir = "/var/www/data/qcarta-tiles/cache_data"

	// Server port (listen on localhost only)
	serverPort = "127.0.0.1:8011"
	
	// Bearer token for cache purge (set via environment variable)
	envBearerToken = "QCARTA_CACHE_PURGE_TOKEN"

	// When set to "1", logs [CACHE DEBUG] lines: normalized query, full cache key, disk paths, HIT/MISS/SAVE.
	envCacheDebug = "QCARTA_CACHE_DEBUG"
	
	// Environment variable to disable cache
	envDisableCache = "QCARTA_DISABLE_CACHE"

	// EPSG:3857 Web Mercator half extent (OGC WMS / slippy tile grid)
	mercatorExtent = 20037508.342789244
	tileSize       = 256
)

// Global cache manager instance (created once at startup)
var cacheManager *CacheManager

// In-memory cache metrics (updated by HTTP handlers)
var (
	cacheHits   int64
	cacheMisses int64
	cacheBypass int64
)

// Limits concurrent upstream QGIS requests (tiles + WMS/GetFeatureInfo) to avoid overload/503s.
var qgisLimiter = make(chan struct{}, 6)

func getCacheDir() string {
	dir := os.Getenv("QCARTA_CACHE_DIR")
	if dir == "" {
		return defaultCacheDir
	}
	return dir
}

func getCacheTTL() time.Duration {
	ttlStr := os.Getenv("QCARTA_CACHE_TTL")
	if ttlStr == "" {
		return 7 * 24 * time.Hour
	}
	ttlSec, err := strconv.Atoi(ttlStr)
	if err != nil {
		return 7 * 24 * time.Hour
	}
	return time.Duration(ttlSec) * time.Second
}

func getMetaTileSize() int {
	v := os.Getenv("QCARTA_METATILE_SIZE")
	if v == "" {
		return 4
	}
	n, err := strconv.Atoi(v)
	if err != nil || n < 1 {
		return 4
	}
	return n
}

func getMetaTileBuffer() int {
	v := os.Getenv("QCARTA_METATILE_BUFFER")
	if v == "" {
		return 64
	}
	n, err := strconv.Atoi(v)
	if err != nil || n < 0 {
		return 64
	}
	return n
}

// getMetaTileSizeForQuery returns meta from query param "meta" when valid (>=1), else env default.
func getMetaTileSizeForQuery(q url.Values) int {
	if s := strings.TrimSpace(getParamCI(q, "meta")); s != "" {
		if n, err := strconv.Atoi(s); err == nil && n >= 1 {
			return n
		}
	}
	return getMetaTileSize()
}

// getMetaTileBufferForQuery returns buffer from query param "buffer" when valid (>=0), else env default.
func cacheDebugEnabled() bool {
	return strings.TrimSpace(os.Getenv(envCacheDebug)) == "1"
}

func getMetaTileBufferForQuery(q url.Values) int {
	if s := strings.TrimSpace(getParamCI(q, "buffer")); s != "" {
		if n, err := strconv.Atoi(s); err == nil && n >= 0 {
			return n
		}
	}
	return getMetaTileBuffer()
}

func isProxyControlParam(k string) bool {
	switch strings.ToUpper(k) {
	case "CACHE", "META", "BUFFER":
		return true
	default:
		return false
	}
}

func shortCacheKey(cacheKey string) string {
	if len(cacheKey) <= 8 {
		return cacheKey
	}
	return cacheKey[:8]
}

func tileToBBox(x, y, z int) (float64, float64, float64, float64) {
	res := (2 * mercatorExtent) / (float64(tileSize) * math.Pow(2, float64(z)))

	minx := -mercatorExtent + float64(x)*float64(tileSize)*res
	maxx := -mercatorExtent + float64(x+1)*float64(tileSize)*res

	maxy := mercatorExtent - float64(y)*float64(tileSize)*res
	miny := mercatorExtent - float64(y+1)*float64(tileSize)*res

	return minx, miny, maxx, maxy
}

func sliceMetaTile(imgData []byte, metaSize int, buffer int) ([][]byte, error) {
	img, err := png.Decode(bytes.NewReader(imgData))
	if err != nil {
		return nil, err
	}

	metaPixels := tileSize * metaSize
	want := metaPixels + 2*buffer
	b := img.Bounds()
	if b.Dx() != want || b.Dy() != want {
		return nil, fmt.Errorf("metatile image size %dx%d, want %dx%d", b.Dx(), b.Dy(), want, want)
	}

	ox, oy := b.Min.X, b.Min.Y
	tiles := [][]byte{}
	for ty := 0; ty < metaSize; ty++ {
		for tx := 0; tx < metaSize; tx++ {
			rect := image.Rect(
				ox+buffer+tx*tileSize,
				oy+buffer+ty*tileSize,
				ox+buffer+(tx+1)*tileSize,
				oy+buffer+(ty+1)*tileSize,
			)

			sub := img.(interface {
				SubImage(r image.Rectangle) image.Image
			}).SubImage(rect)

			buf := new(bytes.Buffer)
			if err := png.Encode(buf, sub); err != nil {
				return nil, err
			}
			tiles = append(tiles, buf.Bytes())
		}
	}

	return tiles, nil
}

type CacheManager struct {
	baseDir string
}

func NewCacheManager(baseDir string) *CacheManager {
	if err := os.MkdirAll(baseDir, 0755); err != nil {
		log.Fatalf("Failed to create cache directory: %v", err)
	}
	// Verify cache directory is writable
	testFile := filepath.Join(baseDir, ".writable_test")
	if f, err := os.Create(testFile); err != nil {
		log.Fatalf("Cache directory is not writable: %v", err)
	} else {
		f.Close()
		os.Remove(testFile)
	}
	return &CacheManager{baseDir: baseDir}
}

// getParamCI gets a query parameter value case-insensitively
// Tries the exact key, then lowercase, then uppercase
func getParamCI(q url.Values, key string) string {
	if v := q.Get(key); v != "" {
		return v
	}
	if v := q.Get(strings.ToLower(key)); v != "" {
		return v
	}
	if v := q.Get(strings.ToUpper(key)); v != "" {
		return v
	}
	return ""
}

func delKeyCI(q url.Values, canonicalUpper string) {
	for k := range q {
		if strings.ToUpper(k) == canonicalUpper {
			delete(q, k)
		}
	}
}

func setParamCI(q url.Values, canonicalUpper, value string) {
	delKeyCI(q, canonicalUpper)
	q.Set(canonicalUpper, value)
}

func cloneURLValues(src url.Values) url.Values {
	dst := make(url.Values, len(src))
	for k, vs := range src {
		dst[k] = append([]string(nil), vs...)
	}
	return dst
}

func urlValuesWithoutCache(q url.Values) url.Values {
	out := make(url.Values)
	for k, v := range q {
		if strings.ToUpper(k) == "CACHE" {
			continue
		}
		out[k] = append([]string(nil), v...)
	}
	return out
}

// urlValuesForTileCacheKey strips proxy/tile-service-only params so the same 256×256 tile
// shares one cache entry regardless of meta/buffer/CACHE (they affect metatile fetch, not tile identity).
func urlValuesForTileCacheKey(q url.Values) url.Values {
	out := make(url.Values)
	for k, v := range q {
		if isProxyControlParam(k) {
			continue
		}
		out[k] = append([]string(nil), v...)
	}
	return out
}

// buildSingleTileWMSQuery returns a 256×256 WMS 1.1.1 / EPSG:3857 GetMap query for one XYZ tile (no WMS normalization).
func buildSingleTileWMSQuery(base url.Values, tileX, tileY, z int) url.Values {
	q := cloneURLValues(base)
	delKeyCI(q, "SERVICE")
	delKeyCI(q, "REQUEST")
	delKeyCI(q, "VERSION")
	delKeyCI(q, "CRS")
	delKeyCI(q, "SRS")
	delKeyCI(q, "BBOX")
	delKeyCI(q, "WIDTH")
	delKeyCI(q, "HEIGHT")
	delKeyCI(q, "FORMAT")
	delKeyCI(q, "TRANSPARENT")

	q.Set("SERVICE", "WMS")
	q.Set("REQUEST", "GetMap")
	q.Set("VERSION", "1.1.1")
	q.Set("SRS", "EPSG:3857")
	minx, miny, maxx, maxy := tileToBBox(tileX, tileY, z)
	q.Set("BBOX", fmt.Sprintf("%f,%f,%f,%f", minx, miny, maxx, maxy))
	q.Set("WIDTH", strconv.Itoa(tileSize))
	q.Set("HEIGHT", strconv.Itoa(tileSize))
	q.Set("FORMAT", "image/png")
	q.Set("TRANSPARENT", "true")
	return q
}

func getWMSVersion(q url.Values) string {
	v := strings.TrimSpace(getParamCI(q, "VERSION"))
	if v == "" {
		return "1.1.1"
	}
	return v
}

func setCRSParam(q url.Values, version string, crs string) {
	delKeyCI(q, "CRS")
	delKeyCI(q, "SRS")
	if strings.TrimSpace(version) == "1.3.0" {
		q.Set("CRS", crs)
	} else {
		q.Set("SRS", crs)
	}
}

func normalizeBBox(version, crs, bbox string) string {
	if strings.TrimSpace(version) == "1.3.0" && strings.ToUpper(strings.TrimSpace(crs)) == "EPSG:4326" {
		parts := strings.Split(bbox, ",")
		if len(parts) != 4 {
			return bbox
		}
		return fmt.Sprintf("%s,%s,%s,%s",
			strings.TrimSpace(parts[1]), strings.TrimSpace(parts[0]),
			strings.TrimSpace(parts[3]), strings.TrimSpace(parts[2]))
	}
	return bbox
}

func normalizeWMSProxyQuery(queryParams url.Values) {
	version := getWMSVersion(queryParams)
	crs := getParamCI(queryParams, "CRS")
	if crs == "" {
		crs = getParamCI(queryParams, "SRS")
	}
	setCRSParam(queryParams, version, crs)
	bbox := getParamCI(queryParams, "BBOX")
	if bbox != "" {
		setParamCI(queryParams, "BBOX", normalizeBBox(version, crs, bbox))
	}
}

// projectKeyFromQuery extracts and sanitizes the project name from the map query parameter
func projectKeyFromQuery(q url.Values) string {
	mapVal := getParamCI(q, "map")
	if mapVal == "" {
		return "unknown"
	}

	decoded, err := url.QueryUnescape(mapVal)
	if err != nil {
		decoded = mapVal
	}

	// map may be full path like /var/www/data/stores/5/pari.qgs
	base := path.Base(decoded) // use path, not filepath (map uses / separators)
	base = strings.TrimSuffix(base, ".qgs")
	base = strings.TrimSuffix(base, ".qgz")

	// sanitize to filesystem-safe
	var b strings.Builder
	for _, ch := range base {
		if (ch >= 'a' && ch <= 'z') ||
			(ch >= 'A' && ch <= 'Z') ||
			(ch >= '0' && ch <= '9') ||
			ch == '.' || ch == '_' || ch == '-' {
			b.WriteRune(ch)
		} else {
			b.WriteRune('_')
		}
	}
	out := strings.Trim(b.String(), "._-")
	if out == "" {
		out = "unknown"
	}
	return out
}

// generateCacheKeyAndQuery builds the exact normalized query string used for hashing and returns its SHA256 hex digest.
// All WMS dimensions that affect the map image are included (e.g. VERSION, CRS/SRS, BBOX, WIDTH, HEIGHT, LAYERS, …).
func (cm *CacheManager) generateCacheKeyAndQuery(queryParams url.Values) (cacheKeyHex string, normalizedQuery string) {
	// Normalize query parameters: decode values and re-encode consistently
	normalized := make(url.Values)

	// First, handle SERVICE and REQUEST case-insensitively and normalize to uppercase keys
	serviceValue := getParamCI(queryParams, "SERVICE")
	requestValue := getParamCI(queryParams, "REQUEST")

	// Sort keys for consistent cache keys
	keys := make([]string, 0, len(queryParams))
	for k := range queryParams {
		// Skip SERVICE/REQUEST keys if we found them case-insensitively (we'll add them back with uppercase keys)
		kUpper := strings.ToUpper(k)
		if (kUpper == "SERVICE" && serviceValue != "") || (kUpper == "REQUEST" && requestValue != "") {
			continue
		}
		keys = append(keys, k)
	}
	sort.Strings(keys)

	// Normalize each parameter: decode URL encoding and re-encode consistently
	for _, k := range keys {
		values := queryParams[k]
		normalizedValues := make([]string, 0, len(values))
		for _, v := range values {
			// Decode URL encoding to normalize
			decoded, err := url.QueryUnescape(v)
			if err != nil {
				// If decoding fails, use original value
				decoded = v
			}
			// Re-encode consistently
			normalizedValues = append(normalizedValues, url.QueryEscape(decoded))
		}
		// Sort values for each key
		sort.Strings(normalizedValues)
		normalized[k] = normalizedValues
	}

	// Add SERVICE and REQUEST with uppercase keys and uppercase values
	if serviceValue != "" {
		decoded, err := url.QueryUnescape(serviceValue)
		if err != nil {
			decoded = serviceValue
		}
		normalized.Set("SERVICE", url.QueryEscape(strings.ToUpper(decoded)))
	}
	if requestValue != "" {
		decoded, err := url.QueryUnescape(requestValue)
		if err != nil {
			decoded = requestValue
		}
		normalized.Set("REQUEST", url.QueryEscape(strings.ToUpper(decoded)))
	}

	// Build sorted query string from normalized parameters
	var parts []string
	for _, k := range keys {
		for _, v := range normalized[k] {
			parts = append(parts, fmt.Sprintf("%s=%s", k, v))
		}
	}

	normalizedQuery = strings.Join(parts, "&")
	hash := sha256.Sum256([]byte(normalizedQuery))
	return hex.EncodeToString(hash[:]), normalizedQuery
}

// generateCacheKey creates a cache key from sorted, normalized query parameters.
func (cm *CacheManager) generateCacheKey(queryParams url.Values) string {
	k, _ := cm.generateCacheKeyAndQuery(queryParams)
	return k
}

// getCachePath returns the file path for a cached response
// Format: ${cacheDir}/wms/<projectKey>/<first2>/<hash>.<ext>
func (cm *CacheManager) getCachePath(projectKey string, cacheKey string, ext string) (string, error) {
	// Guard against short hashes
	if len(cacheKey) < 2 {
		return "", fmt.Errorf("cache key too short: len=%d", len(cacheKey))
	}
	if projectKey == "" {
		projectKey = "unknown"
	}
	// Use first 2 characters for directory structure (like MapProxy)
	// Path structure: wms/<projectKey>/<first2>/<hash>.<ext>
	dir := filepath.Join(cm.baseDir, "wms", projectKey, cacheKey[:2])
	if err := os.MkdirAll(dir, 0755); err != nil {
		return "", fmt.Errorf("failed to create cache subdirectory: %w", err)
	}
	return filepath.Join(dir, cacheKey+"."+ext), nil
}

// getContentTypeExtension maps Content-Type to file extension
func getContentTypeExtension(contentType string) string {
	contentType = strings.ToLower(strings.TrimSpace(contentType))
	if strings.HasPrefix(contentType, "image/png") {
		return "png"
	} else if strings.HasPrefix(contentType, "image/jpeg") || strings.HasPrefix(contentType, "image/jpg") {
		return "jpg"
	} else if strings.HasPrefix(contentType, "image/webp") {
		return "webp"
	}
	// Default to png for unknown image types
	return "png"
}

// getContentTypeFromExtension maps file extension to Content-Type
func getContentTypeFromExtension(ext string) string {
	switch ext {
	case "png":
		return "image/png"
	case "jpg", "jpeg":
		return "image/jpeg"
	case "webp":
		return "image/webp"
	default:
		return "image/png"
	}
}

// getCachedResponse returns cached response if available.
// normQuery is the normalized query string used to build cacheKey; used only for [CACHE DEBUG] when QCARTA_CACHE_DEBUG=1.
// Returns: (data, contentType, found)
func (cm *CacheManager) getCachedResponse(projectKey string, cacheKey string, normQuery string) ([]byte, string, bool) {
	dbg := cacheDebugEnabled()
	// Guard against short hashes
	if len(cacheKey) < 2 {
		return nil, "", false
	}
	// Try common image extensions
	extensions := []string{"png", "jpg", "jpeg", "webp"}
	for _, ext := range extensions {
		cachePath, err := cm.getCachePath(projectKey, cacheKey, ext)
		if err != nil {
			if dbg {
				log.Printf("[CACHE DEBUG] key=%s path=- result=MISS reason=get_path_err err=%v query=%s", cacheKey, err, normQuery)
			}
			continue
		}
		info, err := os.Stat(cachePath)
		if err != nil {
			if dbg {
				if os.IsNotExist(err) {
					log.Printf("[CACHE DEBUG] key=%s path=%s result=MISS reason=not_found query=%s", cacheKey, cachePath, normQuery)
				} else {
					log.Printf("[CACHE DEBUG] key=%s path=%s result=MISS reason=stat_err err=%v query=%s", cacheKey, cachePath, err, normQuery)
				}
			}
			continue
		}
		if time.Since(info.ModTime()) > getCacheTTL() {
			if dbg {
				log.Printf("[CACHE DEBUG] key=%s path=%s result=MISS reason=ttl_expired age=%s ttl=%s query=%s",
					cacheKey, cachePath, time.Since(info.ModTime()), getCacheTTL(), normQuery)
			}
			continue
		}
		data, err := os.ReadFile(cachePath)
		if err != nil {
			if dbg {
				log.Printf("[CACHE DEBUG] key=%s path=%s result=MISS reason=read_err err=%v query=%s", cacheKey, cachePath, err, normQuery)
			}
			continue
		}
		contentType := getContentTypeFromExtension(ext)
		if dbg {
			log.Printf("[CACHE DEBUG] key=%s path=%s result=HIT query=%s", cacheKey, cachePath, normQuery)
		}
		return data, contentType, true
	}
	if dbg {
		log.Printf("[CACHE DEBUG] key=%s path=- result=MISS reason=no_extension_matched query=%s", cacheKey, normQuery)
	}
	return nil, "", false
}

// saveCachedResponse saves a response to cache atomically (unique temp + rename).
// normQuery is optional; included in [CACHE DEBUG] when QCARTA_CACHE_DEBUG=1.
// Concurrent writers use distinct temp files; if another writer already created finalPath, we drop our temp and succeed.
func (cm *CacheManager) saveCachedResponse(projectKey string, cacheKey string, data []byte, contentType string, normQuery string) error {
	// Guard against short hashes
	if len(cacheKey) < 2 {
		return fmt.Errorf("cache key too short: len=%d", len(cacheKey))
	}
	ext := getContentTypeExtension(contentType)
	finalPath, err := cm.getCachePath(projectKey, cacheKey, ext)
	if err != nil {
		return err
	}
	dir := filepath.Dir(finalPath)

	// Ensure directory exists
	if err := os.MkdirAll(dir, 0755); err != nil {
		return err
	}

	// Create unique temp file in same directory (avoids concurrent clobber on fixed ".tmp")
	tmpFile, err := os.CreateTemp(dir, "*.tmp")
	if err != nil {
		return err
	}
	tmpPath := tmpFile.Name()

	if _, err := tmpFile.Write(data); err != nil {
		tmpFile.Close()
		os.Remove(tmpPath)
		return err
	}
	if err := tmpFile.Sync(); err != nil {
		tmpFile.Close()
		os.Remove(tmpPath)
		return err
	}
	if err := tmpFile.Close(); err != nil {
		os.Remove(tmpPath)
		return err
	}

	// Another writer may have finished first
	if _, statErr := os.Stat(finalPath); statErr == nil {
		os.Remove(tmpPath)
		return nil
	} else if statErr != nil && !os.IsNotExist(statErr) {
		os.Remove(tmpPath)
		return statErr
	}

	if err := os.MkdirAll(dir, 0755); err != nil {
		os.Remove(tmpPath)
		return err
	}

	if err := os.Rename(tmpPath, finalPath); err != nil {
		os.Remove(tmpPath)
		return err
	}

	if cacheDebugEnabled() {
		log.Printf("[CACHE DEBUG] key=%s path=%s result=SAVE query=%s", cacheKey, finalPath, normQuery)
	} else {
		log.Printf("[CACHE] SAVE key=%s", shortCacheKey(cacheKey))
	}
	return nil
}

// purgeCache removes cache entries by scope
// Cache structure: wms/<projectKey>/<first2>/<hash>.<ext> (png, jpg, webp, etc.)
// scope can be "all" (purge everything) or a projectKey (purge specific project)
func (cm *CacheManager) purgeCache(scope string) (int, error) {
	removed := 0
	wmsDir := filepath.Join(cm.baseDir, "wms")
	
	// Check if wms directory exists
	if _, err := os.Stat(wmsDir); os.IsNotExist(err) {
		return 0, nil
	}
	
	if scope == "all" {
		// Remove all cache entries in wms/ (all projects)
		entries, err := os.ReadDir(wmsDir)
		if err != nil {
			return 0, err
		}
		
		for _, entry := range entries {
			if entry.IsDir() {
				projectDir := filepath.Join(wmsDir, entry.Name())
				// Recursively remove all files in this project directory
				removed += cm.purgeProjectDir(projectDir)
				// Remove empty project directory
				os.Remove(projectDir)
			}
		}
		// Remove wms directory if empty
		os.Remove(wmsDir)
	} else {
		// Purge by projectKey (scope is projectKey)
		projectDir := filepath.Join(wmsDir, scope)
		if _, err := os.Stat(projectDir); os.IsNotExist(err) {
			return 0, nil // Project directory doesn't exist, nothing to remove
		}
		removed = cm.purgeProjectDir(projectDir)
		// Remove empty project directory
		os.Remove(projectDir)
	}
	
	return removed, nil
}

// purgeProjectDir recursively removes all cache files in a project directory
func (cm *CacheManager) purgeProjectDir(projectDir string) int {
	removed := 0
	entries, err := os.ReadDir(projectDir)
	if err != nil {
		return 0
	}
	
	for _, entry := range entries {
		if entry.IsDir() {
			// This is a <first2> subdirectory
			subDir := filepath.Join(projectDir, entry.Name())
			subEntries, err := os.ReadDir(subDir)
			if err != nil {
				continue
			}
			for _, subEntry := range subEntries {
				if !subEntry.IsDir() {
					// Remove all image files (png, jpg, jpeg, webp, etc.)
					name := subEntry.Name()
					if strings.HasSuffix(name, ".png") || strings.HasSuffix(name, ".jpg") || 
					   strings.HasSuffix(name, ".jpeg") || strings.HasSuffix(name, ".webp") {
						if err := os.Remove(filepath.Join(subDir, name)); err == nil {
							removed++
						}
					}
				}
			}
			// Remove empty subdirectory
			os.Remove(subDir)
		} else {
			// Direct file in project directory (shouldn't happen with new structure, but handle it)
			name := entry.Name()
			if strings.HasSuffix(name, ".png") || strings.HasSuffix(name, ".jpg") || 
			   strings.HasSuffix(name, ".jpeg") || strings.HasSuffix(name, ".webp") {
				if err := os.Remove(filepath.Join(projectDir, name)); err == nil {
					removed++
				}
			}
		}
	}
	
	return removed
}

// shouldCache determines if a request should be cached
// Checks for WMS GetMap requests and respects cache bypass flags
func shouldCache(queryParams url.Values) bool {
	// Check for global cache disable via environment variable
	if os.Getenv(envDisableCache) == "1" {
		return false
	}
	
	// Check for per-request cache bypass (case-insensitive)
	if getParamCI(queryParams, "CACHE") == "0" {
		return false
	}
	
	// Get SERVICE and REQUEST case-insensitively and uppercase the values
	request := strings.ToUpper(getParamCI(queryParams, "REQUEST"))
	service := strings.ToUpper(getParamCI(queryParams, "SERVICE"))
	
	// Only cache WMS GetMap requests
	if request == "GETMAP" && service == "WMS" {
		return true
	}
	
	return false
}

// proxyToQGIS proxies the request to QGIS Server.
// isTile: when true, skips WMS version/CRS/SRS/BBOX normalization and skips proxy-side response caching (tile pipeline handles cache per 256px tile).
func proxyToQGIS(w http.ResponseWriter, r *http.Request, queryParams url.Values, isTile bool) {
	qgisLimiter <- struct{}{}
	defer func() { <-qgisLimiter }()

	if !isTile {
		normalizeWMSProxyQuery(queryParams)
	}

	projectKey := projectKeyFromQuery(queryParams)

	// Remove CACHE, META, BUFFER (tile service controls) before proxying to QGIS
	queryParamsForProxy := make(url.Values)
	for k, v := range queryParams {
		if !isProxyControlParam(k) {
			queryParamsForProxy[k] = v
		}
	}
	
	// Build QGIS Server URL (without CACHE param)
	qgisURL := os.Getenv(envQgisServerURL) + "?" + queryParamsForProxy.Encode()
	
	// Create request to QGIS Server
	req, err := http.NewRequest("GET", qgisURL, nil)
	if err != nil {
		http.Error(w, fmt.Sprintf("Failed to create request: %v", err), http.StatusInternalServerError)
		return
	}
	
	// Copy headers that might be needed
	req.Header.Set("User-Agent", r.UserAgent())
	
	// Make request to QGIS Server
	client := &http.Client{
		Timeout: 30 * time.Second,
	}
	
	resp, err := client.Do(req)
	if err != nil {
		http.Error(w, fmt.Sprintf("Failed to proxy request: %v", err), http.StatusBadGateway)
		return
	}
	defer resp.Body.Close()
	
	// Read response body
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		http.Error(w, fmt.Sprintf("Failed to read response: %v", err), http.StatusInternalServerError)
		return
	}
	
	// Copy response headers (excluding upstream cache headers)
	upstreamCacheHeaders := map[string]bool{
		"Cache-Control": true,
		"Expires":       true,
		"ETag":          true,
	}
	for key, values := range resp.Header {
		if !upstreamCacheHeaders[key] {
			for _, value := range values {
				w.Header().Add(key, value)
			}
		}
	}
	
	// Cache if appropriate (only cache successful image responses; never for metatile upstream fetches)
	cacheKey := ""
	shouldCacheResponse := !isTile && shouldCache(queryParams) && resp.StatusCode == http.StatusOK && layerDiskCacheAllowed(projectKey)
	if shouldCacheResponse {
		// Check if response is an image
		contentType := resp.Header.Get("Content-Type")
		if strings.HasPrefix(contentType, "image/") {
			queryParamsForCache := make(url.Values)
			for k, v := range queryParams {
				if !isProxyControlParam(k) {
					queryParamsForCache[k] = v
				}
			}
			var normQ string
			cacheKey, normQ = cacheManager.generateCacheKeyAndQuery(queryParamsForCache)
			if len(cacheKey) < 2 {
				log.Printf("[CACHE] status=BYPASS project=%s key=%s path= reason=cache_key_too_short",
					projectKey, shortCacheKey(cacheKey))
				w.Header().Set("X-Cache", "BYPASS")
			} else {
				ext := getContentTypeExtension(contentType)
				cachePath, err := cacheManager.getCachePath(projectKey, cacheKey, ext)
				if err != nil {
					log.Printf("[CACHE] status=BYPASS project=%s key=%s path= reason=get_cache_path err=%v",
						projectKey, shortCacheKey(cacheKey), err)
					w.Header().Set("X-Cache", "BYPASS")
				} else {
					// Save to cache atomically
					if err := cacheManager.saveCachedResponse(projectKey, cacheKey, body, contentType, normQ); err != nil {
						log.Printf("[CACHE] status=BYPASS project=%s key=%s path=%s reason=save err=%v",
							projectKey, shortCacheKey(cacheKey), cachePath, err)
						w.Header().Set("X-Cache", "BYPASS")
					} else {
						log.Printf("[CACHE] status=MISS project=%s key=%s path=%s",
							projectKey, shortCacheKey(cacheKey), cachePath)
						w.Header().Set("X-Cache", "MISS")
					}
				}
			}
		} else {
			// Not caching: response is not an image (likely an error XML)
			log.Printf("[CACHE] status=BYPASS project=%s key=%s path= reason=not_image content_type=%s status=%d",
				projectKey, shortCacheKey(cacheKey), contentType, resp.StatusCode)
			w.Header().Set("X-Cache", "BYPASS")
		}
	} else {
		// Not caching: cache disabled or not a cacheable request
		w.Header().Set("X-Cache", "BYPASS")
	}
	
	// Set status code
	w.WriteHeader(resp.StatusCode)
	
	// Write response body
	w.Write(body)
}

// handleService handles WMS proxy requests (shared by /api/wms and legacy /mproxy/service).
func handleService(w http.ResponseWriter, r *http.Request) {
	if r.Method != "GET" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	log.Printf("[ROUTE] WMS query=%s", r.URL.RawQuery)
	log.Printf("[ROUTE] referer=%s", r.Referer())

	queryParams := r.URL.Query()
	normForCache := cloneURLValues(queryParams)
	normalizeWMSProxyQuery(normForCache)

	projectKey := projectKeyFromQuery(normForCache)

	// Check cache first if this is a cacheable request (key matches upstream-normalized WMS params)
	if shouldCache(normForCache) && layerDiskCacheAllowed(projectKey) {
		queryParamsForCache := make(url.Values)
		for k, v := range normForCache {
			if !isProxyControlParam(k) {
				queryParamsForCache[k] = v
			}
		}
		cacheKey, normQuery := cacheManager.generateCacheKeyAndQuery(queryParamsForCache)
		if cacheDebugEnabled() {
			log.Printf("[CACHE DEBUG] key=%s project=%s path=- result=LOOKUP query=%s", cacheKey, projectKey, normQuery)
		}
		if len(cacheKey) < 2 {
			log.Printf("[CACHE] status=BYPASS project=%s key=%s path= reason=cache_key_too_short",
				projectKey, shortCacheKey(cacheKey))
			atomic.AddInt64(&cacheBypass, 1)
		} else {
			if cachedData, contentType, found := cacheManager.getCachedResponse(projectKey, cacheKey, normQuery); found {
				// Determine cache path for logging (try to find the actual file)
				ext := getContentTypeExtension(contentType)
				cachePath, err := cacheManager.getCachePath(projectKey, cacheKey, ext)
				if err != nil {
					log.Printf("[CACHE] status=BYPASS project=%s key=%s path= reason=get_cache_path err=%v",
						projectKey, shortCacheKey(cacheKey), err)
					atomic.AddInt64(&cacheBypass, 1)
				} else {
					// Serve from cache
					w.Header().Set("Content-Type", contentType)
					w.Header().Set("Cache-Control", "public, max-age=86400")
					w.Header().Set("X-Cache", "HIT")
					w.WriteHeader(http.StatusOK)
					w.Write(cachedData)
					log.Printf("[CACHE] status=HIT project=%s key=%s path=%s",
						projectKey, shortCacheKey(cacheKey), cachePath)
					atomic.AddInt64(&cacheHits, 1)
					return
				}
			} else {
				atomic.AddInt64(&cacheMisses, 1)
			}
		}
	} else {
		atomic.AddInt64(&cacheBypass, 1)
	}

	// Proxy to QGIS Server (normalizes queryParams in place before upstream request)
	proxyToQGIS(w, r, queryParams, false)
}

// metatileBBoxFloats unions the block [metaX, metaX+effMeta) × [metaY, metaY+effMeta) using only tile indices (NW + SE corner tiles).
func metatileBBoxFloats(metaX, metaY, effMeta, z int) (minx, miny, maxx, maxy float64) {
	minxa, _, _, maxya := tileToBBox(metaX, metaY, z)
	_, minyb, maxxb, _ := tileToBBox(metaX+effMeta-1, metaY+effMeta-1, z)
	return minxa, minyb, maxxb, maxya
}

func expandBBoxWithBuffer(minx, miny, maxx, maxy float64, buffer int, z int) string {
	res := (2 * mercatorExtent) / (float64(tileSize) * math.Pow(2, float64(z)))
	if buffer > 0 {
		bufferMeters := float64(buffer) * res
		minx -= bufferMeters
		maxx += bufferMeters
		miny -= bufferMeters
		maxy += bufferMeters
	}
	epsilon := res * 1.0
	minx -= epsilon
	maxx += epsilon
	miny -= epsilon
	maxy += epsilon
	return fmt.Sprintf("%f,%f,%f,%f", minx, miny, maxx, maxy)
}

func buildMetaTileQuery(base url.Values, metaX, metaY, effMeta, z, buffer int) url.Values {
	metaQ := cloneURLValues(base)
	for _, k := range []string{"SERVICE", "REQUEST", "VERSION", "CRS", "SRS", "BBOX", "WIDTH", "HEIGHT", "FORMAT", "FORMAT_OPTIONS", "TRANSPARENT"} {
		delKeyCI(metaQ, k)
	}
	metaPixels := tileSize * effMeta
	requestPixels := metaPixels + 2*buffer
	minx, miny, maxx, maxy := metatileBBoxFloats(metaX, metaY, effMeta, z)
	bboxStr := expandBBoxWithBuffer(minx, miny, maxx, maxy, buffer, z)
	metaQ.Set("SERVICE", "WMS")
	metaQ.Set("REQUEST", "GetMap")
	metaQ.Set("VERSION", "1.1.1")
	metaQ.Set("SRS", "EPSG:3857")
	metaQ.Set("BBOX", bboxStr)
	metaQ.Set("WIDTH", strconv.Itoa(requestPixels))
	metaQ.Set("HEIGHT", strconv.Itoa(requestPixels))
	metaQ.Set("FORMAT", "image/png")
	metaQ.Set("FORMAT_OPTIONS", "dpi:96")
	metaQ.Set("TRANSPARENT", "true")
	return metaQ
}

// handleXYZTiles serves /api/tiles/{z}/{x}/{y}.png via metatile WMS fetch, slice, and per-tile disk cache (WMS normalization is never applied).
func handleXYZTiles(w http.ResponseWriter, r *http.Request) {
	if r.Method != "GET" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	parts := strings.Split(strings.TrimPrefix(r.URL.Path, "/api/tiles/"), "/")
	if len(parts) < 3 {
		http.Error(w, "Invalid tile path", http.StatusBadRequest)
		return
	}

	z, err := strconv.Atoi(parts[0])
	if err != nil || z < 0 || z > 30 {
		http.Error(w, "Invalid z", http.StatusBadRequest)
		return
	}
	x, err := strconv.Atoi(parts[1])
	if err != nil {
		http.Error(w, "Invalid x", http.StatusBadRequest)
		return
	}

	yPart := parts[2]
	if !strings.HasSuffix(strings.ToLower(yPart), ".png") {
		http.Error(w, "Invalid tile path (expected .png)", http.StatusBadRequest)
		return
	}
	yStr := strings.TrimSuffix(strings.ToLower(yPart), ".png")
	y, err := strconv.Atoi(yStr)
	if err != nil {
		http.Error(w, "Invalid y", http.StatusBadRequest)
		return
	}

	tilesPerAxis := int(1 << uint(z))
	if x < 0 || x >= tilesPerAxis || y < 0 || y >= tilesPerAxis {
		http.Error(w, "Tile out of range", http.StatusBadRequest)
		return
	}

	log.Printf("[ROUTE] TILE z=%d x=%d y=%d raw_query=%s", z, x, y, r.URL.RawQuery)
	log.Printf("[ROUTE] referer=%s", r.Referer())

	base := r.URL.Query()
	metaSize := getMetaTileSizeForQuery(base)
	metaX := (x / metaSize) * metaSize
	metaY := (y / metaSize) * metaSize

	maxSpanX := tilesPerAxis - metaX
	maxSpanY := tilesPerAxis - metaY
	effMeta := metaSize
	if maxSpanX < effMeta {
		effMeta = maxSpanX
	}
	if maxSpanY < effMeta {
		effMeta = maxSpanY
	}
	if effMeta < 1 {
		effMeta = 1
	}

	tileQ := buildSingleTileWMSQuery(base, x, y, z)
	projectKey := projectKeyFromQuery(tileQ)
	tileKeyMaterial := urlValuesForTileCacheKey(tileQ)

	if shouldCache(tileQ) && layerDiskCacheAllowed(projectKey) {
		cacheKey, tileNormQuery := cacheManager.generateCacheKeyAndQuery(tileKeyMaterial)
		lookupPath := "-"
		if len(cacheKey) >= 2 {
			if p, err := cacheManager.getCachePath(projectKey, cacheKey, "png"); err == nil {
				lookupPath = p
			}
		}
		log.Printf("[TILE DEBUG] z=%d x=%d y=%d key=%s path=%s (per-tile disk cache)", z, x, y, shortCacheKey(cacheKey), lookupPath)
		if len(cacheKey) >= 2 {
			if cached, contentType, ok := cacheManager.getCachedResponse(projectKey, cacheKey, tileNormQuery); ok {
				extHit := getContentTypeExtension(contentType)
				hitPath := lookupPath
				if hp, err := cacheManager.getCachePath(projectKey, cacheKey, extHit); err == nil {
					hitPath = hp
				}
				log.Printf("[TILE DEBUG] key=%s path=%s result=HIT", shortCacheKey(cacheKey), hitPath)
				log.Printf("[CACHE] HIT key=%s", shortCacheKey(cacheKey))
				w.Header().Set("Content-Type", contentType)
				w.Header().Set("Cache-Control", "public, max-age=86400")
				w.Header().Set("X-Cache", "HIT")
				w.WriteHeader(http.StatusOK)
				_, _ = w.Write(cached)
				atomic.AddInt64(&cacheHits, 1)
				return
			}
			log.Printf("[TILE DEBUG] key=%s path=%s result=MISS", shortCacheKey(cacheKey), lookupPath)
			log.Printf("[CACHE] MISS key=%s", shortCacheKey(cacheKey))
		} else {
			log.Printf("[TILE DEBUG] key=%s path=%s result=MISS", shortCacheKey(cacheKey), lookupPath)
		}
		atomic.AddInt64(&cacheMisses, 1)
	}

	buffer := getMetaTileBufferForQuery(base)
	log.Printf("[METATILE] z=%d x=%d y=%d size=%d buffer=%d", z, x, y, effMeta, buffer)

	metaQ := buildMetaTileQuery(base, metaX, metaY, effMeta, z, buffer)
	if bbox := metaQ.Get("BBOX"); bbox != "" {
		var a, b, c, d float64
		if n, _ := fmt.Sscanf(bbox, "%f,%f,%f,%f", &a, &b, &c, &d); n == 4 {
			log.Printf("[BBOX] z=%d x=%d y=%d bbox=%.6f,%.6f,%.6f,%.6f", z, x, y, a, b, c, d)
		}
	}

	rr := httptest.NewRecorder()
	proxyToQGIS(rr, r, metaQ, true)

	if rr.Code != http.StatusOK {
		http.Error(w, "Upstream error", rr.Code)
		return
	}

	slices, err := sliceMetaTile(rr.Body.Bytes(), effMeta, buffer)
	if err != nil && buffer > 0 {
		log.Printf("[METATILE] buffer slice failed, falling back without buffer: %v", err)
		metaQ2 := buildMetaTileQuery(base, metaX, metaY, effMeta, z, 0)
		rr2 := httptest.NewRecorder()
		proxyToQGIS(rr2, r, metaQ2, true)
		if rr2.Code != http.StatusOK {
			http.Error(w, "Upstream error", rr2.Code)
			return
		}
		slices, err = sliceMetaTile(rr2.Body.Bytes(), effMeta, 0)
	}
	if err != nil {
		http.Error(w, "Tile slicing failed", http.StatusInternalServerError)
		return
	}

	dx := x - metaX
	dy := y - metaY
	index := dy*effMeta + dx
	if index < 0 || index >= len(slices) {
		http.Error(w, "Tile index error", http.StatusInternalServerError)
		return
	}

	if shouldCache(tileQ) {
		for ty := 0; ty < effMeta; ty++ {
			for tx := 0; tx < effMeta; tx++ {
				txw, tyw := metaX+tx, metaY+ty
				subQ := buildSingleTileWMSQuery(base, txw, tyw, z)
				pk := projectKeyFromQuery(subQ)
				if !layerDiskCacheAllowed(pk) {
					continue
				}
				tileMat := urlValuesForTileCacheKey(subQ)
				ck, tileNQ := cacheManager.generateCacheKeyAndQuery(tileMat)
				if len(ck) < 2 {
					continue
				}
				si := ty*effMeta + tx
				if si < 0 || si >= len(slices) {
					continue
				}
				savePath := "-"
				if sp, err := cacheManager.getCachePath(pk, ck, "png"); err == nil {
					savePath = sp
				}
				if err := cacheManager.saveCachedResponse(pk, ck, slices[si], "image/png", tileNQ); err != nil {
					log.Printf("[CACHE] save tile failed key=%s err=%v", shortCacheKey(ck), err)
				} else {
					log.Printf("[TILE DEBUG] key=%s path=%s result=SAVE", shortCacheKey(ck), savePath)
				}
			}
		}
	}

	w.Header().Set("Content-Type", "image/png")
	w.Header().Set("Cache-Control", "public, max-age=86400")
	w.Header().Set("X-Cache", "MISS")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write(slices[index])
}

// handleWMTS is a placeholder; future implementation can parse TILECOL/TILEROW/TILEMATRIX and reuse CacheManager.
func handleWMTS(w http.ResponseWriter, r *http.Request) {
	http.Error(w, "WMTS not yet implemented", http.StatusNotImplemented)
}

func handleHealth(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)
	w.Write([]byte("OK"))
}

func handleMetrics(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	fmt.Fprintf(w, `{
		"cache_hits": %d,
		"cache_misses": %d,
		"cache_bypass": %d
	}`, atomic.LoadInt64(&cacheHits), atomic.LoadInt64(&cacheMisses), atomic.LoadInt64(&cacheBypass))
}

// handleCachePurge handles POST /admin/cache/purge
func handleCachePurge(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	
	// Check Bearer token
	authHeader := r.Header.Get("Authorization")
	if authHeader == "" {
		http.Error(w, "Unauthorized: Missing Authorization header", http.StatusUnauthorized)
		return
	}
	
	expectedToken := os.Getenv(envBearerToken)
	if expectedToken == "" {
		log.Printf("Warning: %s environment variable not set", envBearerToken)
		http.Error(w, "Server configuration error", http.StatusInternalServerError)
		return
	}
	
	// Extract Bearer token
	parts := strings.Split(authHeader, " ")
	if len(parts) != 2 || parts[0] != "Bearer" {
		http.Error(w, "Unauthorized: Invalid Authorization header format", http.StatusUnauthorized)
		return
	}
	
	if parts[1] != expectedToken {
		http.Error(w, "Unauthorized: Invalid token", http.StatusUnauthorized)
		return
	}
	
	// Parse scope from request body
	var scope string
	body, err := io.ReadAll(r.Body)
	if err != nil {
		http.Error(w, "Failed to read request body", http.StatusBadRequest)
		return
	}
	
	scope = strings.TrimSpace(string(body))
	if scope == "" {
		scope = "all" // Default to all if not specified
	}
	
	// Purge cache
	removed, err := cacheManager.purgeCache(scope)
	if err != nil {
		http.Error(w, fmt.Sprintf("Failed to purge cache: %v", err), http.StatusInternalServerError)
		return
	}
	
	// Return success response
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	fmt.Fprintf(w, `{"success": true, "removed": %d, "scope": "%s"}`, removed, scope)
}

func main() {
	cacheBase := getCacheDir()
	cacheManager = NewCacheManager(cacheBase)
	initLayerDiskCachePolicyFromEnv()

	// API routes (modern)
	http.HandleFunc("/api/wms", handleService)
	http.HandleFunc("/api/tiles/", handleXYZTiles)
	http.HandleFunc("/api/wmts", handleWMTS)

	// Legacy support
	http.HandleFunc("/mproxy/service", handleService)

	// Health + metrics
	http.HandleFunc("/api/health", handleHealth)
	http.HandleFunc("/api/metrics", handleMetrics)
	http.HandleFunc("/health", handleHealth)

	http.HandleFunc("/admin/cache/purge", handleCachePurge)

	log.Printf("Starting qcarta-tiles server on %s", serverPort)
	log.Printf("Endpoints:")
	log.Printf("  WMS:     http://%s/api/wms", serverPort)
	log.Printf("  Tiles:   http://%s/api/tiles/{z}/{x}/{y}.png  ([TILE DEBUG] in logs includes path= for per-tile disk reuse)", serverPort)
	log.Printf("  WMTS:    http://%s/api/wmts", serverPort)
	log.Printf("  Health:  http://%s/api/health", serverPort)
	log.Printf("  Metrics: http://%s/api/metrics", serverPort)
	log.Printf("  Legacy:  http://%s/mproxy/service", serverPort)
	log.Printf("QGIS Server: %s", os.Getenv(envQgisServerURL))
	log.Printf("Cache directory: %s", cacheBase)
	log.Printf("Cache TTL: %s (QCARTA_CACHE_TTL seconds; default 7d if unset or invalid)", getCacheTTL())
	if cacheDebugEnabled() {
		log.Printf("QCARTA_CACHE_DEBUG=1: logging [CACHE DEBUG] with full cache key, normalized query string, disk paths, HIT/MISS/SAVE")
	}
	log.Printf("Metatile: size=%d buffer=%d (recommended: QCARTA_METATILE_SIZE=4 QCARTA_METATILE_BUFFER=64)", getMetaTileSize(), getMetaTileBuffer())
	if os.Getenv(envDisableCache) == "1" {
		log.Printf("Cache is DISABLED (QCARTA_DISABLE_CACHE=1)")
	} else {
		log.Printf("Cache is ENABLED")
	}
	log.Printf("Per-layer disk cache: qcarta-tiles reads CACHE_ENABLED from env.php under layers/ (override path with QCARTA_WWW_DIR)")

	if err := http.ListenAndServe(serverPort, nil); err != nil {
		log.Fatalf("Server failed to start: %v", err)
	}
}
