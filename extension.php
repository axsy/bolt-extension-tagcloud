<?php

namespace TagCloud;

use Bolt\BaseExtension;

use TagCloud\Provider\TagCloudServiceProvider;

require_once __DIR__ . '/Provider/TagCloudServiceProvider.php';

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


