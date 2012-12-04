<?php
Autoloader::add_core_namespace('Doorman');
Autoloader::add_namespace('Doorman', __DIR__.'/classes/');

\Doorman::_init();