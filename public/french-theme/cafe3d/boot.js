const roots = document.querySelectorAll('[data-cafe3d]');
const observer = new IntersectionObserver(entries => {
  for (const entry of entries) {
    if (!entry.isIntersecting) continue;
    observer.unobserve(entry.target);
    import('./scene.js').then(({ mountCafe }) => mountCafe(entry.target)).catch(() => {
      entry.target.classList.add('cafe3d-fallback');
      entry.target.querySelector('[data-status]').textContent = 'Chưa tải được khung cảnh 3D. Vui lòng tải lại trang.';
      entry.target.querySelectorAll('button').forEach(button => { button.disabled = true; });
    });
  }
}, { rootMargin: '150px' });
roots.forEach(root => observer.observe(root));
