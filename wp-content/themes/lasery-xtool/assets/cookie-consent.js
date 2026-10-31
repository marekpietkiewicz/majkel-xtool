(() => {
  const banner = document.querySelector('[data-lasery-consent-banner]');
  if (!banner) return;

  const key = 'lasery_xtool_external_consent_v1';
  const lifetime = 180 * 24 * 60 * 60 * 1000;
  const attributes = ['src', 'srcset', 'poster', 'data', 'background', 'href', 'xlink:href', 'srcdoc', 'style'];
  let temporaryChoice = null;
  let opener = null;

  const readChoice = () => {
    try {
      const raw = localStorage.getItem(key);
      if (!raw) return temporaryChoice;
      const saved = JSON.parse(raw);
      if (!saved || saved.version !== 1 || !['allow', 'deny'].includes(saved.choice) ||
          !Number.isFinite(saved.expires) || saved.expires <= Date.now()) {
        localStorage.removeItem(key);
        return temporaryChoice;
      }
      return saved.choice;
    } catch (_) {
      return temporaryChoice;
    }
  };

  const updateContent = (choice) => {
    attributes.forEach((attribute) => {
      const dataAttribute = `data-lasery-consent-${attribute.replace(':', '-')}`;
      document.querySelectorAll(`[${dataAttribute}]`).forEach((element) => {
        const xlink = attribute === 'xlink:href' ? 'http://www.w3.org/1999/xlink' : '';
        if (choice === 'allow') {
          const value = element.getAttribute(dataAttribute);
          if (value !== null && element.getAttribute(attribute) !== value) {
            if (xlink) element.setAttributeNS(xlink, attribute, value);
            else element.setAttribute(attribute, value);
          }
        } else {
          if (xlink) element.removeAttributeNS(xlink, 'href');
          else element.removeAttribute(attribute);
        }
        if (attribute === 'src' && element.tagName === 'IFRAME') {
          element.hidden = choice !== 'allow';
        }
      });
    });
    document.querySelectorAll('[data-lasery-consent-css-type]').forEach((element) => {
      element.setAttribute('type', choice === 'allow' ? element.getAttribute('data-lasery-consent-css-type') : 'text/plain');
    });
    document.querySelectorAll('[data-lasery-consent-placeholder]').forEach((element) => {
      element.hidden = choice === 'allow';
    });
  };

  const choose = (choice) => {
    temporaryChoice = choice;
    try {
      localStorage.setItem(key, JSON.stringify({ version: 1, choice, expires: Date.now() + lifetime }));
    } catch (_) {
      // A blocked storage API keeps the choice only for the current page.
    }
    updateContent(choice);
    banner.hidden = true;
    if (opener) opener.focus();
  };

  banner.querySelectorAll('[data-lasery-consent-choice]').forEach((button) => {
    button.addEventListener('click', () => choose(button.getAttribute('data-lasery-consent-choice')));
  });
  document.querySelectorAll('[data-lasery-consent-open]').forEach((button) => {
    button.addEventListener('click', () => {
      opener = button;
      banner.hidden = false;
      const reject = banner.querySelector('[data-lasery-consent-choice="deny"]');
      if (reject) reject.focus();
    });
  });
  window.addEventListener('storage', (event) => {
    if (event.key !== key && event.key !== null) return;
    temporaryChoice = null;
    const choice = readChoice();
    updateContent(choice);
    banner.hidden = choice !== null;
  });

  const choice = readChoice();
  updateContent(choice);
  banner.hidden = choice !== null;
})();
