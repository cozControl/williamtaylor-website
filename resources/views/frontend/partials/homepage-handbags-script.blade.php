@if($homepageHandbags['managed'])
<script>
(() => {
 const root = document.getElementById('root');
 if (!root) return;
 let activeHero = null;
 let timer = null;
 const initialize = () => {
  const hero = root.querySelector('[data-homepage-handbags] [data-handbags-slideshow]');
  if (hero === activeHero) return;
  clearInterval(timer);
  activeHero = hero;
  if (!hero) return;
  const slides = [...hero.querySelectorAll('[data-handbags-slide]')];
  const dots = [...hero.querySelectorAll('[data-handbags-dot]')];
  let current = 0;
  const show = index => {
   current = index;
   slides.forEach((slide, position) => {
    slide.style.opacity = position === index ? '1' : '0';
    slide.style.transform = position === index ? 'scale(1)' : 'scale(1.05)';
   });
   dots.forEach((dot, position) => {
    dot.classList.toggle('bg-wt-gold', position === index);
    dot.classList.toggle('w-6', position === index);
    dot.classList.toggle('bg-wt-cream/50', position !== index);
    dot.setAttribute('aria-pressed', String(position === index));
   });
  };
  dots.forEach((dot, index) => dot.addEventListener('click', () => show(index)));
  if (slides.length > 1) timer = setInterval(() => { if (hero.isConnected) show((current + 1) % slides.length); else initialize(); }, 5000);
 };
 new MutationObserver(initialize).observe(root, {childList: true, subtree: true});
 initialize();
})();
</script>
@endif
