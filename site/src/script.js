(() => {
  const root = document.documentElement;
  const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const dark = () => (root.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')) === 'dark';

  document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    const theme = dark() ? 'light' : 'dark';
    root.dataset.theme = theme;
    try { localStorage.setItem('theme', theme); } catch {}
  });

  const header = document.querySelector('[data-header]');
  const menu = document.querySelector('[data-menu]');
  const setMenu = (open) => {
    header?.classList.toggle('open', open);
    menu?.setAttribute('aria-expanded', String(open));
  };
  menu?.addEventListener('click', () => setMenu(!header.classList.contains('open')));
  header?.querySelectorAll('.nav a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
  const onScroll = () => header?.classList.toggle('scrolled', scrollY > 8);
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  const reveal = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('in');
      reveal.unobserve(entry.target);
    });
  }, { rootMargin: '0px 0px -8% 0px' });
  document.querySelectorAll('[data-reveal]').forEach((el) => reveal.observe(el));

  const counters = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      counters.unobserve(entry.target);
      const el = entry.target;
      const target = Number(el.dataset.count);
      if (calm) return;
      const start = performance.now();
      const tick = (now) => {
        const t = Math.min(1, (now - start) / 1600);
        el.textContent = Math.round(target * (1 - Math.pow(1 - t, 4))).toLocaleString('en-US');
        if (t < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  }, { threshold: 0.6 });
  document.querySelectorAll('[data-count]').forEach((el) => counters.observe(el));

  const movers = [...document.querySelectorAll('[data-speed]')];
  const tilt = document.querySelector('[data-tilt]');
  let queued = false;
  const frame = () => {
    queued = false;
    const vh = innerHeight;
    if (tilt) {
      const top = tilt.getBoundingClientRect().top;
      tilt.style.setProperty('--p', Math.min(1, Math.max(0, 1 - (top - vh * 0.2) / (vh * 0.55))).toFixed(3));
    }
    for (const el of movers) {
      const box = el.parentElement.getBoundingClientRect();
      if (box.bottom < -300 || box.top > vh + 300) continue;
      const offset = box.top + box.height / 2 - vh / 2;
      el.style.setProperty('--py', `${(offset * Number(el.dataset.speed)).toFixed(1)}px`);
    }
  };
  if (!calm) {
    addEventListener('scroll', () => { if (!queued) { queued = true; requestAnimationFrame(frame); } }, { passive: true });
    addEventListener('resize', frame);
    frame();
  }

  document.querySelectorAll('.marquee-track').forEach((track) => {
    track.append(...[...track.children].map((chip) => {
      const copy = chip.cloneNode(true);
      copy.setAttribute('aria-hidden', 'true');
      return copy;
    }));
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

  addEventListener('DOMContentLoaded', () => {
    if (calm || !window.Lenis) return;
    const lenis = new Lenis({ autoRaf: true, anchors: { offset: -84 }, lerp: 0.09 });
    lenis.on('scroll', onScroll);
  });
})();
