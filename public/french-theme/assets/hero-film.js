// A real video source is enabled only after the finished clip is available.
export async function mountHeroFilm() {
  const hero = document.querySelector('[data-film-hero]');
  const video = hero?.querySelector('[data-hero-film]');
  if (!video) return;
  let config;
  try {
    const response = await fetch('./assets/film/film.json');
    if (!response.ok) return;
    config = await response.json();
    if (config.status !== 'ready' || !config.src) return;
    const source = new URL(config.src, response.url);
    if (source.origin !== location.origin) return;
    video.src = source.href;
  } catch { return; }
  const toggle = hero.querySelector('[data-film-toggle]');
  const label = hero.querySelector('[data-film-caption]');
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  let userPaused = reducedMotion.matches || Boolean(navigator.connection?.saveData);
  video.preload = userPaused ? 'metadata' : 'auto';
  let visible = true;
  video.muted = true;
  video.loop = true;
  video.playsInline = true;
  function syncButton() {
    toggle.hidden = false;
    toggle.textContent = video.paused ? 'Phát phim' : 'Tạm dừng phim';
    toggle.setAttribute('aria-pressed', String(!video.paused));
  }
  async function syncPlayback() {
    if (!visible || document.hidden || userPaused) video.pause();
    else { try { await video.play(); } catch { userPaused = true; } }
    syncButton();
  }
  video.addEventListener('loadeddata', () => {
    video.hidden = false;
    hero.classList.add('has-film');
    hero.querySelector('[data-film-meta]').textContent = 'LE FILM DE TOÀN · 13 SECONDES';
    syncPlayback();
  }, { once:true });
  video.addEventListener('error', () => {
    hero.classList.remove('has-film');
    video.hidden = true;
    toggle.hidden = true;
  });
  video.addEventListener('timeupdate', () => {
    label.textContent = video.currentTime < 5 ? 'Bước qua cửa xanh, gặp một khoảng trời riêng.' : video.currentTime < 8 ? 'Để nắng và hoa kể tiếp câu chuyện.' : 'Một tách cà phê, được làm bằng cả sự chăm chút.';
  });
  toggle.addEventListener('click', () => { userPaused = !video.paused; syncPlayback(); });
  const observer = new IntersectionObserver(entries => { visible = entries[0].isIntersecting; syncPlayback(); }, { threshold:0.03 });
  observer.observe(hero);
  document.addEventListener('visibilitychange', syncPlayback);
  reducedMotion.addEventListener('change', () => { userPaused = reducedMotion.matches; syncPlayback(); });
  window.addEventListener('pagehide', event => { if (!event.persisted) { observer.disconnect(); video.pause(); } }, { once:true });
  syncButton();
  video.load();
  syncPlayback();
}
