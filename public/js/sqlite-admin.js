document.addEventListener('click', function (event) {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;
  const confirmMessage = target.getAttribute('data-confirm');
  if (confirmMessage && !window.confirm(confirmMessage)) {
    event.preventDefault();
  }
});
