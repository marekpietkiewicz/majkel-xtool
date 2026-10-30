(() => {
  const track = document.querySelector('[data-carousel]');
  const controls = document.querySelector('[data-carousel-controls]');
  if (!track || !controls) return;
  const slides = Array.from(track.querySelectorAll('.hero-slide'));
  const previous = controls.querySelector('[data-carousel-prev]');
  const next = controls.querySelector('[data-carousel-next]');
  const count = controls.querySelector('[data-carousel-count]');
  if (slides.length < 2 || !previous || !next || !count) return;
  let current = 0;
  const update = () => {
    current = Math.max(0, Math.min(slides.length - 1, Math.round(track.scrollLeft / track.clientWidth)));
    count.textContent = `${current + 1} / ${slides.length}`;
    previous.disabled = current === 0;
    next.disabled = current === slides.length - 1;
  };
  const move = (index) => {
    const target = Math.max(0, Math.min(slides.length - 1, index));
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    track.scrollTo({ left: target * track.clientWidth, behavior: reduced ? 'auto' : 'smooth' });
  };
  previous.addEventListener('click', () => move(current - 1));
  next.addEventListener('click', () => move(current + 1));
  track.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update, { passive: true });
  controls.hidden = false;
  update();
})();
