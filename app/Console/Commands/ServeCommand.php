<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;

/**
 * `php artisan serve` with PHP's own form-body parsing disabled, so the
 * ParseFormBody middleware receives the raw request body (see that class).
 */
class ServeCommand extends BaseServeCommand
{
    protected function serverCommand()
    {
        $command = parent::serverCommand();

        array_splice($command, 1, 0, ['-d', 'enable_post_data_reading=0']);

        return $command;
    }
}
