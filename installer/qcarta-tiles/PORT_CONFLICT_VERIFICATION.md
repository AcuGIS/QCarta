# Port Conflict Verification Commands

## Exact Commands to Verify No Port Conflicts on 8011

Run these commands on the remote server to ensure port 8011 is only used by qcarta-tiles and MapProxy is completely removed.

### 1. Check What's Listening on Port 8011

```bash
sudo netstat -tlnp | grep :8011
```

**Alternative (if netstat not available)**:
```bash
sudo ss -tlnp | grep :8011
```

**Expected Output (qcarta-tiles running)**:
```
tcp        0      0 127.0.0.1:8011          0.0.0.0:*               LISTEN      12345/qcarta-tiles
```

**Expected Output (nothing running)**:
```
(no output)
```

**Verification**: 
- Should show ONLY qcarta-tiles process
- Should listen on 127.0.0.1:8011 (not 0.0.0.0:8011)
- PID should match qcarta-tiles process

### 2. Check Process by PID

```bash
# Get PID from step 1, then:
ps -p 12345 -o pid,comm,args
```

**Expected Output**:
```
  PID COMMAND         COMMAND
12345 qcarta-tiles    /opt/qcarta-tiles/qcarta-tiles
```

**Verification**: Process name should be `qcarta-tiles`

### 3. Verify MapProxy is NOT Listening on 8011

```bash
sudo lsof -i :8011
```

**Expected Output (qcarta-tiles only)**:
```
COMMAND      PID USER   FD   TYPE DEVICE SIZE/OFF NODE NAME
qcarta-tiles 12345 www-data   3u  IPv4  123456      0t0  TCP 127.0.0.1:8011 (LISTEN)
```

**Expected Output (if MapProxy still running - BAD)**:
```
COMMAND    PID USER   FD   TYPE DEVICE SIZE/OFF NODE NAME
mapproxy   999 www-data   3u  IPv4  123456      0t0  TCP *:8011 (LISTEN)
```

**Verification**: 
- Should show ONLY qcarta-tiles
- Should NOT show mapproxy
- Should listen on 127.0.0.1 (not *)

### 4. Check All Processes Using Port 8011

```bash
sudo fuser 8011/tcp
```

**Expected Output (qcarta-tiles only)**:
```
8011/tcp:             12345
```

**Verification**: Should show only one PID (qcarta-tiles)

### 5. Verify MapProxy Service Status

```bash
sudo systemctl status mapproxy
```

**Expected Output (service doesn't exist)**:
```
Unit mapproxy.service could not be found.
```

**Expected Output (service exists but stopped)**:
```
● mapproxy.service
     Loaded: loaded (...)
     Active: inactive (dead) since ...
```

**Verification**: MapProxy service should NOT be active

### 6. Check for MapProxy Processes

```bash
ps aux | grep -i mapproxy | grep -v grep
```

**Expected Output**:
```
(no output)
```

**Verification**: No MapProxy processes should be running

### 7. Check for MapProxy Python Processes

```bash
ps aux | grep -i "python.*mapproxy" | grep -v grep
```

**Expected Output**:
```
(no output)
```

**Verification**: No MapProxy Python processes should be running

### 8. Verify Systemd Unit Conflicts

```bash
systemctl list-unit-files | grep mapproxy
```

**Expected Output**:
```
(no output)
```

**Or if unit exists but disabled**:
```
mapproxy.service                          disabled
```

**Verification**: MapProxy unit should not exist or be disabled

### 9. Check qcarta-tiles Service Conflicts

```bash
systemctl show qcarta-tiles | grep Conflicts
```

**Expected Output**:
```
Conflicts=mapproxy.service
```

**Verification**: qcarta-tiles should conflict with mapproxy.service

### 10. Test Port Binding (Should Fail if Already in Use)

```bash
# Try to bind to port 8011 (should fail if qcarta-tiles is running)
timeout 1 nc -l 127.0.0.1 8011 2>&1
```

**Expected Output (port in use by qcarta-tiles)**:
```
nc: Address already in use
```

**Verification**: Port should be in use by qcarta-tiles (this is expected)

### 11. Verify Apache Proxy Configuration

```bash
grep -A 2 "ProxyPass.*mproxy" /etc/apache2/sites-enabled/*.conf
# OR if using docker/quail.conf:
grep -A 2 "ProxyPass.*mproxy" /path/to/quail.conf
```

**Expected Output**:
```
ProxyPass				/mproxy http://127.0.0.1:8011/mproxy
ProxyPassReverse	/mproxy http://127.0.0.1:8011/mproxy
```

**Verification**: Apache should proxy to 127.0.0.1:8011/mproxy (not mapproxy:8011)

### 12. Test Connection to Port 8011

```bash
# From localhost (should work)
curl -v http://127.0.0.1:8011/health 2>&1 | grep -E "(Connected|HTTP)"
```

**Expected Output**:
```
* Connected to 127.0.0.1 (127.0.0.1) port 8011
HTTP/1.1 200 OK
```

**Verification**: Should connect successfully to 127.0.0.1:8011

### 13. Test Connection from External IP (Should Fail)

```bash
# Replace SERVER_IP with actual server IP
curl -v --connect-timeout 2 http://SERVER_IP:8011/health 2>&1 | grep -E "(Connected|refused|timeout)"
```

**Expected Output**:
```
* Connection refused
# OR
* Connection timeout
```

**Verification**: Port should NOT be accessible from external IP (listening on 127.0.0.1 only)

## Summary Checklist

- [ ] Port 8011 is bound to 127.0.0.1:8011 (not 0.0.0.0:8011)
- [ ] Only qcarta-tiles process is listening on port 8011
- [ ] MapProxy service is stopped/disabled/doesn't exist
- [ ] No MapProxy processes are running
- [ ] Apache proxies to http://127.0.0.1:8011/mproxy
- [ ] Port 8011 is NOT accessible from external IP
- [ ] qcarta-tiles service is running and healthy

## If Port Conflict Detected

If you see MapProxy or another process using port 8011:

1. **Stop MapProxy service**:
   ```bash
   sudo systemctl stop mapproxy
   sudo systemctl disable mapproxy
   ```

2. **Kill any remaining MapProxy processes**:
   ```bash
   sudo pkill -f mapproxy
   ```

3. **Stop any Docker containers**:
   ```bash
   docker stop $(docker ps -q --filter "name=mapproxy")
   docker rm $(docker ps -aq --filter "name=mapproxy")
   ```

4. **Verify port is free**:
   ```bash
   sudo netstat -tlnp | grep :8011
   # Should show no output
   ```

5. **Start qcarta-tiles**:
   ```bash
   sudo systemctl start qcarta-tiles
   ```

6. **Re-run verification commands**
