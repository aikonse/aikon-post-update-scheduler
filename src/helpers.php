<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


function view(string $template, array $data = []): void
{
    $templatePath = __DIR__ . '/../templates/' . $template . '.php';

    if (!file_exists($templatePath)) {
        throw new \Exception("Template not found: " . $template);
    }

    extract($data);
    include $templatePath;
}