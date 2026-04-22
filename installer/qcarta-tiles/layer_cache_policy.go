package main

import (
	"log"
	"net/url"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"time"
)

// Disk cache is keyed by projectKey (same as wms/<projectKey>/). layers.php writes
// const CACHE_ENABLED = true|false into each layers/{id}/env.php — we read that so no DB connection is required.

const (
	envQCARTaWWWDir        = "QCARTA_WWW_DIR"
	envQCARTaPolicyRefresh = "QCARTA_LAYER_CACHE_REFRESH_SEC"
)

var (
	reEnvQGISPlain    = regexp.MustCompile(`(?i)const\s+QGIS_FILENAME\s*=\s*'([^']*)'`)
	reEnvQGISEnc      = regexp.MustCompile(`(?i)const\s+QGIS_FILENAME_ENCODED\s*=\s*'([^']*)'`)
	reEnvCacheEnabled = regexp.MustCompile(`(?i)const\s+CACHE_ENABLED\s*=\s*(true|false)\s*;`)
)

type layerDiskCachePolicy struct {
	wwwDir         string
	refreshEvery   time.Duration
	mu             sync.RWMutex
	byProjectKey   map[string]bool // true = allow disk cache for this projectKey
	lastRebuild    time.Time
	rebuildMu      sync.Mutex
}

var globalLayerDiskPolicy *layerDiskCachePolicy

func initLayerDiskCachePolicyFromEnv() {
	www := strings.TrimSpace(os.Getenv(envQCARTaWWWDir))
	if www == "" {
		www = "/var/www/html"
	}
	refresh := 5 * time.Second
	if s := strings.TrimSpace(os.Getenv(envQCARTaPolicyRefresh)); s != "" {
		if n, err := strconv.Atoi(s); err == nil && n >= 2 {
			refresh = time.Duration(n) * time.Second
		}
	}
	globalLayerDiskPolicy = &layerDiskCachePolicy{
		wwwDir:       www,
		refreshEvery: refresh,
		byProjectKey: make(map[string]bool),
	}
	log.Printf("Layer disk cache policy: reading CACHE_ENABLED from %s/layers/*/env.php (refresh every %s)", www, refresh)
}

func layerDiskCacheAllowed(projectKey string) bool {
	if globalLayerDiskPolicy == nil {
		return true
	}
	if projectKey == "" || projectKey == "unknown" {
		return true
	}
	globalLayerDiskPolicy.maybeRebuild()
	globalLayerDiskPolicy.mu.RLock()
	defer globalLayerDiskPolicy.mu.RUnlock()
	v, ok := globalLayerDiskPolicy.byProjectKey[projectKey]
	if !ok {
		// Not a published QCarta layer under layers/{id}/ — leave default (cache on).
		return true
	}
	return v
}

func (p *layerDiskCachePolicy) maybeRebuild() {
	now := time.Now()
	p.mu.RLock()
	stale := now.Sub(p.lastRebuild) >= p.refreshEvery
	p.mu.RUnlock()
	if !stale {
		return
	}
	p.rebuildMu.Lock()
	defer p.rebuildMu.Unlock()
	p.mu.RLock()
	stale2 := time.Since(p.lastRebuild) >= p.refreshEvery
	p.mu.RUnlock()
	if !stale2 {
		return
	}
	next, err := p.rebuildIndex()
	if err != nil {
		log.Printf("Layer disk cache policy: rebuild failed: %v (set QCARTA_WWW_DIR to the QCarta document root that contains layers/)", err)
		p.mu.Lock()
		p.lastRebuild = time.Now()
		p.mu.Unlock()
		return
	}
	p.mu.Lock()
	p.byProjectKey = next
	p.lastRebuild = time.Now()
	p.mu.Unlock()
	if len(next) == 0 {
		log.Printf("Layer disk cache policy: no layer env.php entries indexed under %s/layers — check QCARTA_WWW_DIR", p.wwwDir)
	}
}

func parseCacheEnabledFromEnvPHP(src string) bool {
	if m := reEnvCacheEnabled.FindStringSubmatch(src); len(m) > 1 {
		return strings.EqualFold(strings.TrimSpace(m[1]), "true")
	}
	// Older env.php without constant: keep previous behaviour (allow disk cache).
	return true
}

func parseQGISMapPathFromEnvPHP(src string) string {
	src = strings.TrimPrefix(src, "\ufeff")
	if m := reEnvQGISPlain.FindStringSubmatch(src); len(m) > 1 {
		v := strings.TrimSpace(m[1])
		if v != "" && !strings.EqualFold(v, "none") {
			return v
		}
	}
	if m := reEnvQGISEnc.FindStringSubmatch(src); len(m) > 1 {
		v := strings.TrimSpace(m[1])
		if v == "" || strings.EqualFold(v, "none") {
			return ""
		}
		dec, err := url.QueryUnescape(v)
		if err != nil {
			dec = v
		}
		if dec != "" && !strings.EqualFold(dec, "none") {
			return dec
		}
	}
	return ""
}

func projectKeyFromMapFilesystemPath(mapPath string) string {
	q := url.Values{}
	q.Set("map", mapPath)
	return projectKeyFromQuery(q)
}

func (p *layerDiskCachePolicy) rebuildIndex() (map[string]bool, error) {
	out := make(map[string]bool)
	layersRoot := filepath.Join(p.wwwDir, "layers")
	entries, err := os.ReadDir(layersRoot)
	if err != nil {
		return out, err
	}
	for _, e := range entries {
		if !e.IsDir() {
			continue
		}
		layerID, err := strconv.Atoi(e.Name())
		if err != nil || layerID <= 0 {
			continue
		}
		envPath := filepath.Join(layersRoot, e.Name(), "env.php")
		b, err := os.ReadFile(envPath)
		if err != nil || len(b) == 0 {
			continue
		}
		src := string(b)
		mapPath := parseQGISMapPathFromEnvPHP(src)
		if mapPath == "" {
			continue
		}
		pk := projectKeyFromMapFilesystemPath(mapPath)
		if pk == "" || pk == "unknown" {
			continue
		}
		allowed := parseCacheEnabledFromEnvPHP(src)
		if prev, exists := out[pk]; exists {
			out[pk] = prev && allowed
		} else {
			out[pk] = allowed
		}
	}
	return out, nil
}
