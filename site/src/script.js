(() => {
  const root = document.documentElement;
  const dark = () => (root.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')) === 'dark';

  document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    const theme = dark() ? 'light' : 'dark';
    root.dataset.theme = theme;
    try { localStorage.setItem('theme', theme); } catch {}
  });

  document.querySelectorAll('.code').forEach((block) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'copy';
    button.textContent = 'Copy';
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(block.querySelector('pre').innerText.trim());
        button.textContent = 'Copied';
      } catch {
        button.textContent = 'Select and copy';
      }
      setTimeout(() => { button.textContent = 'Copy'; }, 1600);
    });
    block.append(button);
  });
})();
