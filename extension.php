<?php

namespace TagCloud
{
    use Bolt\BaseExtension;
    use TagCloud\Provider\TagCloudServiceProvider;

    class Extension extends BaseExtension
    {
        /**
         * Info block for TagCloud Extension.
         */
        function info()
        {
            $data = array(
                'name' => "TagCloud",
                'description' => "This extension includes tag-cloud generation and helpers to display these clouds",
                'keywords' => "bolt, extension, tagcloud",
                'author' => "Aleksey Orlov",
                'link' => "https://github.com/axsy/bolt-extension-tagcloud",
                'version' => "0.1",
                'required_bolt_version' => "1.0.3",
                'highest_bolt_version' => "1.0.3",
                'type' => "General",
                'first_releasedate' => "2013-03-27",
                'latest_releasedate' => "2013-03-27",
                'dependencies' => "",
                'priority' => 10
            );

            return $data;
        }

        function initialize()
        {
            $this->app->register(new TagCloudServiceProvider());
        }
    }
}

namespace TagCloud\Provider
{
    use Silex\Application;
    use Silex\ServiceProviderInterface;
    use TagCloud\Engine\Builder;
    use TagCloud\Engine\Repository;
    use TagCloud\Engine\Storage;
    use Bolt\StorageEvent;
    use Bolt\StorageEvents;

    class TagCloudServiceProvider implements ServiceProviderInterface
    {
        public function register(Application $app)
        {
            $app['tagcloud.repository'] = $app->share(function ($app) {
                return new Repository($app['db']);
            });
            $app['tagcloud.builder'] = $app->share(function ($app) {
                return new Builder($app['config'], $app['tagcloud.repository']);
            });
            $app['tagcloud.storage'] = $app->share(function ($app) {
                return new Storage($app['tagcloud.builder'], $app['cache']);
            });

            $app['dispatcher']->addListener(StorageEvents::postSave, function (StorageEvent $event) use ($app) {
                $app['tagcloud.storage']->deleteCloud($event->getContent()->contenttype['slug']);
            });
        }

        public function boot(Application $app)
        {
        }
    }
}

namespace TagCloud\Engine
{
    use TagCloud\Engine\Exception\NoTagsTaxonomiesAvailableException;
    use TagCloud\Engine\Exception\NoTaxonomiesAvailableException;
    use TagCloud\Engine\Exception\UnknownContentTypeException;
    use Doctrine\Common\Cache\CacheProvider;
    use Doctrine\DBAL\Connection;
    use PDO;

    class Storage
    {
        protected $cache;

        public function __construct(Builder $builder, CacheProvider $cache)
        {
            $this->builder = $builder;
            $this->cache = $cache;
        }

        public function fetchCloud($contentType)
        {
            $cloud = null;
            $key = $this->getKeyFor($contentType);

            if ($this->cache->contains($key)) {
                $cloud = $this->cache->fetch($key);
            } else {
                $cloud = $this->builder->buildCloudFor($contentType);
                $this->cache->save($key, $cloud);
            }

            return $cloud;
        }

        public function deleteCloud($contentType)
        {
            $key = $this->getKeyFor($contentType);
            if ($this->cache->contains($key)) {
                $this->cache->delete($key);
            }
        }

        protected function getKeyFor($contentType)
        {
            return 'tagcloud_' . $contentType;
        }
    }

    class Repository
    {
        /**
         * @var \Doctrine\DBAL\Connection
         */
        protected $conn;

        public function __construct(Connection $conn)
        {
            $this->conn = $conn;
        }

        public function getTaxonomyGroupFor($contentType, $taxonomyType)
        {
            $stmt = $this
                ->conn
                ->createQueryBuilder()
                ->select('bt.slug')
                ->addSelect('COUNT(bt.id) AS count')
                ->from('bolt_taxonomy', 'bt')
                ->groupBy('bt.slug')
                ->where('bt.taxonomytype = :taxonomyType')
                ->andWhere('bt.contenttype = :contentType')
                ->setParameters(array(
                    ':taxonomyType' => $taxonomyType,
                    ':contentType' => $contentType
                ))
                ->execute();

            $tags = array();
            while (false !== ($row = $stmt->fetch(PDO::FETCH_NUM))) {
                $tags[$row[0]] = $row[1];
            }

            return $tags;
        }
    }

    class Builder
    {
        /**
         * @var array
         */
        protected $config;

        protected $repository;

        public function __construct(array $config, Repository $repository)
        {
            $this->config = $config;
            $this->repository = $repository;
        }

        public function buildCloudFor($contentType)
        {
            if (!isset($this->config['contenttypes'][$contentType])) {
                require_once __DIR__ . '/Exception/UnknownContentTypeException.php';
                throw new UnknownContentTypeException($contentType);
            }

            if (!isset($this->config['contenttypes'][$contentType]['taxonomy'])) {
                require_once __DIR__ . '/Exception/NoTaxonomiesAvailableException.php';
                throw new NoTaxonomiesAvailableException($contentType);
            }

            // Get first available taxonomy that behaves like tags
            // TODO: Research, what if content type has several taxonomies which behave like tags? Is it possible?
            $behavesLikeTags = null;
            foreach ($this->config['contenttypes'][$contentType]['taxonomy'] as $taxonomy) {
                if ('tags' == $this->config['taxonomy'][$taxonomy]['behaves_like']) {
                    $behavesLikeTags = $taxonomy;
                    break;
                }
            }
            if (is_null($behavesLikeTags)) {
                require_once __DIR__ . '/Exception/NoTagsTaxonomiesAvailableException.php';
                throw new NoTagsTaxonomiesAvailableException($contentType);
            }

            // Get tags group
            $tags = $this->repository->getTaxonomyGroupFor($contentType, $behavesLikeTags);

            // Normalize tags group
            if (!empty($tags)) {
                $maxRank = max($tags);
                foreach ($tags as &$rank) {
                    $rank = $this->normalize($rank, $maxRank);
                }
            }

            return $tags;
        }

        protected function normalize($rank, $maxRank)
        {
            return round(1 + ($rank - 1) * 4 / ($maxRank - 1));
        }
    }

    interface Exception
    {
    }
}

namespace TagCloud\Engine\Exception
{
    use TagCloud\Engine\Exception;

    class NoTagsTaxonomiesAvailableException extends \RuntimeException implements Exception
    {
        private $contentType;

        public function __construct($contentType)
        {
            $this->contentType = $contentType;

            parent::__construct(sprintf('No taxonomies that behave like tags available for the content type \'%s\'', $contentType));
        }

        public function getContentType()
        {
            return $this->contentType;
        }
    }

    class NoTaxonomiesAvailableException extends \RuntimeException implements Exception
    {
        private $contentType;

        public function __construct($contentType)
        {
            $this->contentType = $contentType;

            parent::__construct(sprintf('No taxonomies available for the content type \'%s\'', $contentType));
        }

        public function getContentType()
        {
            return $this->contentType;
        }
    }

    class UnknownContentTypeException extends \RuntimeException implements Exception
    {
        private $contentType;

        public function __construct($contentType)
        {
            $this->contentType = $contentType;

            parent::__construct(sprintf('Unknown content type \'%s\'', $contentType));
        }

        public function getContentType()
        {
            return $this->contentType;
        }
    }
}