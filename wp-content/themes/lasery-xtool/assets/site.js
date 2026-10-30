(() => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* Header: border after scrolling, mobile menu and language menu. */
  const header = document.querySelector('[data-site-header]');
  if (header) {
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 4);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const toggle = header.querySelector('[data-menu-toggle]');
    const menu = header.querySelector('[data-menu]');
    if (toggle && menu) {
      const setOpen = (open) => {
        toggle.setAttribute('aria-expanded', String(open));
        header.classList.toggle('is-menu-open', open);
      };
      toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
      menu.addEventListener('click', (event) => { if (event.target.closest('a')) setOpen(false); });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
          setOpen(false);
          toggle.focus();
        }
      });
    }
  }

  document.querySelectorAll('[data-lang-menu]').forEach((details) => {
    document.addEventListener('click', (event) => {
      if (details.open && !details.contains(event.target)) details.open = false;
    });
    details.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && details.open) {
        details.open = false;
        details.querySelector('summary').focus();
      }
    });
  });

  /* Order form: show the chosen variant and its price in the summary. */
  document.querySelectorAll('[data-order-form]').forEach((form) => {
    const variant = form.querySelector('[data-order-summary-variant]');
    const price = form.querySelector('[data-order-summary-price]');
    form.addEventListener('change', (event) => {
      const input = event.target;
      if (input.name !== 'variant' || input.type !== 'radio') return;
      const option = input.closest('.variant-option');
      if (variant && option) variant.textContent = option.querySelector('.variant-option-title').textContent;
      if (price) price.textContent = input.dataset.price || '';
    });
  });

  /* Articles: reading progress and the current section in the table of contents. */
  const progress = document.querySelector('[data-reading-progress]');
  const articleContent = document.querySelector('.article-content');
  if (progress && articleContent) {
    // The raw href matches the heading id, also for Cyrillic ids.
    const tocLinks = Array.from(document.querySelectorAll('.article-toc a'));
    const headings = tocLinks.map((link) => document.getElementById(link.getAttribute('href').slice(1)));
    let frame = 0;
    const update = () => {
      frame = 0;
      const rect = articleContent.getBoundingClientRect();
      const value = Math.min(1, Math.max(0, -rect.top / Math.max(rect.height - window.innerHeight * 0.6, 1)));
      progress.style.transform = `scaleX(${value})`;
      // The current section is the last one whose heading reached the sticky header
      // (scroll-padding-top is where links from the table of contents stop).
      const line = (parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0) + 8;
      let current = -1;
      headings.forEach((heading, index) => {
        if (heading && heading.getBoundingClientRect().top <= line) current = index;
      });
      tocLinks.forEach((link, index) => {
        if (index === current) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
      });
    };
    const schedule = () => { frame = frame || requestAnimationFrame(update); };
    update();
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
  }

  /* Sliders (Swiper is loaded only on pages that use it). */
  const Swiper = window.Swiper;
  if (typeof Swiper !== 'function') return;

  const a11y = (element) => ({
    prevSlideMessage: element.dataset.labelPrev,
    nextSlideMessage: element.dataset.labelNext,
  });

  const controlsOf = (element) => {
    const scope = element.closest('[data-rail-section]') || element;
    const controls = scope.querySelector('[data-slider-controls]');
    if (controls) controls.hidden = false;
    return {
      prevEl: scope.querySelector('[data-slider-prev]'),
      nextEl: scope.querySelector('[data-slider-next]'),
      pagination: scope.querySelector('[data-slider-pagination]'),
      autoplayToggle: scope.querySelector('[data-autoplay-toggle]'),
    };
  };

  // A visible pause button for auto-advancing slides (WCAG 2.2.2).
  const bindAutoplayToggle = (swiper, button) => {
    if (!button) return;
    const sync = () => {
      const running = swiper.autoplay.running;
      button.classList.toggle('is-paused', !running);
      button.setAttribute('aria-label', running ? button.dataset.labelPause : button.dataset.labelPlay);
    };
    button.addEventListener('click', () => {
      if (swiper.autoplay.running) swiper.autoplay.stop();
      else swiper.autoplay.start();
    });
    swiper.on('autoplayStart autoplayStop', sync);
    if (reducedMotion.matches) swiper.autoplay.stop();
    sync();
  };

  const hero = document.querySelector('[data-hero-swiper]');
  if (hero && hero.querySelectorAll('.swiper-slide').length > 1) {
    const controls = controlsOf(hero);
    const swiper = new Swiper(hero, {
      effect: 'fade',
      fadeEffect: { crossFade: true },
      speed: 700,
      rewind: true,
      grabCursor: true,
      keyboard: { enabled: true, onlyInViewport: true },
      autoplay: { delay: 6000, pauseOnMouseEnter: true, disableOnInteraction: false },
      navigation: { prevEl: controls.prevEl, nextEl: controls.nextEl },
      pagination: {
        el: controls.pagination,
        clickable: true,
        bulletClass: 'hero-bullet',
        bulletActiveClass: 'is-active',
        // Swiper labels only its default bullets, so custom ones carry their own name.
        renderBullet: (index, className) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = className;
          button.setAttribute('aria-label', hero.dataset.labelSlide.replace('{{index}}', String(index + 1)));
          button.append(document.createElement('span'));
          return button.outerHTML;
        },
      },
      a11y: a11y(hero),
      on: {
        autoplayTimeLeft: (instance, timeLeft, percentage) => hero.style.setProperty('--hero-progress', String(1 - percentage)),
        autoplayStop: () => hero.style.setProperty('--hero-progress', '1'),
      },
    });
    bindAutoplayToggle(swiper, controls.autoplayToggle);
  }

  document.querySelectorAll('[data-rail]').forEach((rail) => {
    const controls = controlsOf(rail);
    new Swiper(rail, {
      slidesPerView: 'auto',
      spaceBetween: 12,
      grabCursor: true,
      watchOverflow: true,
      navigation: { prevEl: controls.prevEl, nextEl: controls.nextEl },
      a11y: a11y(rail),
      breakpoints: { 700: { spaceBetween: 20 } },
    });
  });

  const gallery = document.querySelector('[data-gallery-main]');
  if (gallery && gallery.querySelectorAll('.swiper-slide').length > 1) {
    const controls = controlsOf(gallery);
    const thumbsElement = document.querySelector('[data-gallery-thumbs]');
    const thumbButtons = thumbsElement ? Array.from(thumbsElement.querySelectorAll('.gallery-thumb')) : [];
    const thumbs = thumbsElement ? new Swiper(thumbsElement, { slidesPerView: 'auto', spaceBetween: 10, freeMode: true, watchOverflow: true }) : null;
    const swiper = new Swiper(gallery, {
      speed: 450,
      rewind: true,
      grabCursor: true,
      keyboard: { enabled: true, onlyInViewport: true },
      autoplay: { delay: 5000, pauseOnMouseEnter: true, disableOnInteraction: true },
      navigation: { prevEl: controls.prevEl, nextEl: controls.nextEl },
      pagination: { el: controls.pagination, type: 'fraction' },
      a11y: a11y(gallery),
      on: {
        slideChange: (instance) => {
          thumbButtons.forEach((button, index) => {
            if (index === instance.activeIndex) button.setAttribute('aria-current', 'true');
            else button.removeAttribute('aria-current');
          });
          if (thumbs) thumbs.slideTo(Math.max(0, instance.activeIndex - 1));
        },
      },
    });
    thumbButtons.forEach((button, index) => button.addEventListener('click', () => {
      swiper.autoplay.stop();
      swiper.slideTo(index);
    }));
    bindAutoplayToggle(swiper, controls.autoplayToggle);
  }
})();
