# QCarta Tile Service Verification

## Prerequisites

1. Go service is built and installed on remote server
2. Systemd service is running
3. Apache is configured and running
4. QGIS Server is accessible
5. Bearer token is set in systemd service

## Pre-Migration Verification (MapProxy Removal)

### 0.1. Verify MapProxy Service is Stopped/Disabled

```bash
sudo systemctl status mapproxy
```

**Expected Output**:
```
● mapproxy.service
     Loaded: loaded (...)
     Active: inactive (dead) since ...
```

**Or if service doesn't exist**:
```
Unit mapproxy.service could not be found.
```

**Verification**: MapProxy service should be stopped or not exist

### 0.2. Verify MapProxy is Not Running (Process Check)

```bash
ps aux | grep -i mapproxy | grep -v grep
```

**Expected Output**:
```
(no output - no MapProxy processes running)
```

**Verification**: No MapProxy processes should be found

### 0.3. Verify Port 8011 is Not in Use by MapProxy

```bash
sudo netstat -tlnp | grep :8011
# OR
sudo ss -tlnp | grep :8011
```

**Expected Output (before qcarta-tiles starts)**:
```
(no output - port should be free)
```

**Expected Output (after qcarta-tiles starts)**:
```
tcp        0      0 127.0.0.1:8011          0.0.0.0:*               LISTEN      12345/qcarta-tiles
```

**Verification**: 
- Port 8011 should only be bound by qcarta-tiles (PID shown)
- Should listen on 127.0.0.1:8011 (not 0.0.0.0:8011)

### 0.4. Verify No MapProxy Docker Container

```bash
docker ps -a | grep -i mapproxy
```

**Expected Output**:
```
(no output - no MapProxy containers)
```

**Verification**: No MapProxy Docker containers should exist

### 0.5. Verify MapProxy Systemd Unit Conflicts

```bash
systemctl list-unit-files | grep mapproxy
```

**Expected Output**:
```
(no output - no MapProxy unit files)
```

**Or if unit file exists but is disabled**:
```
mapproxy.service                          disabled
```

**Verification**: MapProxy unit should not exist or be disabled

## Verification Commands

### 1. Check Service Status

```bash
sudo systemctl status qcarta-tiles
```

**Expected Output**:
```
● qcarta-tiles.service - QCarta Tile Service (replaces MapProxy)
     Loaded: loaded (/etc/systemd/system/qcarta-tiles.service; enabled; vendor preset: enabled)
     Active: active (running) since ...
```

**Verification**: Service should be active and running

### 1.1. Verify Port 8011 is Bound by qcarta-tiles Only

```bash
sudo netstat -tlnp | grep :8011
# OR
sudo ss -tlnp | grep :8011
```

**Expected Output**:
```
tcp        0      0 127.0.0.1:8011          0.0.0.0:*               LISTEN      12345/qcarta-tiles
```

**Verification**: 
- Port 8011 should be bound to 127.0.0.1:8011 (localhost only)
- Process name should be `qcarta-tiles`
- Only ONE process should be listening on port 8011

### 2. Check Service Health

```bash
curl http://127.0.0.1:8011/health
```

**Expected Output**:
```
OK
```

### 3. Test WMS GetCapabilities (Not Cached)

```bash
curl "http://127.0.0.1:8011/mproxy/service?SERVICE=WMS&REQUEST=GetCapabilities" | head -20
```

**Expected Output**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<WMS_Capabilities version="1.1.0">
  <Service>
    <Name>WMS</Name>
    ...
```

**Verification**: Response should be XML, NOT cached (no cache file created)

### 4. Test WMS GetMap (Should Cache)

```bash
curl -o /tmp/test_tile.png "http://127.0.0.1:8011/mproxy/service?SERVICE=WMS&REQUEST=GetMap&VERSION=1.1.0&LAYERS=Bee_Map.Fields&STYLES=&SRS=EPSG:3857&BBOX=1000000,5000000,2000000,6000000&WIDTH=256&HEIGHT=256&FORMAT=image/png"
file /tmp/test_tile.png
```

**Expected Output**:
```
/tmp/test_tile.png: PNG image data, 256 x 256, ...
```

**Verification**: 
- File should be valid PNG
- Cache file should be created in `/var/www/data/mapproxy/cache_data/`

### 5. Verify Cache Hit

```bash
# Request same tile again
curl -o /tmp/test_tile2.png "http://127.0.0.1:8011/mproxy/service?SERVICE=WMS&REQUEST=GetMap&VERSION=1.1.0&LAYERS=Bee_Map.Fields&STYLES=&SRS=EPSG:3857&BBOX=1000000,5000000,2000000,6000000&WIDTH=256&HEIGHT=256&FORMAT=image/png"
diff /tmp/test_tile.png /tmp/test_tile2.png
```

**Expected Output**:
```
(no output - files are identical)
```

**Verification**: Second request should be served from cache (faster response)

### 6. Test Cache Key with FILTER Parameter

```bash
# Request with FILTER parameter
curl -o /tmp/test_filter1.png "http://127.0.0.1:8011/mproxy/service?SERVICE=WMS&REQUEST=GetMap&VERSION=1.1.0&LAYERS=Bee_Map.Fields&FILTER=Bee_Map.Fields:name%20IN%20('test')&STYLES=&SRS=EPSG:3857&BBOX=1000000,5000000,2000000,6000000&WIDTH=256&HEIGHT=256&FORMAT=image/png"

# Request with different FILTER (should create different cache entry)
curl -o /tmp/test_filter2.png "http://127.0.0.1:8011/mproxy/service?SERVICE=WMS&REQUEST=GetMap&VERSION=1.1.0&LAYERS=Bee_Map.Fields&FILTER=Bee_Map.Fields:name%20IN%20('other')&STYLES=&SRS=EPSG:3857&BBOX=1000000,5000000,2000000,6000000&WIDTH=256&HEIGHT=256&FORMAT=image/png"
```

**Verification**: 
- Both requests should succeed
- Two different cache files should exist (different FILTER = different cache key)

### 7. Test Sorted Query Parameters

```bash
# Request with parameters in one order
curl -o /tmp/test_order1.png "http://127.0.0.1:8011/mproxy/service?SERVICE=WMS&REQUEST=GetMap&LAYERS=Bee_Map.Fields&BBOX=1000000,5000000,2000000,6000000&WIDTH=256&HEIGHT=256"

# Request with same parameters in different order
curl -o /tmp/test_order2.png "http://127.0.0.1:8011/mproxy/service?BBOX=1000000,5000000,2000000,6000000&REQUEST=GetMap&SERVICE=WMS&HEIGHT=256&WIDTH=256&LAYERS=Bee_Map.Fields"
diff /tmp/test_order1.png /tmp/test_order2.png
```

**Expected Output**:
```
(no output - files are identical)
```

**Verification**: Same cache key should be used regardless of parameter order

### 8. Test Cache Purge (All)

```bash
# Get bearer token from systemd service
TOKEN=$(sudo systemctl show qcarta-tiles | grep QCARTA_CACHE_PURGE_TOKEN | cut -d= -f2)

# Purge all cache
curl -X POST http://127.0.0.1:8011/admin/cache/purge \
  -H "Authorization: Bearer $TOKEN" \
  -d "all"
```

**Expected Output**:
```json
{"success": true, "removed": 42, "scope": "all"}
```

**Verification**: 
- Should return success with number of removed entries
- Cache directory should be empty or have fewer files

### 9. Test Cache Purge (Invalid Token)

```bash
curl -X POST http://127.0.0.1:8011/admin/cache/purge \
  -H "Authorization: Bearer invalid-token" \
  -d "all"
```

**Expected Output**:
```
Unauthorized: Invalid token
```

**Status Code**: `401`

### 10. Test Cache Purge (Missing Token)

```bash
curl -X POST http://127.0.0.1:8011/admin/cache/purge \
  -d "all"
```

**Expected Output**:
```
Unauthorized: Missing Authorization header
```

**Status Code**: `401`

### 11. Test WMTS GetCapabilities

```bash
curl "http://127.0.0.1:8011/mproxy/service?SERVICE=WMTS&REQUEST=GetCapabilities" | head -20
```

**Expected Output**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<Capabilities xmlns="http://www.opengis.net/wmts/1.0">
  ...
```

**Verification**: Should return WMTS capabilities XML, NOT cached

### 12. Test WMTS GetTile (Should Cache)

```bash
curl -o /tmp/test_wmts.png "http://127.0.0.1:8011/mproxy/service?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=Bee_Map.Fields&STYLE=default&TILEMATRIXSET=EPSG:3857&TILEMATRIX=10&TILEROW=100&TILECOL=200&FORMAT=image/png"
file /tmp/test_wmts.png
```

**Expected Output**:
```
/tmp/test_wmts.png: PNG image data, ...
```

**Verification**: Should be valid PNG and cached

### 13. Test Through Apache Proxy

```bash
# Test through Apache (port 80/8000)
curl "http://localhost:8000/mproxy/service?SERVICE=WMS&REQUEST=GetCapabilities" | head -20
```

**Expected Output**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<WMS_Capabilities version="1.1.0">
  ...
```

**Verification**: Apache should proxy to Go service correctly

### 14. Verify WFS Redirect Still Works

```bash
curl -I "http://localhost:8000/mproxy/service?SERVICE=WFS&REQUEST=GetFeature&LAYERS=Bee_Map.Fields"
```

**Expected Output**:
```
HTTP/1.1 302 Found
Location: /proxy_wfs.php?SERVICE=WFS&REQUEST=GetFeature&LAYERS=Bee_Map.Fields
...
```

**Verification**: WFS requests should be redirected to proxy_wfs.php (Apache rewrite rule)

### 15. Check Service Logs

```bash
sudo journalctl -u qcarta-tiles -n 50 --no-pager
```

**Expected Output**: Should show service startup and any errors/warnings

### 16. Verify Cache Directory Structure

```bash
ls -la /var/www/data/mapproxy/cache_data/
```

**Expected Output**:
```
total 0
drwxr-xr-x ... ..  (subdirectories with 2-char names)
```

**Verification**: Cache should be organized in subdirectories (first 2 chars of hash)

### 17. Performance Test (Cache Hit vs Miss)

```bash
# First request (cache miss - slower)
time curl -o /dev/null "http://127.0.0.1:8011/mproxy/service?SERVICE=WMS&REQUEST=GetMap&VERSION=1.1.0&LAYERS=Bee_Map.Fields&STYLES=&SRS=EPSG:3857&BBOX=1000000,5000000,2000000,6000000&WIDTH=256&HEIGHT=256&FORMAT=image/png"

# Second request (cache hit - faster)
time curl -o /dev/null "http://127.0.0.1:8011/mproxy/service?SERVICE=WMS&REQUEST=GetMap&VERSION=1.1.0&LAYERS=Bee_Map.Fields&STYLES=&SRS=EPSG:3857&BBOX=1000000,5000000,2000000,6000000&WIDTH=256&HEIGHT=256&FORMAT=image/png"
```

**Verification**: Second request should be significantly faster (served from cache)

## Troubleshooting

### Service Won't Start

1. Check logs: `sudo journalctl -u qcarta-tiles -n 100`
2. Verify QGIS Server is accessible: `curl http://localhost/cgi-bin/qgis_mapserv.fcgi?SERVICE=WMS&REQUEST=GetCapabilities`
3. Check cache directory permissions: `ls -la /var/www/data/mapproxy/`
4. Verify port 8011 is not in use by another service: `sudo netstat -tlnp | grep :8011`
5. Verify MapProxy is stopped: `sudo systemctl status mapproxy`
6. Verify no MapProxy processes: `ps aux | grep mapproxy | grep -v grep`

### Cache Not Working

1. Check cache directory exists and is writable: `ls -ld /var/www/data/mapproxy/cache_data`
2. Check service logs for cache errors
3. Verify requests include valid GetMap/GetTile parameters
4. Check Content-Type is image/png in response headers

### Apache Proxy Not Working

1. Check Apache error logs: `sudo tail -f /var/log/apache2/error.log`
2. Verify ProxyPass directive in quail.conf
3. Test direct connection to Go service: `curl http://127.0.0.1:8011/health`
4. Restart Apache: `sudo systemctl restart apache2`

### Cache Purge Failing

1. Verify Bearer token is set: `sudo systemctl show qcarta-tiles | grep QCARTA_CACHE_PURGE_TOKEN`
2. Check token matches in request header
3. Verify service is running: `sudo systemctl status qcarta-tiles`
4. Check service logs for authentication errors
