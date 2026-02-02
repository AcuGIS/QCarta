package main

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"os"
	"path"
	"path/filepath"
	"sort"
	"strings"
	"time"
)

const (
	// QGIS Server endpoint
	envQgisServerURL = "QGIS_SERVER_URL"
	
	// Cache directory
	cacheDir = "/var/www/data/qcarta-tiles/cache_data"
	
	// Server port (listen on localhost only)
	serverPort = "127.0.0.1:8011"
	
	// Bearer token for cache purge (set via environment variable)
	envBearerToken = "QCARTA_CACHE_PURGE_TOKEN"
	
	// Environment variable to disable cache
	envDisableCache = "QCARTA_DISABLE_CACHE"
)

// Global cache manager instance (created once at startup)
var cacheManager *CacheManager

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

// generateCacheKey creates a cache key from sorted, normalized query parameters
func (cm *CacheManager) generateCacheKey(queryParams url.Values) string {
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
	
	queryString := strings.Join(parts, "&")
	
	// Create SHA256 hash of the normalized query string
	hash := sha256.Sum256([]byte(queryString))
	return hex.EncodeToString(hash[:])
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

// getCachedResponse returns cached response if available
// Returns: (data, contentType, found)
func (cm *CacheManager) getCachedResponse(projectKey string, cacheKey string) ([]byte, string, bool) {
	// Guard against short hashes
	if len(cacheKey) < 2 {
		return nil, "", false
	}
	// Try common image extensions
	extensions := []string{"png", "jpg", "jpeg", "webp"}
	for _, ext := range extensions {
		cachePath, err := cm.getCachePath(projectKey, cacheKey, ext)
		if err != nil {
			continue
		}
		data, err := os.ReadFile(cachePath)
		if err == nil {
			contentType := getContentTypeFromExtension(ext)
			return data, contentType, true
		}
	}
	return nil, "", false
}

// saveCachedResponse saves a response to cache atomically (tmp + rename)
func (cm *CacheManager) saveCachedResponse(projectKey string, cacheKey string, data []byte, contentType string) error {
	// Guard against short hashes
	if len(cacheKey) < 2 {
		return fmt.Errorf("cache key too short: len=%d", len(cacheKey))
	}
	ext := getContentTypeExtension(contentType)
	cachePath, err := cm.getCachePath(projectKey, cacheKey, ext)
	if err != nil {
		return err
	}
	// Create temporary file in the same directory
	tmpPath := cachePath + ".tmp"
	
	// Write to temporary file
	if err := os.WriteFile(tmpPath, data, 0644); err != nil {
		return fmt.Errorf("failed to write temp file: %w", err)
	}
	
	// Sync to disk for reliability
	f, err := os.OpenFile(tmpPath, os.O_RDWR, 0644)
	if err != nil {
		os.Remove(tmpPath)
		return fmt.Errorf("failed to open temp file for sync: %w", err)
	}
	if err := f.Sync(); err != nil {
		f.Close()
		os.Remove(tmpPath)
		return fmt.Errorf("failed to sync temp file: %w", err)
	}
	f.Close()
	
	// Atomically rename temp file to final cache file
	if err := os.Rename(tmpPath, cachePath); err != nil {
		// Clean up temp file on error
		os.Remove(tmpPath)
		return fmt.Errorf("failed to rename temp file: %w", err)
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

// proxyToQGIS proxies the request to QGIS Server
func proxyToQGIS(w http.ResponseWriter, r *http.Request, queryParams url.Values) {
	projectKey := projectKeyFromQuery(queryParams)
	
	// Remove CACHE parameter before proxying (don't send it to QGIS)
	queryParamsForProxy := make(url.Values)
	for k, v := range queryParams {
		if k != "CACHE" {
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
	
	// Cache if appropriate (only cache successful image responses)
	cacheKey := ""
	shouldCacheResponse := shouldCache(queryParams) && resp.StatusCode == http.StatusOK
	if shouldCacheResponse {
		// Check if response is an image
		contentType := resp.Header.Get("Content-Type")
		if strings.HasPrefix(contentType, "image/") {
			// Remove CACHE param before generating cache key
			queryParamsForCache := make(url.Values)
			for k, v := range queryParams {
				if k != "CACHE" {
					queryParamsForCache[k] = v
				}
			}
			cacheKey = cacheManager.generateCacheKey(queryParamsForCache)
			if len(cacheKey) < 2 {
				log.Printf("CACHE BYPASS cache key too short")
				w.Header().Set("X-Cache", "BYPASS")
			} else {
				ext := getContentTypeExtension(contentType)
				cachePath, err := cacheManager.getCachePath(projectKey, cacheKey, ext)
				if err != nil {
					log.Printf("CACHE BYPASS %s %v", cacheKey, err)
					w.Header().Set("X-Cache", "BYPASS")
				} else {
					// Save to cache atomically
					if err := cacheManager.saveCachedResponse(projectKey, cacheKey, body, contentType); err != nil {
						log.Printf("CACHE BYPASS %s %s %v", cacheKey, cachePath, err)
						w.Header().Set("X-Cache", "BYPASS")
					} else {
						log.Printf("CACHE MISS project=%s %s %s", projectKey, cacheKey, cachePath)
						w.Header().Set("X-Cache", "MISS")
					}
				}
			}
		} else {
			// Not caching: response is not an image (likely an error XML)
			log.Printf("CACHE BYPASS response is not an image (Content-Type: %s, Status: %d)", contentType, resp.StatusCode)
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

// handleService handles /mproxy/service requests
func handleService(w http.ResponseWriter, r *http.Request) {
	if r.Method != "GET" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	
	queryParams := r.URL.Query()
	projectKey := projectKeyFromQuery(queryParams)
	
	// Check cache first if this is a cacheable request
	if shouldCache(queryParams) {
		// Remove CACHE param before generating cache key
		queryParamsForCache := make(url.Values)
		for k, v := range queryParams {
			if k != "CACHE" {
				queryParamsForCache[k] = v
			}
		}
		cacheKey := cacheManager.generateCacheKey(queryParamsForCache)
		if len(cacheKey) < 2 {
			log.Printf("CACHE BYPASS cache key too short")
		} else {
			if cachedData, contentType, found := cacheManager.getCachedResponse(projectKey, cacheKey); found {
				// Determine cache path for logging (try to find the actual file)
				ext := getContentTypeExtension(contentType)
				cachePath, err := cacheManager.getCachePath(projectKey, cacheKey, ext)
				if err != nil {
					log.Printf("CACHE BYPASS %s %v", cacheKey, err)
				} else {
					// Serve from cache
					w.Header().Set("Content-Type", contentType)
					w.Header().Set("Cache-Control", "public, max-age=86400")
					w.Header().Set("X-Cache", "HIT")
					w.WriteHeader(http.StatusOK)
					w.Write(cachedData)
					log.Printf("CACHE HIT project=%s %s %s", projectKey, cacheKey, cachePath)
					return
				}
			}
		}
	}
	
	// Proxy to QGIS Server
	proxyToQGIS(w, r, queryParams)
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
	// Initialize global cache manager once at startup
	cacheManager = NewCacheManager(cacheDir)
	
	// Setup routes
	http.HandleFunc("/mproxy/service", handleService)
	http.HandleFunc("/admin/cache/purge", handleCachePurge)
	
	// Health check endpoint
	http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("OK"))
	})
	
	log.Printf("Starting qcarta-tiles server on %s", serverPort)
	log.Printf("QGIS Server: %s", os.Getenv(envQgisServerURL))
	log.Printf("Cache directory: %s", cacheDir)
	if os.Getenv(envDisableCache) == "1" {
		log.Printf("Cache is DISABLED (QCARTA_DISABLE_CACHE=1)")
	} else {
		log.Printf("Cache is ENABLED")
	}
	
	if err := http.ListenAndServe(serverPort, nil); err != nil {
		log.Fatalf("Server failed to start: %v", err)
	}
}
