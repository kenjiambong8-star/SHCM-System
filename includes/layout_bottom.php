  </main>
</div>
<script>
(function () {
  var b = document.getElementById('menuBtn'), n = document.getElementById('sideNav');
  if (!b || !n) return;
  b.addEventListener('click', function () {
    var open = n.classList.toggle('open');
    b.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
</script>
</body>
</html>