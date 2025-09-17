function doGet() {
  return HtmlService.createHtmlOutputFromFile('Index').setTitle('BMAD Assistant');
}

function getSessionUserEmail() {
  try {
    return Session.getActiveUser().getEmail();
  } catch (e) {
    return '';
  }
}

function ping() {
  return { ok: true, ts: new Date().toISOString() };
}
