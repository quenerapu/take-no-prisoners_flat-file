<footer>
      <div class="footer-inner">
          <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($config['name'] ?? 'Wiki') ?></p>
          <?php if (!empty($config['languages'])): ?>
          <p><?php
              $langLinks = [];
              foreach (array_keys($config['languages']) as $l) {
                  $langLinks[] = '<a href="' . $config['base_url'] . '/' . $l . '">' . strtoupper($l) . '</a>';
              }
              echo implode(' | ', $langLinks);
          ?></p>
          <?php endif; ?>
      </div>
  </footer>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/prism.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/plugins/autoloader/prism-autoloader.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/plugins/toolbar/prism-toolbar.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/plugins/line-numbers/prism-line-numbers.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js"></script>
  <script>
  if (typeof Prism !== 'undefined' && Prism.plugins && Prism.plugins.toolbar) {
    Prism.plugins.toolbar.registerButton('download-code', function(env) {
      var pre = env.element.parentNode;
      var filename = pre && pre.getAttribute('data-filename');
      if (!filename) return;
      var btn = document.createElement('button');
      btn.textContent = 'Download';
      btn.addEventListener('click', function() {
        var blob = new Blob([env.element.textContent], { type: 'text/plain' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(url);
      });
      return btn;
    });
  }
  </script>

  <?php 
  // 1. Inyección de Snippets del motor de contenido
  if (isset($accumulatedFooter)) echo $accumulatedFooter; 

  // 2. Inyección de custom snippets
  Core\Helpers::renderSystemSnippet('system-footer', $config);
  ?>

</body>
</html>
