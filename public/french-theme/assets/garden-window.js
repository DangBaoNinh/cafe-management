// Decorative foliage never intercepts links, gestures or keyboard focus.
export function mountGardenWindow() {
  document.body.classList.add('jardin-window-page');
  if (document.querySelector('[data-botanical-frame]')) return;
  const frame = document.createElement('div');
  frame.className = 'botanical-frame';
  frame.dataset.botanicalFrame = '';
  frame.setAttribute('aria-hidden', 'true');
  const foliageUrl = new URL('./bougainvillea-left.png', import.meta.url).href;
  frame.innerHTML = `<div class="botanical-wing botanical-wing-left"><img src="${foliageUrl}" alt="" width="1024" height="1536" decoding="async"></div><div class="botanical-wing botanical-wing-right"><img src="${foliageUrl}" alt="" width="1024" height="1536" decoding="async"></div>`;
  document.body.append(frame);
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  const removers = [];
  let manuallyPaused = false;
  function sync() {
    const paused = manuallyPaused || reducedMotion.matches || document.hidden;
    frame.classList.toggle('is-paused', paused);
    document.querySelectorAll('[data-foliage-toggle]').forEach(button => {
      button.hidden = reducedMotion.matches;
      button.setAttribute('aria-pressed', String(!paused));
      button.textContent = paused ? 'Cho cây lá đung đưa' : 'Dừng cây lá đung đưa';
    });
  }
  document.querySelectorAll('[data-foliage-toggle]').forEach(button => {
    const toggle = () => { manuallyPaused = !frame.classList.contains('is-paused'); sync(); };
    button.addEventListener('click', toggle);
    removers.push(() => button.removeEventListener('click', toggle));
  });
  document.addEventListener('visibilitychange', sync);
  reducedMotion.addEventListener('change', sync);
  sync();
  return () => {
    removers.forEach(remove => remove());
    document.removeEventListener('visibilitychange', sync);
    reducedMotion.removeEventListener('change', sync);
    frame.remove();
    document.body.classList.remove('jardin-window-page');
  };
}
