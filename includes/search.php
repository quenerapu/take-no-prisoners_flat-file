<?php
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

echo "<h1>Resultados para: " . htmlspecialchars($query) . "</h1>";

if (strlen($query) < 2) {
    echo "<p>El término de búsqueda es demasiado corto. Por favor escribe al menos 2 caracteres.</p>";
} else {
    if (class_exists('Core\Search')) {
        
        // El primer parámetro es nulo porque PDO ha desaparecido
        $searchEngine = new Core\Search(null, $config, $currentLang ?? 'es');
        $results = $searchEngine->search($query);

        if (!empty($results)) {
            echo '<ul class="search-results">';
            foreach ($results as $item) {
                echo '<li class="search-item">';
                echo '  <h3><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['title']) . '</a></h3>';
                echo '  <p>' . htmlspecialchars($item['excerpt']) . '</p>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo "<p>No se encontraron resultados que coincidan con tu búsqueda.</p>";
        }
    }
}
