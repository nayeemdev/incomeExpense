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
  year: String(new Date().getFullYear()),
};

const read = (path) => readFileSync(join(src, path), 'utf8');
const fill = (template, values) => template.replace(/\{\{(\w+)\}\}/g, (match, key) => values[key] ?? match);

const css = read('styles.css').replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, ' ').replace(/\s*([{}:;,>])\s*/g, '$1').trim();
const js = read('script.js').trim();
const layout = read('layout.html');

const playStoreButton = site.playStore
  ? `<a class="btn btn-primary" href="${site.playStore}" rel="noopener">Get it on Google Play</a>`
  : '<span class="store-soon" aria-disabled="true">Google Play, coming soon</span>';

rmSync(out, { recursive: true, force: true });
cpSync(join(src, 'static'), out, { recursive: true });

const pages = [];

for (const file of readdirSync(join(src, 'pages'))) {
  const raw = read(join('pages', file));
  const [, meta, body] = raw.match(/^<!--([\s\S]*?)-->\n([\s\S]*)$/);
  const page = JSON.parse(meta);
  const values = { ...site, playStoreButton };
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
