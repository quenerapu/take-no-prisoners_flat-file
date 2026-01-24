<footer>
      <div class="footer-inner">
          <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($config['name'] ?? 'Wiki') ?></p>
      </div>
  </footer>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/prism.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/plugins/autoloader/prism-autoloader.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/plugins/toolbar/prism-toolbar.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js"></script>

  <?php 
  // 1. Inyección de Snippets del motor de contenido
  if (isset($accumulatedFooter)) echo $accumulatedFooter; 

  // 2. Inyección de custom snippets
  Core\Helpers::renderSystemSnippet('system-footer', $config);
  ?>

</body>
</html>
