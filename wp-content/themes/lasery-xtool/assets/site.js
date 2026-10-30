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
    // Links from the table of contents stop at scroll-padding-top; a section is current
    // once its heading has reached that line under the sticky header.
    let line = 0;
    let current = -2;
    let frame = 0;
    const measure = () => { line = (parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0) + 8; };
    const update = () => {
      frame = 0;
      // Read every position before writing styles.
      const rect = articleContent.getBoundingClientRect();
      const value = Math.min(1, Math.max(0, -rect.top / Math.max(rect.height - window.innerHeight * 0.6, 1)));
      let next = -1;
      headings.forEach((heading, index) => {
        if (heading && heading.getBoundingClientRect().top <= line) next = index;
      });
      progress.style.transform = `scaleX(${value})`;
      if (next === current) return;
      current = next;
      tocLinks.forEach((link, index) => {
        if (index === current) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
      });
    };
    const schedule = () => { frame = frame || requestAnimationFrame(update); };
    measure();
    update();
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', () => { measure(); schedule(); }, { passive: true });
  }

  /* Search: the small index of the current language is fetched once and searched in the browser. */
  const searchDialog = document.querySelector('[data-search]');
  const searchOpeners = document.querySelectorAll('[data-search-open]');
  if (searchDialog && searchOpeners.length && typeof searchDialog.showModal === 'function') {
    const form = searchDialog.querySelector('form');
    const input = form.querySelector('input[type="search"]');
    const results = searchDialog.querySelector('[data-search-results]');
    const status = searchDialog.querySelector('[data-search-status]');
    const labels = searchDialog.dataset;
    let entries = null;
    let loading = null;

    // Case- and accent-insensitive, so "laser swiatlowodowy" finds "Laser światłowodowy"
    // (the same folding as the results page without JavaScript).
    const normalize = (text) => String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l').replace(/ß/g, 's');
    const load = () => {
      loading = loading || fetch(labels.endpoint, { credentials: 'omit' })
        .then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
        .then((index) => {
          entries = index.map((item) => ({ item, title: normalize(item[1]), text: normalize(`${item[1]} ${item[2]} ${item[6]}`) }));
        })
        .catch(() => { loading = null; });
      return loading;
    };
    // Every word must match; words at the start of the title rank highest.
    const score = (entry, terms) => {
      let total = 0;
      for (const term of terms) {
        if (!entry.text.includes(term)) return 0;
        if (entry.title.startsWith(term)) total += 8;
        else if (entry.title.includes(` ${term}`)) total += 5;
        else if (entry.title.includes(term)) total += 3;
        else total += 1;
      }
      return total;
    };
    const element = (tag, className, text) => {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text) node.textContent = text;
      return node;
    };
    const resultLink = ([type, title, , url, image, price, label]) => {
      const link = element('a', `search-result search-result--${type === 'p' ? 'product' : 'article'}`);
      link.href = url;
      const media = element('span', 'search-result-media');
      if (image) {
        const img = element('img');
        img.src = image;
        img.alt = '';
        img.loading = 'lazy';
        media.append(img);
      }
      const body = element('span', 'search-result-body');
      body.append(element('span', 'search-result-title', title), element('span', 'search-result-meta', [label, price].filter(Boolean).join(' · ')));
      link.append(media, body);
      return link;
    };
    const render = () => {
      if (!entries) return;
      const query = input.value.trim();
      const terms = normalize(query).split(/\s+/).filter(Boolean);
      results.replaceChildren();
      if (!terms.length) {
        status.textContent = '';
        return;
      }
      const matches = entries
        .map((entry) => ({ item: entry.item, score: score(entry, terms) }))
        .filter((match) => match.score > 0)
        .sort((a, b) => b.score - a.score);
      // The group with the best match comes first, so Enter opens the best match.
      const groups = [['p', labels.labelProducts, 8], ['a', labels.labelArticles, 4]];
      if (matches.length && matches[0].item[0] === 'a') groups.reverse();
      let truncated = false;
      groups.forEach(([type, heading, limit]) => {
        const found = matches.filter((match) => match.item[0] === type);
        if (!found.length) return;
        truncated = truncated || found.length > limit;
        const group = element('section', 'search-group');
        group.append(element('p', 'search-group-title', heading), ...found.slice(0, limit).map((match) => resultLink(match.item)));
        results.append(group);
      });
      // Functions as replacements keep "$&" and similar in the query literal.
      if (!matches.length) results.append(element('p', 'search-empty', labels.labelEmpty.replace('%s', () => query)));
      if (truncated) {
        const all = element('a', 'search-all', labels.labelAll.replace('%d', () => String(matches.length)));
        all.href = `${form.action}?${new URLSearchParams(new FormData(form))}`;
        results.append(all);
      }
      status.textContent = labels.labelCount.replace('%d', () => String(matches.length));
    };
    const open = (event) => {
      if (event) event.preventDefault();
      if (searchDialog.open) return;
      searchDialog.showModal();
      input.focus();
      input.select();
      load().then(render);
    };

    searchOpeners.forEach((opener) => {
      opener.addEventListener('click', open);
      // Start downloading the index before the first click.
      opener.addEventListener('pointerenter', load, { once: true });
      opener.addEventListener('focus', load, { once: true });
    });
    // Loading again after a failed request, so typing retries.
    input.addEventListener('input', () => load().then(render));
    // Enter opens the best match; without one, the form shows the results page.
    form.addEventListener('submit', (event) => {
      const first = results.querySelector('a');
      if (first) {
        event.preventDefault();
        window.location.href = first.href;
      }
    });
    searchDialog.querySelector('[data-search-close]').addEventListener('click', () => searchDialog.close());
    searchDialog.addEventListener('click', (event) => {
      if (event.target === searchDialog) searchDialog.close();
    });
    searchDialog.addEventListener('keydown', (event) => {
      // A search field would first clear its text on Escape; the dialog closes at once instead.
      if (event.key === 'Escape') {
        // Not also the mobile menu, whose handler listens on the document.
        event.preventDefault();
        event.stopPropagation();
        searchDialog.close();
        return;
      }
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
      const items = [input, ...results.querySelectorAll('a')];
      const next = items[(items.indexOf(document.activeElement) + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length];
      event.preventDefault();
      next.focus();
    });
    document.addEventListener('keydown', (event) => {
      const typing = event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable="true"]');
      const shortcut = (event.key === 'k' && (event.metaKey || event.ctrlKey)) || (event.key === '/' && !typing && !event.metaKey && !event.ctrlKey && !event.altKey);
      if (shortcut) open(event);
    });
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
