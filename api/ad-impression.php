<?php
declare(strict_types=1);

require_once rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/') . '/config.php';
require __DIR__ . '/ad-event.php';

recordAdEvent('impression');
