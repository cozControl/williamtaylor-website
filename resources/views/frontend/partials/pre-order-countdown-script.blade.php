<script>
(() => {
 const update = () => document.querySelectorAll('[data-pre-order-campaign][data-countdown-target]').forEach(card => {
  const target = Date.parse(card.dataset.countdownTarget);
  const remaining = Number.isFinite(target) ? Math.max(0, Math.floor((target - Date.now()) / 1000)) : 0;
  const values = {days: Math.floor(remaining / 86400), hours: Math.floor((remaining % 86400) / 3600), minutes: Math.floor((remaining % 3600) / 60), seconds: remaining % 60};
  Object.entries(values).forEach(([unit, value]) => { const node = card.querySelector(`[data-countdown-unit="${unit}"]`); if (node) node.textContent = String(value).padStart(2, '0'); });
  card.toggleAttribute('data-countdown-expired', remaining === 0);
 });
 update();
 window.setInterval(update, 1000);
})();
</script>
