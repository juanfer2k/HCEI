/**
 * API configuration and utilities
 */

// Ensure namespace exists
window.AppAPI = window.AppAPI || {};

// Get base URL from config or fall back to /
window.AppAPI.getBaseUrl = function() {
  return window.AppConfig.baseUrl || '/';
};

// Ensure base URL has trailing slash
window.AppAPI.normalizeUrl = function(url) {
  const base = window.AppAPI.getBaseUrl();
  return base.endsWith('/') ? base + url : base + '/' + url;
};

// Helper to build complete API URLs
window.AppAPI.apiUrl = function(endpoint) {
  return window.AppAPI.normalizeUrl(endpoint);
};