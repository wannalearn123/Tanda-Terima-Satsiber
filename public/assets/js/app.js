// UX ringan: default tanggal hari ini, konfirmasi reset, auto-hide alert.
document.addEventListener('DOMContentLoaded', () => {
  const t = document.getElementById('tanggal');
  if (t && !t.value) t.value = new Date().toISOString().slice(0, 10);
  const p = document.getElementById('pukul');
  if (p && !p.value) {
    const d = new Date();
    p.value = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }
  const r = document.getElementById('btnReset');
  if (r) r.addEventListener('click', (ev) => {
    if (!confirm('Kosongkan form? Data yang sudah tersimpan tidak berubah.')) ev.preventDefault();
  });
  // Papan tanda tangan: mouse + sentuh (Pointer Events), tanpa library.
  const cv = document.getElementById('sigpad');
  if (cv) {
    const ctx = cv.getContext('2d');
    const hidden = document.getElementById('tandatangan');
    const paint = () => { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cv.width, cv.height); };
    paint();
    ctx.strokeStyle = '#111'; ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    let drawing = false, drawn = false, last = null;
    const pos = (ev) => {
      const b = cv.getBoundingClientRect();
      return { x: (ev.clientX - b.left) * cv.width / b.width, y: (ev.clientY - b.top) * cv.height / b.height };
    };
    cv.addEventListener('pointerdown', (ev) => {
      drawing = true; drawn = true; last = pos(ev);
      try { cv.setPointerCapture(ev.pointerId); } catch (e) {}
    });
    cv.addEventListener('pointermove', (ev) => {
      if (!drawing) return;
      const p = pos(ev);
      ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
      last = p;
    });
    const stop = () => { drawing = false; };
    cv.addEventListener('pointerup', stop);
    cv.addEventListener('pointercancel', stop);
    const clear = document.getElementById('sigclear');
    if (clear) clear.addEventListener('click', () => { paint(); drawn = false; hidden.value = ''; });
    const form = cv.closest('form');
    if (form) {
      form.addEventListener('reset', () => { setTimeout(() => { paint(); drawn = false; hidden.value = ''; }, 0); });
      form.addEventListener('submit', () => { hidden.value = drawn ? cv.toDataURL('image/png') : ''; });
    }
  }
  document.querySelectorAll('.alert').forEach((el) => {
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 6000);
  });
});
