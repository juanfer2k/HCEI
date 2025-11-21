/**
 * form-api.js
 * API calls for form components
 */

window.AppAPI = window.AppAPI || {};

// Define apiUrl function immediately
window.AppAPI.apiUrl = function(path) {
  return window.AppConfig.baseUrl + path;
};

// Normalize error response
window.AppAPI.handleError = function(error) {
  console.error('API Error:', error);
  return { results: [] };
};

// CIE10 search
window.AppAPI.searchCie10 = async function(term) {
  try {
    const response = await $.ajax({
      url: window.AppAPI.apiUrl('buscar_cie10.php'),
      data: { q: term },
      dataType: 'json'
    });
    if (!response || !Array.isArray(response.results)) {
      console.error('Invalid CIE10 response:', response);
      return { results: [] };
    }
    return response;
  } catch (error) {
    return window.AppAPI.handleError(error);
  }
};

// Municipio search
window.AppAPI.searchMunicipio = async function(term) {
  try {
    const response = await $.ajax({
      url: window.AppAPI.apiUrl('buscar_municipio.php'),
      data: { q: term },
      dataType: 'json'
    });
    if (!response || !Array.isArray(response.results)) {
      console.error('Invalid municipio response:', response);
      return { results: [] };
    }
    return response;
  } catch (error) {
    return window.AppAPI.handleError(error);
  }
};

// IPS search
window.AppAPI.searchIps = async function(term) {
  try {
    const response = await $.ajax({
      url: window.AppAPI.apiUrl('buscar_ips.php'),
      data: { q: term },
      dataType: 'json'
    });
    if (!response || !Array.isArray(response.results)) {
      console.error('Invalid IPS response:', response);
      return { results: [] };
    }
    return response;
  } catch (error) {
    return window.AppAPI.handleError(error);
  }
};