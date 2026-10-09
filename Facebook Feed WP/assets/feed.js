(function () {
  'use strict';
  var modal;

  function close() {
    if (modal) { modal.remove(); modal = null; }
    document.removeEventListener('keydown', onKey);
  }
  function onKey(e) { if (e.key === 'Escape') close(); }

  function open(src, fullText, link) {
    close();
    modal = document.createElement('div');
    modal.className = 'dfbf-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    var box = document.createElement('div');
    box.className = 'dfbf-modal-box';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'dfbf-close';
    btn.setAttribute('aria-label', DFBF.close);
    btn.innerHTML = '&times;';
    var img = document.createElement('img');
    img.src = src;
    img.alt = '';
    box.appendChild(btn);
    box.appendChild(img);
    if (fullText) {
      var text = document.createElement('div');
      text.className = 'dfbf-modal-text';
      var copy = fullText.cloneNode(true);
      copy.removeAttribute('hidden');
      text.appendChild(copy);
      box.appendChild(text);
    }
    if (link) box.appendChild(link);
    modal.appendChild(box);
    modal.addEventListener('click', function (e) { if (e.target === modal || e.target === btn) close(); });
    document.body.appendChild(modal);
    document.addEventListener('keydown', onKey);
    btn.focus();
  }

  document.addEventListener('click', function (e) {
    var thumb = e.target.closest('.dfbf-thumb[data-full]');
    if (thumb) {
      var feed = thumb.closest('.dfbf-feed');
      if (feed && feed.dataset.open === 'lightbox' && !(e.ctrlKey || e.metaKey || e.shiftKey)) {
        e.preventDefault();
        var item = thumb.closest('.dfbf-item');
        var full = item.querySelector('.dfbf-full');
        var link = item.querySelector('.dfbf-link');
        open(thumb.dataset.full, full, link ? link.cloneNode(true) : null);
      }
      return;
    }
    var more = e.target.closest('.dfbf-more');
    if (!more) return;
    var box = more.closest('.dfbf-feed');
    var d = box.dataset;
    var body = new URLSearchParams({
      action: 'dfbf_more', nonce: DFBF.nonce, page: d.page, token: more.dataset.next,
      limit: d.limit, open: d.open, excerpt: d.excerpt, show_date: d.date
    });
    more.disabled = true;
    fetch(DFBF.ajax, { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.success) { more.disabled = false; return; }
        box.querySelector('.dfbf-items').insertAdjacentHTML('beforeend', r.data.html);
        if (r.data.next) { more.dataset.next = r.data.next; more.disabled = false; }
        else more.remove();
      })
      .catch(function () { more.disabled = false; });
  });
})();
