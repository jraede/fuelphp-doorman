<?php
//Autoloader::add_core_namespace('Doorman');
Autoloader::add_namespace('Doorman', __DIR__.'/classes/');
Autoloader::add_classes(array(
    'Doorman\\Doorman'=>__DIR__.'/classes/doorman.php'
));
Autoloader::alias_to_namespace('Doorman\\Doorman');

\Config::load('doorman', 'doorman');