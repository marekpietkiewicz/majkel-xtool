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
    // Tabbing out closes the list, like a click outside.
    details.addEventListener('focusout', (event) => {
      if (details.open && !details.contains(event.relatedTarget)) details.open = false;
    });
    // A language chosen here opens pages that have none in their address (typed or shared links).
    details.addEventListener('click', (event) => {
      const link = event.target.closest('a[hreflang]');
      if (link) document.cookie = `lasery_lang=${link.hreflang}; path=/; max-age=31536000; samesite=lax${window.location.protocol === 'https:' ? '; secure' : ''}`;
    });
  });

  /* Set contents: a larger photo of a part, as a card on hover or keyboard focus, or in a sheet after a tap on touch screens. */
  document.querySelectorAll('[data-set-previews]').forEach((section) => {
    const card = section.querySelector('[data-set-card]');
    const sheet = section.querySelector('[data-set-sheet]');
    if (!card || !sheet) return;
    const parts = '.variant-set-item[data-set-preview]';
    const hover = window.matchMedia('(hover: hover) and (pointer: fine)');
    const fill = (target, item) => {
      const link = item.querySelector('a');
      const image = target.querySelector('[data-set-image]');
      image.srcset = item.dataset.setPreviewSrcset;
      image.src = item.dataset.setPreview;
      target.querySelector('[data-set-title]').textContent = link.textContent.trim();
      target.querySelector('[data-set-details]').textContent = Array.from(item.querySelectorAll('small, .variant-set-quantity'), (detail) => detail.textContent.trim()).join(' · ');
      target.querySelector('[data-set-link]').href = link.href;
    };
    let current = null;
    let showTimer = 0;
    let hideTimer = 0;
    // Under the part, or above it when there is no room below; always within the window and under the header.
    const place = () => {
      const box = current.getBoundingClientRect();
      const ceiling = (header ? header.getBoundingClientRect().bottom : 0) + 8;
      const top = box.bottom + 8 + card.offsetHeight <= window.innerHeight - 8 ? box.bottom + 8 : Math.max(ceiling, box.top - card.offsetHeight - 8);
      card.style.top = `${top}px`;
      card.style.left = `${Math.max(8, Math.min(box.left, document.documentElement.clientWidth - card.offsetWidth - 8))}px`;
    };
    const show = (item) => {
      clearTimeout(showTimer);
      clearTimeout(hideTimer);
      if (current === item) return;
      current = item;
      fill(card, item);
      card.hidden = false;
      place();
    };
    const hide = () => {
      clearTimeout(showTimer);
      clearTimeout(hideTimer);
      card.hidden = true;
      current = null;
    };
    // A short delay, so moving the mouse across the list does not flash cards; the card itself can be hovered.
    section.addEventListener('pointerover', (event) => {
      if (event.pointerType !== 'mouse') return;
      const item = event.target.closest(parts);
      if (item) {
        clearTimeout(hideTimer);
        clearTimeout(showTimer);
        if (item !== current) showTimer = setTimeout(() => show(item), current ? 0 : 300);
      } else if (card.contains(event.target)) {
        clearTimeout(hideTimer);
      }
    });
    section.addEventListener('pointerout', (event) => {
      if (event.pointerType !== 'mouse') return;
      const to = event.relatedTarget;
      if (to && (to.closest(parts) || card.contains(to))) return;
      clearTimeout(showTimer);
      hideTimer = setTimeout(hide, 250);
    });
    section.addEventListener('focusin', (event) => {
      const item = event.target.closest(parts);
      if (item && event.target.matches(':focus-visible')) show(item);
    });
    section.addEventListener('focusout', (event) => {
      if (current && event.target.closest(parts) === current) hide();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && current) hide();
    });
    window.addEventListener('scroll', () => { if (current) place(); }, { passive: true });
    window.addEventListener('resize', () => { if (current) hide(); });
    // Touch screens have no hover: a tap on a part opens the sheet, whose button leads to the product.
    section.addEventListener('click', (event) => {
      const item = event.target.closest(parts);
      if (!item) return;
      // With a mouse the whole part leads to its page, like its name.
      if (hover.matches || typeof sheet.showModal !== 'function') {
        if (!event.target.closest('a')) item.querySelector('a').click();
        return;
      }
      event.preventDefault();
      fill(sheet, item);
      sheet.showModal();
    });
    sheet.addEventListener('click', (event) => {
      if (event.target === sheet || event.target.closest('[data-set-close]')) sheet.close();
    });
  });

  /* Cart: the number of items comes from the cart cookie, so pages stay cacheable for everyone. */
  const cartCount = document.querySelector('[data-cart-count]');
  const cartCookie = () => {
    const cookie = document.cookie.split('; ').find((part) => part.startsWith('lasery_cart='));
    return cookie ? cookie.slice('lasery_cart='.length) : '';
  };
  const showCartCount = () => {
    let count = 0;
    try {
      // The same entries the server accepts: "product:variant:quantity", at most 30.
      count = decodeURIComponent(cartCookie()).split('|').slice(0, 30)
        .filter((item) => /^[1-9]\d{0,19}:(?:[1-9]\d{0,19})?:(?:[1-9]\d?|100)$/.test(item))
        .reduce((sum, item) => sum + Number(item.split(':')[2]), 0);
    } catch (error) {
      count = 0;
    }
    cartCount.textContent = String(count);
    cartCount.hidden = !count;
  };
  if (cartCount) {
    showCartCount();
    window.addEventListener('pageshow', (event) => { if (event.persisted) showCartCount(); });
  }
  /* Quantities and prices of several pieces. */
  // A typed quantity the field's pattern accepts (1 to 100, leading zeros allowed), or NaN.
  const quantityOf = (input) => (input.validity.valid ? Number(input.value) : NaN);
  // A number out of range becomes the nearest allowed one; anything else, the last saved number.
  const normaliseQuantity = (input) => {
    const value = /^\s*\d+\s*$/.test(input.value) ? Number(input.value) : NaN;
    input.value = Number.isFinite(value) ? String(Math.min(100, Math.max(1, value))) : input.dataset.saved || input.defaultValue;
  };
  document.addEventListener('submit', (event) => event.target.querySelectorAll('[data-quantity]').forEach(normaliseQuantity), true);
  // Quantity fields take digits only: other typed characters are refused, pasted ones dropped (before other handlers see them).
  document.addEventListener('beforeinput', (event) => {
    if (event.target.matches('[data-quantity]') && event.data && /\D/.test(event.data)) event.preventDefault();
  });
  document.addEventListener('input', (event) => {
    const input = event.target;
    if (!input.matches('[data-quantity]') || !/\D/.test(input.value)) return;
    input.value = input.value.replace(/\D/g, '');
  }, true);
  // Cents in the page's language, written as the server writes prices: thousands always grouped ("1 541,75 €"
  // in Polish too), "€" where a locale writes the code.
  const formatPrice = (cents, currency, locale) => {
    const text = new Intl.NumberFormat(locale, { style: 'currency', currency, useGrouping: 'always' }).format(cents / 100);
    return currency === 'EUR' ? text.replace('EUR', '€') : text;
  };

  /* Cart confirmations show for a moment; the address drops the result, so a reload does not repeat it. */
  const forgetCartResult = () => {
    const url = new URL(window.location.href);
    if (!url.searchParams.has('cart')) return;
    url.searchParams.delete('cart');
    history.replaceState(history.state, '', url);
  };
  const toast = document.querySelector('[data-cart-toast]');
  let toastTimer = 0;
  const showToast = (message) => {
    clearTimeout(toastTimer);
    const show = () => {
      toast.textContent = message;
      toast.classList.add('is-shown');
      toastTimer = setTimeout(() => {
        toast.classList.remove('is-shown');
        toastTimer = setTimeout(() => { toast.textContent = ''; }, 300);
      }, 4000);
    };
    // The same message again is emptied for a moment first, so screen readers announce it again.
    if (toast.textContent !== message) {
      show();
      return;
    }
    toast.textContent = '';
    toastTimer = setTimeout(show, 100);
  };
  if (toast) {
    const message = toast.textContent.trim();
    if (message) showToast(message);
    if (/^(?:added|limit|updated|removed)$/.test(new URL(window.location.href).searchParams.get('cart') || '')) forgetCartResult();
  }
  // Errors stay; focus goes to them instead of the top of the page.
  const cartError = document.querySelector('[data-cart-error]');
  if (cartError) {
    cartError.tabIndex = -1;
    cartError.focus();
  }

  /* Product page: the chosen variant and the price of the typed quantity in the summary; adding keeps the page. */
  document.querySelectorAll('[data-order-form]').forEach((form) => {
    const variant = form.querySelector('[data-order-summary-variant]');
    const price = form.querySelector('[data-order-summary-price]');
    const quantity = form.querySelector('[data-quantity]');
    const showPrice = () => {
      const count = quantityOf(quantity);
      const { amount, currency } = price.dataset;
      if (!amount || Number.isNaN(count)) return;
      const { locale } = form.dataset;
      price.textContent = formatPrice(Number(amount) * count, currency, locale);
      if (count > 1) {
        const each = document.createElement('small');
        each.className = 'order-summary-each';
        each.textContent = form.dataset.each.replace('%s', formatPrice(Number(amount), currency, locale));
        price.append(each);
      }
    };
    const chooseVariant = (input) => {
      const option = input.closest('.variant-option');
      if (variant) variant.textContent = option.querySelector('.variant-option-title').textContent;
      if (input.dataset.amount) {
        Object.assign(price.dataset, { amount: input.dataset.amount, currency: input.dataset.currency });
        showPrice();
      } else {
        delete price.dataset.amount;
        price.textContent = option.querySelector('.variant-option-price').textContent;
      }
    };
    form.addEventListener('change', (event) => {
      const input = event.target;
      if (input === quantity) {
        normaliseQuantity(quantity);
        showPrice();
      } else if (input.name === 'variant' && input.type === 'radio') {
        chooseVariant(input);
      }
    });
    quantity.addEventListener('input', showPrice);
    // Some browsers keep the chosen variant after a reload.
    const restored = form.querySelector('input[type="radio"][name="variant"]:checked');
    if (restored) chooseVariant(restored);
    const cartLink = form.querySelector('[data-cart-link]');
    let adding = false;
    let native = false;
    form.addEventListener('submit', async (event) => {
      if (native) return;
      event.preventDefault();
      if (adding) return;
      adding = true;
      form.classList.add('is-adding');
      const before = cartCookie();
      let response = null;
      try {
        response = await fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
        if (!response.ok) throw new Error(String(response.status));
        // The answer is the cart page with a confirmation, or a page with the reason the product was not added,
        // which opens as it is (an unavailable variant is then no longer offered).
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (page.querySelector('[data-cart-error]')) {
          window.location.assign(response.url);
          return;
        }
        const added = page.querySelector('[data-cart-toast]');
        const message = added ? added.textContent.trim() : '';
        if (!message) throw new Error('No confirmation');
        showToast(message);
        document.querySelectorAll('[data-cart-error]').forEach((error) => error.remove());
        forgetCartResult();
        if (cartLink) cartLink.classList.add('btn', 'btn-secondary', 'btn-block');
        if (cartCount) showCartCount();
      } catch (error) {
        // The cookie tells whether the server added the product: then another page opens (the cart, or the
        // server's answer), so it is never added twice; otherwise the browser sends the form itself.
        if (cartCookie() !== before) {
          if (cartLink) window.location.assign(cartLink.href);
          else if (response && response.redirected) window.location.assign(response.url);
          else window.location.reload();
          return;
        }
        native = true;
        form.requestSubmit(event.submitter);
      }
      // Free again, unless another page is opening (the returns above keep it busy).
      adding = false;
      form.classList.remove('is-adding');
    });
    // Back from that page may restore this one as it was.
    window.addEventListener('pageshow', (event) => {
      if (!event.persisted) return;
      adding = false;
      form.classList.remove('is-adding');
    });
  });

  /* Cart: prices follow a typed quantity at once, changes are saved without leaving the page, and the server's new cart page confirms them. */
  const cartForm = document.querySelector('[data-cart-form]');
  if (cartForm) {
    const update = cartForm.querySelector('[data-cart-update]');
    const removeForm = document.getElementById('cart-remove');
    const sends = cartForm.querySelectorAll('[data-cart-send]');
    const quantities = () => cartForm.querySelectorAll('.cart-line [data-quantity]');
    quantities().forEach((input) => { input.dataset.saved = input.value; });
    let queue = Promise.resolve();
    let pending = 0;
    let timer = 0;
    let native = false;
    // Line values, the number of pieces and the total (when every line has a price) for the typed quantities.
    const showPrices = () => {
      const { locale } = cartForm.dataset;
      const sums = new Map();
      let pieces = 0;
      let priced = true;
      cartForm.querySelectorAll('[data-cart-line]:not(.is-removing)').forEach((line) => {
        const input = line.querySelector('[data-quantity]');
        const typed = quantityOf(input);
        const count = Number.isNaN(typed) ? Number(input.dataset.saved) : typed;
        const { amount, currency } = line.dataset;
        pieces += count;
        if (!amount) {
          priced = false;
          return;
        }
        sums.set(currency, (sums.get(currency) || 0) + Number(amount) * count);
        line.querySelector('.cart-line-total').textContent = formatPrice(Number(amount) * count, currency, locale);
      });
      cartForm.querySelectorAll('[data-cart-pieces]').forEach((element) => { element.textContent = cartForm.dataset.pieces.replace('%d', pieces); });
      if (!priced || !sums.size) return;
      const total = Array.from(sums, ([currency, cents]) => formatPrice(cents, currency, locale)).join(' + ');
      cartForm.querySelectorAll('[data-cart-sum]').forEach((element) => { element.textContent = total; });
    };
    // Lines still in the cart get their new prices; a number changed since it was sent stays as typed.
    const apply = (page, url) => {
      const fresh = new Map(Array.from(page.querySelectorAll('[data-cart-line]'), (line) => [line.dataset.cartLine, line]));
      const lines = Array.from(cartForm.querySelectorAll('[data-cart-line]'));
      if (!fresh.size || Array.from(fresh.keys()).some((key) => !lines.some((line) => line.dataset.cartLine === key))) {
        window.location.replace(url);
        return;
      }
      const focused = document.activeElement;
      lines.forEach((line, index) => {
        const next = fresh.get(line.dataset.cartLine);
        if (!next) {
          // Focus goes to a neighbour's quantity, so a repeated key press cannot remove the next product.
          if (line.contains(focused)) {
            const neighbour = lines.slice(index + 1).concat(lines.slice(0, index).reverse()).find((other) => fresh.has(other.dataset.cartLine));
            neighbour.querySelector('[data-quantity]').focus();
          }
          line.remove();
          return;
        }
        line.className = next.className;
        delete line.dataset.amount;
        delete line.dataset.currency;
        Object.assign(line.dataset, next.dataset);
        const parts = next.querySelectorAll('[data-cart-update-part]');
        line.querySelectorAll('[data-cart-update-part]').forEach((part, i) => part.replaceWith(parts[i]));
        const input = line.querySelector('[data-quantity]');
        const saved = next.querySelector('[data-quantity]').value;
        if (input.value === input.dataset.sent) input.value = saved;
        input.dataset.saved = saved;
      });
      const totals = page.querySelectorAll('[data-cart-total]');
      cartForm.querySelectorAll('[data-cart-total]').forEach((total, i) => total.replaceWith(totals[i]));
      // Numbers typed while this change was on its way keep their prices.
      showPrices();
      cartForm.elements.snapshot.value = page.querySelector('[name="snapshot"]').value;
      const freshSends = page.querySelectorAll('[data-cart-send]');
      sends.forEach((button, i) => { button.disabled = freshSends[i].disabled; });
      if (cartCount) showCartCount();
      const message = page.querySelector('[data-cart-toast]').textContent.trim();
      const total = cartForm.querySelector('.order-summary-price');
      if (message) showToast(`${message} ${cartForm.querySelector('.order-summary-total').textContent}: ${total.textContent}`);
    };
    // Changes go one after another, so the last response always holds the whole cart.
    const send = (form, submitter) => {
      pending += 1;
      queue = queue.then(async () => {
        try {
          if (native) return;
          // The field being typed in is left as it is; the server ignores a number it cannot read.
          quantities().forEach((input) => {
            if (input !== document.activeElement) normaliseQuantity(input);
            input.dataset.sent = input.value;
          });
          const body = new FormData(form);
          if (submitter.name) body.append(submitter.name, submitter.value);
          // Attributes, because form.action would be the removal form's field named "action".
          const response = await fetch(submitter.getAttribute('formaction') || form.getAttribute('action'), { method: 'POST', body, credentials: 'same-origin' });
          if (!response.ok) throw new Error(String(response.status));
          const page = new DOMParser().parseFromString(await response.text(), 'text/html');
          if (pending === 1) apply(page, response.url);
        } catch (error) {
          // Without a usable response the browser submits the change itself.
          native = true;
          form.requestSubmit(submitter);
        } finally {
          pending -= 1;
        }
      });
    };
    const flush = () => {
      clearTimeout(timer);
      timer = 0;
      send(cartForm, update);
    };
    const isQuantity = (element) => element.matches('.cart-line [data-quantity]');
    cartForm.addEventListener('input', (event) => {
      if (!isQuantity(event.target)) return;
      showPrices();
      clearTimeout(timer);
      timer = event.target.validity.valid ? setTimeout(flush, 500) : 0;
    });
    // A number out of range is brought within the limits (0 becomes 1: the bin removes a product).
    cartForm.addEventListener('change', (event) => {
      if (!isQuantity(event.target) || event.target.validity.valid) return;
      normaliseQuantity(event.target);
      showPrices();
      flush();
    });
    // Enter in a quantity saves it instead of sending the order.
    cartForm.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' || !isQuantity(event.target)) return;
      event.preventDefault();
      normaliseQuantity(event.target);
      showPrices();
      flush();
    });
    let sending = false;
    cartForm.addEventListener('submit', (event) => {
      // Only the fallback above submits the update button.
      if (event.submitter === update) return;
      // The order includes the typed quantities; it waits for changes on their way, so the cart it empties stays empty.
      clearTimeout(timer);
      timer = 0;
      if (pending) {
        event.preventDefault();
        const submitter = event.submitter;
        queue.then(() => { if (!native && !submitter.disabled) cartForm.requestSubmit(submitter); });
        return;
      }
      // One order per click: a second submit would only hit the rate limit. Back restores the buttons.
      sending = true;
      sends.forEach((button) => { button.disabled = true; });
    });
    window.addEventListener('pageshow', () => {
      if (!sending) return;
      sending = false;
      sends.forEach((button) => { button.disabled = false; });
    });
    if (removeForm) {
      removeForm.addEventListener('submit', (event) => {
        if (native) return;
        event.preventDefault();
        const line = event.submitter.closest('.cart-line');
        if (line.classList.contains('is-removing')) return;
        line.classList.add('is-removing');
        showPrices();
        if (timer) flush();
        send(removeForm, event.submitter);
      });
    }
  }

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
    const swiper = new Swiper(rail, {
      slidesPerView: 'auto',
      spaceBetween: 12,
      grabCursor: true,
      watchOverflow: true,
      navigation: { prevEl: controls.prevEl, nextEl: controls.nextEl },
      a11y: { ...a11y(rail), scrollOnFocus: false },
      breakpoints: { 700: { spaceBetween: 20 } },
    });
    // Keyboard focus slides a card that is out of view into it.
    rail.addEventListener('focusin', (event) => {
      const slide = event.target.closest('.swiper-slide');
      if (!slide) return;
      rail.scrollLeft = 0;
      const box = slide.getBoundingClientRect();
      const view = rail.getBoundingClientRect();
      if (box.left < view.left || box.right > view.right) swiper.slideTo(swiper.slides.indexOf(slide));
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
