@if($homepageClientStories['managed'])
<script>
(() => {
 const root = document.getElementById('root');
 if (!root || !('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
 const known = new WeakSet();
 const observer = new IntersectionObserver(entries => entries.forEach(entry => {
  if (!entry.isIntersecting) return;
  const card = entry.target;
  card.animate([{opacity:0,transform:'translateY(30px)'},{opacity:1,transform:'translateY(0)'}], {duration:600,delay:Number(card.dataset.storyDelay),fill:'backwards'});
  observer.unobserve(card);
 }), {threshold:0});
 const initialize = () => root.querySelectorAll('[data-homepage-client-stories] [data-client-story-card]').forEach((card, index) => {
  if (known.has(card)) return;
  known.add(card);
  card.dataset.storyDelay = String(index * 150);
  observer.observe(card);
 });
 new MutationObserver(initialize).observe(root, {childList:true,subtree:true});
 initialize();
})();
</script>
@endif
