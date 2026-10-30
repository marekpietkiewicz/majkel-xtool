(() => {
  const track = document.querySelector('[data-carousel]');
  const controls = document.querySelector('[data-carousel-controls]');
  if (!track || !controls) return;
  const slides = Array.from(track.querySelectorAll('.hero-slide'));
  const previous = controls.querySelector('[data-carousel-prev]');
  const next = controls.querySelector('[data-carousel-next]');
  const pause = controls.querySelector('[data-carousel-pause]');
  const count = controls.querySelector('[data-carousel-count]');
  if (slides.length < 2 || !previous || !next || !pause || !count) return;

  const container = track.closest('.hero-shell');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0;
  let userPaused = reduced.matches;
  let hovered = false;
  let focused = false;
  let dragging = false;
  let timer;
  let dragStart = null;
  let suppressClick = false;

  const move = (index) => {
    const target = (index + slides.length) % slides.length;
    track.scrollTo({ left: target * track.clientWidth, behavior: reduced.matches ? 'auto' : 'smooth' });
  };
  const update = () => {
    current = Math.max(0, Math.min(slides.length - 1, Math.round(track.scrollLeft / track.clientWidth)));
    count.textContent = `${current + 1} / ${slides.length}`;
  };
  const schedule = () => {
    window.clearTimeout(timer);
    const playing = !userPaused && !hovered && !focused && !dragging && !document.hidden;
    pause.textContent = userPaused ? pause.dataset.labelPlay : pause.dataset.labelPause;
    count.setAttribute('aria-live', playing ? 'off' : 'polite');
    if (playing) timer = window.setTimeout(() => { move(current + 1); schedule(); }, 7000);
  };

  previous.addEventListener('click', () => { move(current - 1); schedule(); });
  next.addEventListener('click', () => { move(current + 1); schedule(); });
  pause.addEventListener('click', () => { userPaused = !userPaused; schedule(); });
  track.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update, { passive: true });
  document.addEventListener('visibilitychange', schedule);
  reduced.addEventListener('change', (event) => { if (event.matches) userPaused = true; schedule(); });
  if (container) {
    container.addEventListener('mouseenter', () => { hovered = true; schedule(); });
    container.addEventListener('mouseleave', () => { hovered = false; schedule(); });
    container.addEventListener('focusin', () => { focused = true; schedule(); });
    container.addEventListener('focusout', (event) => { focused = container.contains(event.relatedTarget); schedule(); });
  }

  track.addEventListener('dragstart', (event) => event.preventDefault());
  track.addEventListener('pointerdown', (event) => {
    if (event.pointerType !== 'mouse' || event.button !== 0) return;
    dragStart = { x: event.clientX, left: track.scrollLeft, id: event.pointerId };
    suppressClick = false;
  });
  track.addEventListener('pointermove', (event) => {
    if (!dragStart || event.pointerId !== dragStart.id) return;
    const distance = event.clientX - dragStart.x;
    if (!dragging && Math.abs(distance) > 6) {
      dragging = true;
      track.classList.add('is-dragging');
      track.setPointerCapture(event.pointerId);
      schedule();
    }
    if (dragging) {
      event.preventDefault();
      track.scrollLeft = dragStart.left - distance;
    }
  });
  const endDrag = () => {
    if (!dragStart) return;
    if (dragging) {
      suppressClick = true;
      dragging = false;
      track.classList.remove('is-dragging');
      move(Math.round(track.scrollLeft / track.clientWidth));
      window.setTimeout(() => { suppressClick = false; }, 0);
      schedule();
    }
    dragStart = null;
  };
  track.addEventListener('pointerup', endDrag);
  track.addEventListener('pointercancel', endDrag);
  track.addEventListener('click', (event) => {
    if (suppressClick) { event.preventDefault(); event.stopPropagation(); }
  }, true);

  controls.hidden = false;
  update();
  schedule();
})();
