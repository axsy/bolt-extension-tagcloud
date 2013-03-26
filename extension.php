<?php
// TagCloud Extension for Bolt, by Aleksey Orlov

namespace TagCloud;

class Extension extends \Bolt\BaseExtension
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
            'required_bolt_version' => "1.0.2",
            'highest_bolt_version' => "1.0.2",
            'type' => "General",
            'first_releasedate' => "2013-03-27",
            'latest_releasedate' => "2013-03-27",
            'dependencies' => "",
            'priority' => 10
        );

        return $data;

    }

    /**
     * Initialize TagCloud. Called during bootstrap phase.
     */
    function init($app)
    {

        // If yourextension has a 'config.yml', it is automatically loaded.
        // $foo = $this->config['bar'];

        // Initialize the Twig function
        $this->addTwigFunction('tag_cloud', 'twigTag_cloud');

    }

    /**
     * Twig function {{ tag_cloud() }} in TagCloud extension.
     */
    function twigTag_cloud($name="")
    {

        $html = "Twig extension TagCloud.";

        return new \Twig_Markup($html, 'UTF-8');

    }


}


