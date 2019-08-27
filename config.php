<?php namespace EENPC;
  $config = array(
    'ai_key' => $_ENV['EENPC_AI_KEY'],
    'username' => $_ENV['EENPC_USERNAME'],
    'base_url' => 'http://www.earthempires.com/api',    //Don't change this unless qz tells you to =D it needs to end in /api either way
    'server' => 'ai',       //don't change this
    'turnsleep' => 500000,    //don't get too ridiculously fast; 500000 is half a second
    'save_settings_file' => 'settings.json'
);
