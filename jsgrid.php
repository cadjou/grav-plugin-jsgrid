<?php
namespace Grav\Plugin;

use Grav\Common\Data\ValidationException;
use Grav\Common\Debugger;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Types;
use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use Grav\Common\Uri;
use Grav\Common\Yaml;
use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Framework\Route\Route;
use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod\CurlPost;
use RocketTheme\Toolbox\File\JsonFile;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\Event\Event;
use Grav\Plugin\Jsgrid\Jsgrid;
use Grav\Plugin\Jsgrid\Jsgrids;

class JsgridPlugin extends Plugin
{
	
    /** @var Form */
    protected $jsgrid;

    /** @var array */
    protected $jsgrids = [];
	
	/**
     * @return bool
     */
    public static function checkRequirements(): bool
    {
        return version_compare(GRAV_VERSION, '1.6', '>');
    }
	
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
		if (!static::checkRequirements()) {
            return [];
        }
		
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0]
            ],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ];
    }

	/**
     * Initialize forms from cache if possible
     */
    public function onPluginsInitialized()
    {
        $this->grav['jsgrids'] = function () {
            $jsgrids = new Jsgrids();

            $grav = Grav::instance();
            $event = new Event(['jsgrids' => $jsgrids]);
            $grav->fireEvent('onJsgridRegisterTypes', $event);

            return $jsgrids;
        };

        if ($this->isAdmin()) {
            $this->enable([
                'onGetPageTemplates' => ['onGetPageTemplates', 0],
            ]);
            return;
        }

        // Mini Keep-Alive Logic
        $task = $this->grav['uri']->param('task');
        if ($task && $task === 'keep-alive') {
            exit;
        }

        $this->enable([
            'onPageProcessed' => ['onPageProcessed', 0],
            'onPageInitialized' => ['onPageInitialized', 0],
        ]);
    }
	
	public function onGetPageTemplates(Event $event)
    {
        /** @var Types $types */
        $types = $event->types;
        $types->register('jsgrid');
    }
	
	public function pageIsJsgrid($page)
	{
		$pageName = $page->value('name');
		if ($page->modularTwig())
		{
			$pageName = substr($pageName,6);
		}
		if ($pageName <> 'jsgrid')
		{
			return false;
		}
		$header = (array) $page->header();
		if (empty($header['jsgrid']))
		{
			return false;
		}
		return $header['jsgrid'];
	}
	
	/**
     * Process forms after page header processing, but before caching
     *
     * @param Event $e
     */
	public function onPageProcessed(Event $e)
    {
        /** @var PageInterface $page */
        $page = $e['page'];
		$dataJsgrid = $this->pageIsJsgrid($page);
		if (!$dataJsgrid)
		{
			return;
		}
        // print_r($dataJsgrid);
        
        $twig	= $this->grav['twig'];
        $twig->twig_vars['jsgrid'] = $dataJsgrid;
        
        
		return;
		 // Force never_cache_twig if modular form (recursively up)
        $current = $page;
        while ($current && $current->modularTwig()) {
            $header = $current->header();
            $header->never_cache_twig = true;

            $current = $current->parent();
        }
        $parent = $current && $current !== $page ? $current : null;

        $page_route = $page->home() ? '/' : $page->route();

        // If the form was in the modular page, we need to add the form into the parent page as well.
        if ($parent) {
            $parent->addJsgrid($pageJsgrids);
            $parent_route = $parent->home() ? '/' : $parent->route();
        }

        /** @var Forms $forms */
        $jsgrids = $this->grav['jsgrids'];

        // Store the page forms in the forms instance
        foreach ($pageJsgrids as $name => $jsgrid) {
            if (isset($parent, $parent_route)) {
                $this->addJsgrid($parent_route, $jsgrids->createPageForm($parent, $name, $jsgrid));
            }
            $this->addJsgrid($page_route, $jsgrids->createPageForm($parent, $name, $jsgrid));
        }
	}
    /**
     * Initialize configuration
     */
    public function onPageInitialized()
    {
        $load = !empty($this->config->get('plugins.jsgrid.always_load'));

		if (!$load)
		{
			$page = $this->grav['page'];
			$data = Yaml::parse($page->frontmatter());
			$load = (is_array($data) and isset($data['jsgrid'])) ? $data['jsgrid'] : false;
		}
		
		if ($load)
		{
			$this->enable([
				'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
			]);
		}
    }
	
    /**
     * Add a form to the forms plugin
     *
     * @param string|null $page_route
     * @param FormInterface|null $form
     */
    public function addJsgrid(?string $page_route, ?FormInterface $form)
    {
        if (null === $form) {
            return;
        }

        $name = $form->getName();

        if (!isset($this->jsgrids[$page_route][$name])) {
            $this->jsgrids[$page_route][$name] = $form;

            $this->recache_jsgrids = true;
        }
    }
	
    /**
     * if enabled on this page, load the JS + CSS and set the selectors.
     */
    public function onTwigSiteVariables()
    {
        $config = $this->config->get('plugins.jsgrid');

        $mode = (isset($config['mode']) and $config['mode'] == 'production') ? '.min' : '';

		$currentVersion = isset($config['version']) ? $config['version'] : '1.5.3';
		
		$jsgridCDN = 'https://cdnjs.cloudflare.com/ajax/libs';
        $jsgrid_bits = [];

        if (!empty($config['use_cdn']))
		{
			$jsgrid_bits[] = "{$jsgridCDN}/jsgrid/{$currentVersion}/jsgrid{$mode}.js";
			$jsgrid_bits[] = "{$jsgridCDN}/jsgrid/{$currentVersion}/jsgrid-theme{$mode}.css";
			$jsgrid_bits[] = "{$jsgridCDN}/jsgrid/{$currentVersion}/jsgrid{$mode}.css";
        }
		else
		{
			$jsgrid_bits[] = "plugin://jsgrid/js/{$currentVersion}/jsgrid{$mode}.js";
			$jsgrid_bits[] = "plugin://jsgrid/css/{$currentVersion}/jsgrid-theme{$mode}.css";
			$jsgrid_bits[] = "plugin://jsgrid/css/{$currentVersion}/jsgrid{$mode}.css";
        }
		
		if ($jsgrid_bits)
		{
			$assets = $this->grav['assets'];
			$assets->registerCollection('jsgrid', $jsgrid_bits);
			$assets->add('jsgrid', 100);
		}

        
    }
	/**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }
}
