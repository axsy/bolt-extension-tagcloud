<?php

namespace TagCloud\Engine;

use Doctrine\Common\Cache\CacheProvider;

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