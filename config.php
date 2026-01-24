<?php
return [
  'app_name' => 'Take no prisoners',
  'app_version' => '1.0.0',
  'cache_enabled' => false,
  'base_url'    => 'https://your-domain.com',
  'name'        => 'Take no prisoners',
  'description' => 'Whatever you want.',
  'author'      => 'Your name',
  'twitter'     => '@your-username',
  'default_img' => '/assets/default-share.jpg',
  'languages'   => [
    'es' => [
      'name' => 'Español',
      'date' => function($ts) {
        $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        return date('j', $ts) . ' de ' . $meses[date('n', $ts)] . ' de ' . date('Y', $ts);
        }
    ],
    'en' => [
      'name' => 'English',
      'date' => function($ts) { return date('F j, Y', $ts); }
    ],
  ]
];
