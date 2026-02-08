<?php
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

$titulo = ($currentLang === 'en') ? "Search results for: " : "Resultados para: ";
echo "<h1>" . $titulo . htmlspecialchars($query) . "</h1>";

if (mb_strlen($query) < 2) {
    $error = ($currentLang === 'en') ? "Search term too short. Please enter at least 2 characters." : "El término de búsqueda es demasiado corto. Por favor escribe al menos 2 caracteres.";
    echo "<p>$error</p>";
} else {
    if (class_exists('Core\Search')) {
        
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
            $noResults = ($currentLang === 'en') ? "No results found for your search." : "No se encontraron resultados que coincidan con tu búsqueda.";
            echo "<p>$noResults</p>";
        }
    }
}
