var GEMINI_API_KEY_PROP = 'GEMINI_API_KEY';

function getGeminiApiKey_() {
  var key = PropertiesService.getScriptProperties().getProperty(GEMINI_API_KEY_PROP);
  if (!key) throw new Error('Missing GEMINI_API_KEY in Script Properties');
  return key;
}

function geminiComplete_(prompt) {
  var apiKey = getGeminiApiKey_();
  var url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' + encodeURIComponent(apiKey);
  var payload = {
    contents: [{ parts: [{ text: prompt }]}]
  };
  var res = UrlFetchApp.fetch(url, {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });
  var code = res.getResponseCode();
  if (code >= 400) {
    throw new Error('Gemini API error ' + code + ': ' + res.getContentText());
  }
  var body = JSON.parse(res.getContentText());
  var text = (((body || {}).candidates || [])[0] || {}).content || {};
  var part = (text.parts || [])[0] || {};
  return part.text || '';
}
