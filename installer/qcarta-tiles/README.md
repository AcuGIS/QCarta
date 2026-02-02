# QCarta Tile Service

Go service that replaces MapProxy for serving WMS/WMTS tiles with disk caching.

## Installation

### Build

```bash
cd qcarta-tiles
go build -o qcarta-tiles
```

### Install

```bash
sudo cp qcarta-tiles /opt/qcarta-tiles/
sudo cp qcarta-tiles.service /etc/systemd/system/
sudo systemctl daemon-reload
```

### Configure

Edit `/etc/systemd/system/qcarta-tiles.service` and set the Bearer token:

```ini
Environment="QCARTA_CACHE_PURGE_TOKEN=your-secure-token-here"
```

### Start Service

```bash
sudo systemctl enable qcarta-tiles
sudo systemctl start qcarta-tiles
```

## Configuration

- **QGIS Server URL**: `http://localhost/cgi-bin/qgis_mapserv.fcgi` (hardcoded)
- **Cache Directory**: `/var/www/data/mapproxy/cache_data`
- **Server Address**: `127.0.0.1:8011` (listens on localhost only)
- **Bearer Token**: Set via `QCARTA_CACHE_PURGE_TOKEN` environment variable

## API Endpoints

### GET /mproxy/service
Proxies WMS/WMTS requests to QGIS Server with disk caching for GetMap/GetTile requests.

**Query Parameters**: All WMS/WMTS standard parameters (SERVICE, REQUEST, LAYERS, BBOX, etc.)

**Caching**:
- Caches PNG responses for GetMap and GetTile requests
- Cache key includes all sorted query parameters (especially FILTER)
- Does NOT cache GetCapabilities requests

### POST /admin/cache/purge
Purges cache entries. Requires Bearer token authentication.

**Headers**:
```
Authorization: Bearer <token>
```

**Body**: Optional scope (layer name or "all" for all cache)

**Response**:
```json
{"success": true, "removed": 42, "scope": "all"}
```

### GET /health
Health check endpoint.

**Response**: `OK`

## Cache Management

Cache keys are SHA256 hashes of sorted query parameters. This ensures:
- Same requests (with same parameters) hit the same cache
- FILTER parameter variations create different cache entries
- Parameter order doesn't matter

Cache structure:
```
/var/www/data/mapproxy/cache_data/
  <first-2-chars-of-hash>/
    <full-hash>
```

## Verification

See `VERIFICATION.md` for verification commands and expected outputs.

See `PORT_CONFLICT_VERIFICATION.md` for exact commands to verify no port conflicts and MapProxy removal.
