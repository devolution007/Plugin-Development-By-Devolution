(function () {
  var modal, image, caption, closeButton, previousFocus;
  function ensureModal() {
    if (modal) return;
    modal = document.createElement('div'); modal.className = 'msog-lightbox'; modal.hidden = true;
    modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.setAttribute('aria-label', 'Image preview');
    modal.innerHTML = '<button type="button" class="msog-lightbox-close" aria-label="Close image preview">&times;</button><figure class="msog-lightbox-figure"><img class="msog-lightbox-image" alt=""><figcaption class="msog-lightbox-caption"></figcaption></figure>';
    document.body.appendChild(modal); image = modal.querySelector('.msog-lightbox-image'); caption = modal.querySelector('.msog-lightbox-caption'); closeButton = modal.querySelector('.msog-lightbox-close');
    closeButton.addEventListener('click', close);
    modal.addEventListener('click', function (event) { if (event.target === modal) close(); });
  }
  function open(link) {
    ensureModal(); previousFocus = link; image.src = link.dataset.full || link.href; image.alt = link.dataset.name || '';
    caption.textContent = link.dataset.name || ''; modal.hidden = false; document.body.classList.add('msog-lightbox-open'); closeButton.focus();
  }
  function close() {
    if (!modal || modal.hidden) return; modal.hidden = true; image.src = ''; document.body.classList.remove('msog-lightbox-open');
    if (previousFocus) previousFocus.focus();
  }
  document.addEventListener('click', function (event) { var link = event.target.closest('.msog-lightbox-link'); if (link) { event.preventDefault(); open(link); } });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
}());
