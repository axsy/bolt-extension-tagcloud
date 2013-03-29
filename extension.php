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
                'description' => "An extension provides capapbility of tag cloud generation and helpers to display these clouds",
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
    use TagCloud\Engine\TwigExtension;
    use TagCloud\Engine\View;
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
            $app['tagcloud.view'] = $app->share(function ($app) {
                return new View($app['tagcloud.storage'], $app['paths']['root']);
            });

            $app['dispatcher']->addListener(StorageEvents::postSave, function (StorageEvent $event) use ($app) {
                $app['tagcloud.storage']->deleteCloud($event->getContent()->contenttype['slug']);
            });

            $app['twig']->addExtension(new TwigExtension($app['tagcloud.builder'], $app['tagcloud.view']));
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
    use TagCloud\Engine\Exception\UnsupportedViewException;
    use Doctrine\Common\Cache\CacheProvider;
    use Doctrine\DBAL\Connection;
    use Bolt\Content;
    use PDO;

    interface Exception
    {
    }

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
                if (false !== $cloud) {
                    $this->cache->save($key, $cloud);
                }
            }

            return $cloud;
        }

        protected function getKeyFor($contentType)
        {
            return 'tagcloud_' . $contentType;
        }

        public function deleteCloud($contentType)
        {
            $key = $this->getKeyFor($contentType);
            if ($this->cache->contains($key)) {
                $this->cache->delete($key);
            }
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
            if (false === ($tagsTaxonomy = $this->canBuildCloudFor($contentType))) {
                return false;
            }

            // Get tags group
            $tags = $this->repository->getTaxonomyGroupFor($contentType, $tagsTaxonomy);

            // Normalize tags group
            if (!empty($tags)) {
                $maxRank = max($tags);
                foreach ($tags as &$rank) {
                    $rank = $this->normalize($rank, $maxRank);
                }
            }

            return array(
                'taxonomytype' => $tagsTaxonomy,
                'tags' => $tags
            );
        }

        public function getTagsTaxonomy($contentType)
        {
            if (!isset($this->config['contenttypes'][$contentType])) {
                return false;
            }

            if (!isset($this->config['contenttypes'][$contentType]['taxonomy'])) {
                return false;
            }

            // Get first available taxonomy that behaves like tags
            // TODO: Research, what if content type has several taxonomies which behave like tags? Is it possible?
            $tagsTaxonomy = null;
            foreach ($this->config['contenttypes'][$contentType]['taxonomy'] as $taxonomy) {
                if ('tags' == $this->config['taxonomy'][$taxonomy]['behaves_like']) {
                    $tagsTaxonomy = $taxonomy;
                    break;
                }
            }
            if (is_null($tagsTaxonomy)) {
                return false;
            }

            return $tagsTaxonomy;
        }

        protected function normalize($rank, $maxRank)
        {
            return round(1 + ($rank - 1) * 4 / ($maxRank - 1));
        }
    }

    class View
    {
        protected $storage;

        public function __construct(Storage $storage, $baseUrl)
        {
            $this->storage = $storage;
            $this->baseUrl = $baseUrl;
        }

        public function render($contentType, array $options)
        {
            $html = false;
            $cloud = $this->storage->fetchCloud($contentType);

            if (false != $cloud) {
                $options = array_merge($this->getDefaultOptions(), $options);

                if ('raw' != $options['view']) {
                    $html = '<ul';
                    if (!empty($options['list_options']) && is_array($options['list_options'])) {
                        $html .= $this->renderOptions($options['list_options']);
                    }
                    $html .= '>';
                } else {
                    $html = null;
                }

                foreach ($cloud['tags'] as $tag => $rank) {
                    $link = $this->renderLink(
                        $cloud['taxonomytype'], $tag, $rank, $options['marker'], $options['link_options']);
                    switch ($options['view']) {
                        case 'raw':
                            $html .= "$link ";
                            break;
                        case 'list':
                            $html .= "<li>$link</li>";
                            break;
                        default:
                            throw new UnsupportedViewException($options['view']);
                            break;
                    }
                }
                $html = trim($html) . ('raw' != $options['view'] ? '</ul>' : null);
            }

            return $html;
        }

        public function getDefaultOptions()
        {
            return array(
                'view' => 'list',
                'marker' => 'tag-{rank}',
                'list_options' => null,
                'link_options' => null
            );
        }

        private function renderOptions(array $options)
        {
            $html = null;
            foreach ($options as $key => $value) {
                $html .= " $key=\"" . (is_array($value) ? implode(' ', $value) : trim($value)) . '"';
            }
            return $html;
        }

        private function renderLink($taxonomyType, $tag, $rank, $marker, array $options = null)
        {
            if (!isset($options['class'])) {
                $options['class'] = null;
            }
            if (is_array($options['class'])) {
                $options['class'] = implode(' ', $options['class']);
            }
            $options['class'] .= ' ' . str_replace('{rank}', $rank, $marker);

            $html = '<a href="' . sprintf("%s%s/%s", $this->baseUrl, $taxonomyType, $tag) . '"'
                . $this->renderOptions($options) . '>' . $tag . '</a>';

            return $html;
        }
    }

    class TwigExtension extends \Twig_Extension
    {
        protected $builder;
        protected $view;

        public function __construct(Builder $builder, View $view)
        {
            $this->builder = $builder;
            $this->view = $view;
        }

        public function getFunctions()
        {
            return array(
                new \Twig_SimpleFunction('has_tag_cloud', array($this, 'hasTagCloud')),
                new \Twig_SimpleFunction('tag_cloud', array($this, 'render'), array('is_safe' => array('html'))),
                new \Twig_SimpleFunction('tag_cloud_raw', array($this, 'renderRaw'), array('is_safe' => array('html'))),
                new \Twig_SimpleFunction('tag_cloud_list', array($this, 'renderList'), array('is_safe' => array('html')))
            );
        }

        public function getFilters()
        {
            return array(
                new \Twig_SimpleFilter('contenttype', array($this, 'getContentType'))
            );
        }

        public function hasTagCloud($contentType)
        {
            return false !== $this->builder->getTagsTaxonomy($contentType);
        }

        public function getContentType($content)
        {
            return $content instanceof Content ? $content->contenttype['slug'] : false;
        }

        public function render($contentType, array $options = null)
        {
            return $this->view->render($contentType, $options);
        }

        public function renderRaw($contentType, $linkOptions = null, $marker = null)
        {
            $options = array(
                'view' => 'raw'
            );
            if (!is_null($linkOptions)) {
                $options['link_options'] = $linkOptions;
            }
            if (!is_null($marker)) {
                $options['marker'] = $marker;
            }

            return $this->render($contentType, $options);
        }

        public function renderList($contentType, $listOptions = null, $linkOptions = null, $marker = null)
        {
            $options = array(
                'view' => 'list'
            );
            if (!is_null($listOptions)) {
                $options['list_options'] = $listOptions;
            }
            if (!is_null($linkOptions)) {
                $options['link_options'] = $linkOptions;
            }
            if (!is_null($marker)) {
                $options['marker'] = $marker;
            }

            return $this->render($contentType, $options);
        }

        public function getName()
        {
            return 'tagcloud';
        }
    }
}

namespace TagCloud\Engine\Exception
{
    use TagCloud\Engine\Exception;

    class UnsupportedViewException extends \RuntimeException implements Exception
    {
        private $view;

        public function __construct($view)
        {
            $this->view = $view;

            parent::__construct(sprintf('Unknown view mode \'%s\'', $view));
        }

        public function getView()
        {
            return $this->view;
        }
    }
}