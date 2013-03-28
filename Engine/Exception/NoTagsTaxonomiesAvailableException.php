<?php

namespace TagCloud\Engine\Exception;

use TagCloud\Engine\Exception;

require_once __DIR__ . '/../Exception.php';

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