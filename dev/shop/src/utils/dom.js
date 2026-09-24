// Small DOM helpers — escape HTML, build elements declaratively.
const HTML_ESCAPE = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

export function escapeHTML(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => HTML_ESCAPE[c]);
}

export function formatPrice(value) {
  return `¥${Number(value || 0).toFixed(2)}`;
}

export function debounce(fn, wait = 200) {
  let timer;
  return function debounced(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), wait);
  };
}

// Build an inline SVG placeholder shown while the real image loads (or if it
// fails to load). This keeps the layout stable without bundling binary assets.
export function placeholderSVG(label, color = '#94a3b8') {
  const safe = escapeHTML(label).slice(0, 24);
  return `data:image/svg+xml;utf8,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480">` +
      `<rect width="640" height="480" fill="${color}" opacity="0.18"/>` +
      `<rect x="40" y="40" width="560" height="400" fill="none" stroke="${color}" stroke-width="2" stroke-dasharray="8 8" rx="12"/>` +
      `<text x="320" y="252" font-family="system-ui, sans-serif" font-size="28" fill="${color}" text-anchor="middle">${safe}</text>` +
      `</svg>`,
  )}`;
}
