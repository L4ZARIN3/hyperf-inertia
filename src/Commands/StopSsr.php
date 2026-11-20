<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Context\ApplicationContext;

use function Hyperf\Config\config;

#[Command]
class StopSsr extends HyperfCommand
{
    protected ?string $name = 'inertia:stop-ssr';

    protected string $description = 'Stop the Inertia SSR server';

    /**
     * Stop the SSR server.
     */
    public function handle(): int
    {
        $url = str_replace('/render', '', config('inertia.ssr.url', 'http://127.0.0.1:13714')) . '/shutdown';

        try {
            ApplicationContext::getContainer()->get(Client::class)->request('GET', $url);
        } catch (GuzzleException $e) {
            // The SSR server closes the connection as soon as it receives the
            // shutdown request, which Guzzle reports as a connection error.
            // Any error means the server is either already stopped or unreachable.
            $this->info('Inertia SSR server is not running or is already stopped.');

            return self::SUCCESS;
        }

        $this->info('Inertia SSR server stopped.');

        return self::SUCCESS;
    }
}
