import PhotoSwipeLightbox from './lib/photoswipe/photoswipe-lightbox.esm.min.js';

const gallery = document.querySelector('[data-gallery-main]');
const links = gallery ? Array.from(gallery.querySelectorAll('a[data-pswp-width]')) : [];

if (links.length) {
  // Fetched after the page renders, so the gallery itself is not delayed.
  const styles = document.createElement('link');
  styles.rel = 'stylesheet';
  styles.href = new URL('./lib/photoswipe/photoswipe.css', import.meta.url).href;
  document.head.append(styles);

  const lightbox = new PhotoSwipeLightbox({
    dataSource: links.map((link) => ({
      src: link.href,
      width: Number(link.dataset.pswpWidth),
      height: Number(link.dataset.pswpHeight),
      alt: link.querySelector('img')?.alt || '',
    })),
    pswpModule: () => import('./lib/photoswipe/photoswipe.esm.min.js'),
    bgOpacity: 1,
    showHideAnimationType: 'fade',
  });
  // Keep the page gallery on the photo that was last viewed.
  lightbox.on('change', () => gallery.swiper?.slideTo(lightbox.pswp.currIndex));
  lightbox.init();

  gallery.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-pswp-width]');
    // Swiper cancels the click that ends a drag.
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
    event.preventDefault();
    gallery.swiper?.autoplay?.stop();
    lightbox.loadAndOpen(links.indexOf(link));
  });
}
