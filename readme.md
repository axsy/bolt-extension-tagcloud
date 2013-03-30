TagCloud
========

An extension provides capability of tag cloud generation and helpers to display these clouds. You can read more about
awesome Bolt CMS built on top of Silex microframework at [Bolt.cm](http://bolt.cm).

The tag clouds rendering are extremely fast becouse they're being calculated only once for the specified content type
and will be invalidated on the cache clear or some changes in the list of records of single content type. The configuration
is also being cached using Symfony's Config component.

Usage
-----

Is as simple as add the following lines to the code:

    {{ tag_cloud(record|contenttype) }}

or

    {{ tag_cloud(records|first|contenttype) }}

The general notation is

    {{ tag_cloud(contenttype{, {<options>}}) }}

These produces the following markup:

    <ul>
        <li>
            <a href="/tags/annotations" class="tag-1">annotations</a>
        </li>
        <li>
            <a href="/tags/aop" class="tag-3">aop</a>
        </li>
        <li>
            <a href="/tags/demo" class="tag-1">demo</a>
        </li>
        <li>
            <a href="/tags/doctrine" class="tag-1">doctrine</a>
        </li>
        <li>
            <a href="/tags/firephp" class="tag-5">firephp</a>
        </li>
        <li>
            <a href="/tags/nginx" class="tag-5">nginx</a>
        </li>
        <li>
            <a href="/tags/tests" class="tag-1">tests</a>
        </li>
    </ul>

Default cloud markup can be highly customized via the following rendering options:

    *   view
        Can be equal to "list"(by default) or "raw". In case of "raw" value
        the tag cloud will be rendered just like hyperlinks divided by spaces.
    *   marker
        Defines template for the marker class of the tag cloud hyperlinks ("tag-{rank}" by default). Can be overriden
        with vour custom template where is "{rank}" substring. Rank are values from 1 up pto 5.
    *   list_options
        An array with classes and/or attributes to be set on the '<ul>' tag (makes sence only with view options set to
        "list".
    *   link_options
        An array with classes and/or attributes to be set on the '<a>' tags of the tag cloud


Two shortcuts are available:

    {{ tag_cloud_raw(contenttype[, {<link_options>}[, <marker>]]) }}

and

    {{ tag_cloud_list(contenttype[, {<link_options>}[, <marker>[, {<list_options>}]]]) }}

Configuration
-------------

To make an extension works you have to copy the predefined config.yml.desc to config.yml file and customize it:

    cp config.yml.desc config.yml

Currently the configuration file consists only of one tag cloud parameter, the cloud size. This param defines how much
tags will be shown in the tag cloud.

Examples
--------

Render raw tag-cloud with customized marker class and some custom classes added:

    {{ tag_cloud(contenttype, {view: "raw", marker: "rank-{rank}", link_options:{class: "tag pretty"}}) }}

    <a href="/tags/annotations" class="tag pretty rank-1">annotations</a>
    <a href="/tags/aop" class="tag pretty rank-3">aop</a>
    <a href="/tags/demo" class="tag pretty rank-1">demo</a>
    <a href="/tags/doctrine" class="tag pretty rank-1">doctrine</a>
    <a href="/tags/firephp" class="tag pretty rank-5">firephp</a>
    <a href="/tags/nginx" class="tag pretty rank-5">nginx</a>
    <a href="/tags/tests" class="tag pretty rank-1">tests</a>

Render the same hyperlinks but as the customized list:

    {{ tag_cloud(contenttype, {view: "list", marker: "rank-{rank}",
       link_options: {class: "tag pretty"}, list_options: {id: "cloud"}}) }}

    <ul id="cloud">
        <li>
            <a href="/tags/annotations" class="tag pretty rank-1">annotations</a>
        </li>
        <li>
            <a href="/tags/aop" class="tag pretty rank-3">aop</a>
        </li>
        <li>
            <a href="/tags/demo" class="tag pretty rank-1">demo</a>
        </li>
        <li>
            <a href="/tags/doctrine" class="tag pretty rank-1">doctrine</a>
        </li>
        <li>
            <a href="/tags/firephp" class="tag pretty rank-5">firephp</a>
        </li>
        <li>
            <a href="/tags/nginx" class="tag pretty rank-5">nginx</a>
        </li>
        <li>
            <a href="/tags/tests" class="tag pretty rank-1">tests</a>
        </li>
    </ul>

Render simple lightly cusatomized raw tag cloud:

    {{ tag_cloud_raw(contenttype, {class: "tag"}) }}

    <a href="/tags/annotations" class="tag tag-1">annotations</a>
    <a href="/tags/aop" class="tag tag-3">aop</a>
    <a href="/tags/demo" class="tag tag-1">demo</a>
    <a href="/tags/doctrine" class="tag tag-1">doctrine</a>
    <a href="/tags/firephp" class="tag tag-5">firephp</a>
    <a href="/tags/nginx" class="tag tag-5">nginx</a>
    <a href="/tags/tests" class="tag tag-1">tests</a>

Realworld usage scenario
------------------------

Generally speaking Bolt provides record pages or records list pages. Probably, you've decided to use template inheritance
for your own theme and choosed classic page markup with single aside for the all types of pages. Assume this aside
should have the tag cloud related to the record(s) that is/are being shown. Also assume that you have two different content
types and only one of them have taxonomy that behaves like tags. This way we can't calculate cloud tags and therefore can't
show it for the content types which don't support them. For the simplicity lets also assume that we have only two template
pages, record.twig and listing.twig

Since tag cloud are being shown based on the content type we have to determine current content type and pass it to the
aside template. This can be done as follows:

record.twig:

    {% extends 'layout.twig' %}

    {% block aside %}
        {% include '_aside.twig' with {contenttype: record|contenttype} %}
    {% endblock %}

    {% block content %}
        {% include '_record.twig' %}
    {% endblock content %}

listing.twig:

    {% extends 'layout.twig' %}

    {% block aside %}
        {% include '_aside.twig' with {contenttype: records|first|contenttype} %}
    {% endblock %}

    {% block content %}
        {% include '_records.twig' %}
        {{ pager() }}
    {% endblock content %}

Then we just out tag cloud in the aside but previously check does this content type support tag clouds? This can be done
via has_tag_cloud() helper:

aside.twig:

    {% if has_tag_cloud(contenttype) %}
        <section>
            <h1>Tags
            {# We can use there whatever rendering function we like, with any set of options #}
            {{ tag_cloud_list(contenttype, {class: "tag"}) }}
        </section>
    {% endif %}

Extensibility
-------------

Well, the goal of it's extension was not to provide a huge list of options, helpers etc. to customize the tag clouds are
being produced, but allow to make an engine simply extended with your own specific requirements and customization.
Would you like to completely change the rendering way, noirmalization alorithm, etc.? Thanks to Silex this can be done
in simple and usual way, by overriding the defined services or by use only some of them independently.

The following services are defined:

    *   tagcloud.config
        Not a service but just an extension configuration in the form of the associative array
    *   tagcloud.repository
        Talks to the database to get a data necessary to build a tag cloud
    *   tagcloud.builder
        Builds tag clouds
    *   tagcloud.storage
        Controls tag clouds cache
    *   tagcloud.view
        Renders previously calculated tag cloud

Please look to the extension code to find out how they're work. In case you want to replace (or override) some of them
in the chain you can replace the service with an extended or reimplemented class, please look to the following interfaces:

    *   StorageInterface
    *   RepositoryInterface
    *   BuilderInterface
    *   ViewInterface

Todo List
---------

    *   Make sure taxonomy table has all necessary indexes. Probably via some console schema updater but I think
        I need to talk with Bolt core developers first...
    *   Add support for the tag boost in order to make some tags always shown in the tag cloud
    *   Probably add tags sorting
    *   Add service to make it simple generate 'Tags' sections on the site (just like 'Archive', 'Categories' etc.)