(() => {
  const root = document.querySelector('[data-lasery-gallery]');
  if (!root || !window.wp || !wp.media) return;
  const list = root.querySelector('[data-gallery-list]');
  const mode = root.querySelector('[data-gallery-mode]');
  const status = root.querySelector('[data-gallery-status]');
  const add = root.querySelector('[data-gallery-add]');
  const restore = root.querySelector('[data-gallery-restore]');
  const setManual = () => { mode.value = 'manual'; status.textContent = 'Własna galeria zostanie zapisana po aktualizacji produktu.'; };
  const revealButtons = () => root.querySelectorAll('[data-gallery-up], [data-gallery-down], [data-gallery-remove]').forEach((button) => { button.hidden = false; });
  revealButtons();
  add.hidden = false;
  restore.hidden = false;
  list.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (!button) return;
    const item = button.closest('[data-gallery-item]');
    if (!item) return;
    if (button.matches('[data-gallery-remove]')) item.remove();
    else if (button.matches('[data-gallery-up]') && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
    else if (button.matches('[data-gallery-down]') && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
    else return;
    setManual();
  });
  add.addEventListener('click', () => {
    const frame = wp.media({ title: 'Wybierz zdjęcia do galerii', button: { text: 'Dodaj zdjęcia' }, library: { type: 'image' }, multiple: true });
    frame.on('select', () => {
      const existing = new Set(Array.from(list.querySelectorAll('input[name="lasery_gallery_ids[]"]'), (field) => field.value));
      let limited = false;
      frame.state().get('selection').each((attachment) => {
        const image = attachment.toJSON();
        const id = String(image.id);
        if (existing.has(id)) return;
        if (existing.size >= 40) { limited = true; return; }
        existing.add(id);
        const item = document.createElement('li');
        item.dataset.galleryItem = '';
        const preview = document.createElement('img');
        preview.src = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
        preview.alt = '';
        preview.style.cssText = 'max-width:80px;height:auto;vertical-align:middle';
        const field = document.createElement('input');
        field.type = 'hidden'; field.name = 'lasery_gallery_ids[]'; field.value = id;
        const title = document.createElement('span'); title.textContent = image.title || '';
        item.append(preview, field, title);
        [['galleryUp', 'W górę'], ['galleryDown', 'W dół'], ['galleryRemove', 'Usuń']].forEach(([name, label]) => {
          const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.dataset[name] = ''; button.textContent = label; item.append(button);
        });
        list.append(item);
      });
      setManual();
      if (limited) status.textContent = 'Możesz wybrać maksymalnie 40 zdjęć. Pozostałe nie zostały dodane.';
    });
    frame.open();
  });
  restore.addEventListener('click', () => {
    mode.value = 'auto';
    status.textContent = 'Automatyczna galeria zostanie przywrócona po aktualizacji produktu.';
  });
})();
