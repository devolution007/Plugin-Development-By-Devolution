(function () {
  var modal, image, closeButton, previousButton, nextButton, previousFocus, links = [], activeIndex = 0;
  function ensureModal() {
    if (modal) return;
    modal = document.createElement('div'); modal.className = 'msog-lightbox'; modal.hidden = true;
    modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.setAttribute('aria-label', 'Image preview');
    modal.innerHTML = '<button type="button" class="msog-lightbox-close" aria-label="Close image preview">&times;</button><button type="button" class="msog-lightbox-control msog-lightbox-previous" aria-label="Previous image">&#10094;</button><figure class="msog-lightbox-figure"><img class="msog-lightbox-image" alt=""></figure><button type="button" class="msog-lightbox-control msog-lightbox-next" aria-label="Next image">&#10095;</button>';
    document.body.appendChild(modal); image = modal.querySelector('.msog-lightbox-image'); closeButton = modal.querySelector('.msog-lightbox-close'); previousButton = modal.querySelector('.msog-lightbox-previous'); nextButton = modal.querySelector('.msog-lightbox-next');
    closeButton.addEventListener('click', close);
    previousButton.addEventListener('click', function () { show(activeIndex - 1); });
    nextButton.addEventListener('click', function () { show(activeIndex + 1); });
    modal.addEventListener('click', function (event) { if (event.target === modal) close(); });
  }
  function show(index) {
    if (!links.length) return;
    activeIndex = (index + links.length) % links.length;
    var link = links[activeIndex]; image.src = link.dataset.full || link.href; image.alt = '';
  }
  function open(link) {
    ensureModal(); previousFocus = link;
    var scope = link.closest('.msog-browser') || link.closest('.msog-gallery') || document;
    links = Array.prototype.slice.call(scope.querySelectorAll('.msog-lightbox-link'));
    activeIndex = Math.max(0, links.indexOf(link)); show(activeIndex);
    previousButton.hidden = links.length < 2; nextButton.hidden = links.length < 2;
    modal.hidden = false; document.body.classList.add('msog-lightbox-open'); closeButton.focus();
  }
  function close() {
    if (!modal || modal.hidden) return; modal.hidden = true; image.src = ''; document.body.classList.remove('msog-lightbox-open');
    if (previousFocus) previousFocus.focus();
  }
  document.addEventListener('click', function (event) { var link = event.target.closest('.msog-lightbox-link'); if (link) { event.preventDefault(); open(link); } });
  document.addEventListener('keydown', function (event) {
    if (!modal || modal.hidden) return;
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowLeft') show(activeIndex - 1);
    if (event.key === 'ArrowRight') show(activeIndex + 1);
  });
}());
