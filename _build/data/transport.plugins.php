<?php
/**
 * @package CustomUrls
 * @var MODx $modx
 * @var array $sources
 * @var array $scriptProperties
 */
$plugins= array();
$plugins['UserUrls'] = array(
    'description' => 'The original UserUrls plugin. Do not use at the same time as CustomUrls.',
    'events' => array(
        'OnPageNotFound' => array('priority' => 15,),
    ),
);
$plugins['CustomUrls'] = array(
    'description' => 'The main CustomUrls plugin.',
    'events' => array(
        'OnPageNotFound' => array('priority' => 10,),
        'OnLoadWebDocument' => array('priority' => 10,),
        'OnWebPagePrerender' => array('priority' => 10,),
    ),
);
return $plugins;
