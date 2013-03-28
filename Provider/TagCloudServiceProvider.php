<?php

namespace TagCloud\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use TagCloud\Engine\Builder;
use TagCloud\Engine\Repository;
use TagCloud\Engine\Storage;
use Bolt\StorageEvent;
use Bolt\StorageEvents;

require_once __DIR__ . '/../Engine/Builder.php';
require_once __DIR__ . '/../Engine/Storage.php';
require_once __DIR__ . '/../Engine/Repository.php';

class TagCloudServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['tagcloud.repository'] = $app->share(function($app) {
            return new Repository($app['db']);
        });
        $app['tagcloud.builder'] = $app->share(function($app) {
            return new Builder($app['config'], $app['tagcloud.repository']);
        });
        $app['tagcloud.storage'] = $app->share(function($app) {
            return new Storage($app['tagcloud.builder'], $app['cache']);
        });

        $app['dispatcher']->addListener(StorageEvents::postSave, function(StorageEvent $event) use ($app) {
            $app['tagcloud.storage']->deleteCloud($event->getContent()->contenttype['slug']);
        });
    }

    public function boot(Application $app)
    {
    }
}