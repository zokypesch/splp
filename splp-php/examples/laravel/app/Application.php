<?php

namespace App;

use Illuminate\Foundation\Application as BaseApplication;

class Application extends BaseApplication
{
    /**
     * The application namespace.
     *
     * @var string
     */
    protected $namespace = 'App\\';

    /**
     * Get the path to the application "app" directory.
     */
    public function path(string $path = ''): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'app'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the base path of the Laravel installation.
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}