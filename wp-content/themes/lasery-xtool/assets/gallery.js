(() => {
  document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
    const main = gallery.querySelector('[data-gallery-main]');
    const mainImage = main && main.querySelector('img');
    const links = Array.from(gallery.querySelectorAll('[data-gallery-item]'));
    const controls = gallery.querySelector('[data-gallery-controls]');
    if (!main || !mainImage || links.length < 2 || !controls) return;
    const previous = controls.querySelector('[data-gallery-prev]');
    const next = controls.querySelector('[data-gallery-next]');
    const pause = controls.querySelector('[data-gallery-pause]');
    const count = controls.querySelector('[data-gallery-count]');
    if (!previous || !next || !pause || !count) return;

    let current = 0;
    let userPaused = true;
    let hovered = false;
    let focused = false;
    let dragging = false;
    let dragStart = null;
    let suppressClick = false;
    let timer;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const buttons = links.map((link, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'product-gallery-choice';
      button.setAttribute('aria-label', link.getAttribute('aria-label') || '');
      button.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');
      while (link.firstChild) button.appendChild(link.firstChild);
      link.replaceWith(button);
      return button;
    });
    const select = (index) => {
      current = (index + links.length) % links.length;
      const link = links[current];
      main.href = link.href;
      mainImage.src = link.dataset.large || link.href;
      main.setAttribute('aria-label', link.getAttribute('aria-label') || '');
      buttons.forEach((button, buttonIndex) => button.setAttribute('aria-pressed', buttonIndex === current ? 'true' : 'false'));
      count.textContent = `${current + 1} / ${links.length}`;
    };
    const schedule = () => {
      window.clearTimeout(timer);
      const playing = !userPaused && !hovered && !focused && !dragging && !document.hidden;
      pause.textContent = userPaused ? pause.dataset.labelPlay : pause.dataset.labelPause;
      count.setAttribute('aria-live', playing ? 'off' : 'polite');
      if (playing) timer = window.setTimeout(() => { select(current + 1); schedule(); }, 7000);
    };
    buttons.forEach((button, index) => button.addEventListener('click', () => { select(index); schedule(); }));
    previous.addEventListener('click', () => { select(current - 1); schedule(); });
    next.addEventListener('click', () => { select(current + 1); schedule(); });
    pause.addEventListener('click', () => { userPaused = !userPaused; schedule(); });
    gallery.addEventListener('mouseenter', () => { hovered = true; schedule(); });
    gallery.addEventListener('mouseleave', () => { hovered = false; schedule(); });
    gallery.addEventListener('focusin', () => { focused = true; schedule(); });
    gallery.addEventListener('focusout', (event) => { focused = gallery.contains(event.relatedTarget); schedule(); });
    document.addEventListener('visibilitychange', schedule);
    reduced.addEventListener('change', (event) => { if (event.matches) userPaused = true; schedule(); });

    main.addEventListener('dragstart', (event) => event.preventDefault());
    main.addEventListener('pointerdown', (event) => {
      if (event.pointerType === 'mouse' && event.button !== 0) return;
      dragStart = { x: event.clientX, y: event.clientY, id: event.pointerId };
      suppressClick = false;
    });
    main.addEventListener('pointermove', (event) => {
      if (!dragStart || event.pointerId !== dragStart.id) return;
      const dx = event.clientX - dragStart.x;
      const dy = event.clientY - dragStart.y;
      if (!dragging && Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(dy)) {
        dragging = true;
        main.setPointerCapture(event.pointerId);
        schedule();
      }
      if (dragging) event.preventDefault();
    });
    const endDrag = (event, cancelled) => {
      if (!dragStart || event.pointerId !== dragStart.id) return;
      if (dragging) {
        const dx = event.clientX - dragStart.x;
        suppressClick = true;
        dragging = false;
        if (!cancelled && Math.abs(dx) > 35) select(current + (dx < 0 ? 1 : -1));
        window.setTimeout(() => { suppressClick = false; }, 0);
        schedule();
      }
      dragStart = null;
    };
    main.addEventListener('pointerup', (event) => endDrag(event, false));
    main.addEventListener('pointercancel', (event) => endDrag(event, true));
    main.addEventListener('click', (event) => {
      if (suppressClick) { event.preventDefault(); event.stopPropagation(); }
    }, true);

    controls.hidden = false;
    schedule();
  });
})();
