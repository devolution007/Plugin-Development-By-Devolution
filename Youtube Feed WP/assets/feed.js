(function () {
  'use strict';
  var modal;

  function close() {
    if (modal) { modal.remove(); modal = null; }
    document.removeEventListener('keydown', onKey);
  }
  function onKey(e) { if (e.key === 'Escape') close(); }

  function open(id) {
    close();
    modal = document.createElement('div');
    modal.className = 'dytf-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    var box = document.createElement('div');
    box.className = 'dytf-modal-box';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'dytf-close';
    btn.setAttribute('aria-label', DYTF.close);
    btn.innerHTML = '&times;';
    var f = document.createElement('iframe');
    f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
    f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    f.allowFullscreen = true;
    box.appendChild(btn);
    box.appendChild(f);
    modal.appendChild(box);
    modal.addEventListener('click', function (e) { if (e.target === modal || e.target === btn) close(); });
    document.body.appendChild(modal);
    document.addEventListener('keydown', onKey);
    btn.focus();
  }

  document.addEventListener('click', function (e) {
    var item = e.target.closest('.dytf-item');
    if (item) {
      var feed = item.closest('.dytf-feed');
      if (feed && feed.dataset.play === 'lightbox' && !(e.ctrlKey || e.metaKey || e.shiftKey)) {
        e.preventDefault();
        open(item.dataset.video);
      }
      return;
    }
    var more = e.target.closest('.dytf-more');
    if (!more) return;
    var box = more.closest('.dytf-feed');
    var d = box.dataset;
    var body = new URLSearchParams({
      action: 'dytf_more', nonce: DYTF.nonce, playlist: d.playlist, token: more.dataset.next,
      limit: d.limit, play: d.play, show_title: d.title, show_date: d.date
    });
    more.disabled = true;
    fetch(DYTF.ajax, { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.success) { more.disabled = false; return; }
        box.querySelector('.dytf-items').insertAdjacentHTML('beforeend', r.data.html);
        if (r.data.next) { more.dataset.next = r.data.next; more.disabled = false; }
        else more.remove();
      })
      .catch(function () { more.disabled = false; });
  });
})();
