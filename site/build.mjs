import { cpSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const src = join(root, 'src');
const out = join(root, 'dist');

const site = {
  url: (process.env.SITE_URL || 'https://ledgerfly.nayeemdev.com').replace(/\/$/, ''),
  repo: 'https://github.com/nayeemdev/ledgerfly',
  playStore: process.env.PLAY_STORE_URL || '',
  appStore: process.env.APP_STORE_URL || '',
  year: String(new Date().getFullYear()),
};

const read = (path) => readFileSync(join(src, path), 'utf8');
const fill = (template, values) => template.replace(/\{\{(\w+)\}\}/g, (match, key) => values[key] ?? match);

const css = read('styles.css').replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, ' ').replace(/\s*([{}:;,>])\s*/g, '$1').trim();
const js = read('script.js').trim();
const layout = read('layout.html');

const playIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M22.018 13.298l-3.919 2.218-3.515-3.493 3.543-3.521 3.891 2.202a1.49 1.49 0 0 1 0 2.594zM1.337.924a1.486 1.486 0 0 0-.112.568v21.017c0 .217.045.419.124.6l11.155-11.087L1.337.924zm12.207 10.065l3.258-3.238L3.45.195a1.466 1.466 0 0 0-.946-.179l11.04 10.973zm0 2.067l-11 10.933c.298.036.612-.016.906-.183l13.324-7.54-3.23-3.21z"/></svg>';
const appleIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/></svg>';
const badge = (icon, small, big, href, note) => href
  ? `<a class="store" href="${href}" rel="noopener">${icon}<span><small>${small}</small><b>${big}</b></span></a>`
  : `<a class="store store-off" href="${note}">${icon}<span><small>${small}</small><b>${big}</b></span></a>`;
const storeBadges = [
  badge(playIcon, site.playStore ? 'Get it on' : 'Coming soon to', 'Google Play', site.playStore, '#android'),
  badge(appleIcon, site.appStore ? 'Download on the' : 'Not on the App Store yet', site.appStore ? 'App Store' : 'Build for iPhone', site.appStore, '#ios'),
].join('');

rmSync(out, { recursive: true, force: true });
cpSync(join(src, 'static'), out, { recursive: true });

const pages = [];

for (const file of readdirSync(join(src, 'pages'))) {
  const raw = read(join('pages', file));
  const [, meta, body] = raw.match(/^<!--([\s\S]*?)-->\n([\s\S]*)$/);
  const page = JSON.parse(meta);
  const values = { ...site, storeBadges };
  const html = fill(layout, {
    ...values,
    ...page,
    canonical: site.url + page.path,
    image: `${site.url}/og/${page.og}`,
    css,
    js,
    robots: page.path === '/404.html' ? 'noindex' : 'index,follow',
    schema: page.schema ? `<script type="application/ld+json">${JSON.stringify(page.schema).replace(/\{\{(\w+)\}\}/g, (m, key) => values[key] ?? m)}</script>` : '',
    content: fill(body, values),
  });

  const target = page.path.endsWith('/') ? join(out, page.path, 'index.html') : join(out, page.path);
  mkdirSync(dirname(target), { recursive: true });
  writeFileSync(target, html);
  if (page.path !== '/404.html') pages.push(page);
}

const today = new Date().toISOString().slice(0, 10);
writeFileSync(join(out, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${pages.map((page) => `  <url><loc>${site.url}${page.path}</loc><lastmod>${today}</lastmod></url>`).join('\n')}
</urlset>
`);
writeFileSync(join(out, 'robots.txt'), `User-agent: *\nAllow: /\n\nSitemap: ${site.url}/sitemap.xml\n`);

console.log(`Built ${pages.length} pages for ${site.url}`);
