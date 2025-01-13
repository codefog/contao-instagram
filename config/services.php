<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autoconfigure()
        ->autowire()
        ->bind('$accessTokenTtl', '%instagram_access_token_ttl%')
        ->bind('$cacheTtl', '%instagram_cache_ttl%')
        ->bind('$projectDir', '%kernel.project_dir%')
    ;

    $services
        ->load('Codefog\\InstagramBundle\\', __DIR__ . '/../src')
        ->exclude(__DIR__ . '/../src/ContaoManager')
        ->exclude(__DIR__ . '/../src/FrontendModule')
    ;

    $services->set(\Codefog\InstagramBundle\Controller\InstagramController::class)->public();
    $services->set(\Codefog\InstagramBundle\EventListener\ModuleListener::class)->public();
};
