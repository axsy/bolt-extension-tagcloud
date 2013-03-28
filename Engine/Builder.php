<?php

namespace TagCloud\Engine;

require_once __DIR__ . '/Repository.php';

use TagCloud\Engine\Exception\NoTagsTaxonomiesAvailableException;
use TagCloud\Engine\Exception\NoTaxonomiesAvailableException;
use TagCloud\Engine\Exception\UnknownContentTypeException;

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
        foreach($this->config['contenttypes'][$contentType]['taxonomy'] as $taxonomy) {
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
            foreach($tags as &$rank) {
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