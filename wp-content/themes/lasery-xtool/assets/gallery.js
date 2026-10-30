(() => {
  document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
    const main = gallery.querySelector('[data-gallery-main]');
    const mainImage = main && main.querySelector('img');
    const links = Array.from(gallery.querySelectorAll('[data-gallery-item]'));
    if (!main || !mainImage || links.length < 2) return;

    const buttons = links.map((link, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'product-gallery-choice';
      button.setAttribute('aria-label', link.getAttribute('aria-label') || '');
      button.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');
      while (link.firstChild) button.appendChild(link.firstChild);
      button.addEventListener('click', () => {
        main.href = link.href;
        mainImage.src = link.dataset.large || link.href;
        main.setAttribute('aria-label', link.getAttribute('aria-label') || '');
        buttons.forEach((choice) => choice.setAttribute('aria-pressed', choice === button ? 'true' : 'false'));
      });
      link.replaceWith(button);
      return button;
    });
  });
})();
