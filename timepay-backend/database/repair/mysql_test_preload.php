<?php

// Used only with phpunit against a pre-created, converted disposable MySQL schema.
require dirname(__DIR__, 2).'/vendor/autoload.php';
Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;
