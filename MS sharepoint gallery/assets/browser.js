(function () {
  function init(browser) {
    var grid = browser.querySelector('.msog-view-folders');
    var status = browser.querySelector('.msog-browser-status');
    var nav = browser.querySelector('.msog-browser-nav');
    var path = browser.querySelector('.msog-browser-path');
    var history = [];
    function load(folder, token, name, push) {
      if (push) history.push({ folder: browser.dataset.folder, token: browser.dataset.token, name: path.textContent || '' });
      browser.classList.add('is-loading'); status.textContent = MSOG_BROWSER.loading;
      var body = new URLSearchParams({ action: 'msog_browse_folder', _ajax_nonce: MSOG_BROWSER.nonce, drive: browser.dataset.drive, folder: folder, token: token });
      fetch(MSOG_BROWSER.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          if (!result.success) throw new Error(result.data && result.data.message || MSOG_BROWSER.error);
          grid.innerHTML = result.data.html; browser.dataset.folder = folder; browser.dataset.token = token;
          path.textContent = name || ''; nav.hidden = history.length === 0; status.textContent = '';
        })
        .catch(function (error) { status.textContent = error.message || MSOG_BROWSER.error; })
        .finally(function () { browser.classList.remove('is-loading'); });
    }
    browser.addEventListener('click', function (event) {
      var folder = event.target.closest('.msog-folder-card');
      if (folder) load(folder.dataset.folder, folder.dataset.token, folder.dataset.name, true);
      if (event.target.closest('.msog-back') && history.length) {
        var previous = history.pop(); load(previous.folder, previous.token, previous.name, false);
      }
    });
  }
  document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.msog-browser').forEach(init); });
}());
