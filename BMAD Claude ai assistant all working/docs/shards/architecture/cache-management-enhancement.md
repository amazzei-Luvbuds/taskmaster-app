# Cache Management Enhancement

```javascript
// CacheManager.gs - Enhanced caching with sharding
class CacheManager {
  constructor(namespace) {
    this.namespace = namespace;
    this.cache = CacheService.getUserCache();
    this.MAX_KEY_SIZE = 100 * 1024; // 100KB limit
  }
  
  set(key, value, ttl = 3600) {
    const cacheKey = this.getCacheKey_(key);
    const serialized = JSON.stringify(value);
    
    if (serialized.length > this.MAX_KEY_SIZE) {
      // Shard large data
      return this.setSharded_(cacheKey, serialized, ttl);
    }
    
    try {
      this.cache.put(cacheKey, serialized, ttl);
      return true;
    } catch (e) {
      Logger.log(`Cache set failed: ${e.toString()}`);
      return false;
    }
  }
  
  get(key) {
    const cacheKey = this.getCacheKey_(key);
    
    // Check for sharded data
    const shardIndex = this.cache.get(`${cacheKey}_shards`);
    if (shardIndex) {
      return this.getSharded_(cacheKey, parseInt(shardIndex));
    }
    
    const value = this.cache.get(cacheKey);
    return value ? JSON.parse(value) : null;
  }
  
  setSharded_(key, data, ttl) {
    const chunkSize = 90 * 1024; // 90KB chunks to be safe
    const chunks = [];
    
    for (let i = 0; i < data.length; i += chunkSize) {
      chunks.push(data.slice(i, i + chunkSize));
    }
    
    // Store chunks
    chunks.forEach((chunk, index) => {
      this.cache.put(`${key}_${index}`, chunk, ttl);
    });
    
    // Store shard index
    this.cache.put(`${key}_shards`, String(chunks.length), ttl);
    
    return true;
  }
  
  getSharded_(key, shardCount) {
    const chunks = [];
    
    for (let i = 0; i < shardCount; i++) {
      const chunk = this.cache.get(`${key}_${i}`);
      if (!chunk) {
        return null; // Missing shard, data incomplete
      }
      chunks.push(chunk);
    }
    
    return JSON.parse(chunks.join(''));
  }
  
  getCacheKey_(key) {
    // Create consistent cache key
    if (typeof key === 'object') {
      // Hash object keys for consistent caching
      const sorted = Object.keys(key).sort().reduce((obj, k) => {
        obj[k] = key[k];
        return obj;
      }, {});
      return `${this.namespace}_${Utilities.base64Encode(
        Utilities.computeDigest(
          Utilities.DigestAlgorithm.MD5,
          JSON.stringify(sorted)
        )
      )}`;
    }
    
    return `${this.namespace}_${key}`;
  }
  
  clear(pattern) {
    // Clear cache entries matching pattern
    // Note: Apps Script doesn't provide cache enumeration
    // This is a best-effort clear based on known patterns
    if (pattern) {
      this.cache.remove(pattern);
    } else {
      // Clear all namespace entries (requires tracking keys)
      const keys = this.getTrackedKeys_();
      this.cache.removeAll(keys);
    }
  }
  
  getTrackedKeys_() {
    // Implement key tracking if needed
    const props = PropertiesService.getUserProperties();
    const tracked = props.getProperty(`${this.namespace}_keys`);
    return tracked ? JSON.parse(tracked) : [];
  }
}
```
