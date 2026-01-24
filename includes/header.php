<!DOCTYPE html>
<html lang="<?= $currentLang ?? 'es' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <?php 
  if (isset($seo) && $seo) {
      echo $seo->render();
  } else {
      $t = $meta['title'] ?? $config['name'] ?? 'Wiki';
      echo '<title>' . htmlspecialchars($t) . '</title>';
      echo '<meta name="robots" content="noindex, nofollow">';
  }
  ?>

  <link rel="stylesheet" href="<?= $config['base_url'] ?>/assets/css/style.css?v=<?= time() ?><?= $config['app_version'] ?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/themes/prism-okaidia.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/plugins/toolbar/prism-toolbar.min.css" rel="stylesheet" />

  <?php $iconPath = $config['base_url'] . '/assets/favicons'; ?>
  <link rel="icon" type="image/png" href="<?= $iconPath ?>/favicon-96x96.png" sizes="96x96">
  <link rel="icon" type="image/svg+xml" href="<?= $iconPath ?>/favicon.svg">
  <link rel="shortcut icon" href="<?= $iconPath ?>/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $iconPath ?>/apple-touch-icon.png">
  <link rel="manifest" href="<?= $iconPath ?>/site.webmanifest">
  <link rel="mask-icon" href="<?= $iconPath ?>/safari-pinned-tab.svg" color="#14532d">
  <meta name="msapplication-TileColor" content="#ffffff">
  <meta name="theme-color" content="#14532d">

  <?php 
  // 1. Inyección de Snippets del motor de contenido
  if (isset($accumulatedHeader)) echo $accumulatedHeader; 

  // 2. Inyección de custom snippets
  Core\Helpers::renderSystemSnippet('system-header', $config);
  ?>

</head>
<body>
  <header>
    <div class="header-inner">
      <a href="<?= $config['base_url'] ?>/" class="site-title"><?= htmlspecialchars($config['name'] ?? 'Wiki') ?></a>
      
      <form action="<?= $config['base_url'] ?>/search" method="get" class="search-form">
          <input type="text" name="q" placeholder="Buscar..." required>
      </form>
    </div>
  </header>
