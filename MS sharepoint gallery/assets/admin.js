document.addEventListener('click', function (event) {
  var button = event.target.closest('.msog-copy-btn');
  if (!button) return;
  var code = button.parentElement.querySelector('code');
  if (!code) return;
  navigator.clipboard.writeText(code.textContent).then(function () {
    var old = button.textContent; button.textContent = 'Copied!';
    window.setTimeout(function () { button.textContent = old; }, 1400);
  });
});
