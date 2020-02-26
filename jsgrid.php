<?php
namespace Grav\Plugin;
use Grav\Common\Session;
use Grav\Common\Data\ValidationException;
use Grav\Common\Debugger;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Types;
use Grav\Common\Page\Page;
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
use Grav\Plugin\Form\Form;
use Grav\Plugin\Form\Forms;

class JsgridPlugin extends Plugin
{
	
    /** @var Form */
    protected $jsgrid;

    /** @var array */
    protected $jsgrids = [];
    
    /** @var array */
    protected $jsgridsSession = [];
	
	public static function checkRequirements(): bool
    {
        return version_compare(GRAV_VERSION, '1.6', '>');
    }
	
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
    
	public function onPluginsInitialized()
    {
        require __DIR__ . '/classes/Jsgrid.php';
        $this->jsgridsSession = Grav::instance()['session']->getFlashObject('jsgrid');
        if ($this->isAdmin()) {
            $this->enable([
                'onGetPageTemplates' => ['onGetPageTemplates', 0],
            ]);
            return;
        }
        
        if ($this->isJsgridRoute())
		{
			$this->enable([
				'onPagesInitialized' => ['onPagesInitialized', 0],
				'onFormProcessed'    => ['onFormProcessed', 0],
			]);
		}
		else
		{
			$this->enable([
				'onPageProcessed'    => ['onPageProcessed', 0],
				'onPageInitialized' => ['onPageInitialized', 0],
			]);
		}

        // Mini Keep-Alive Logic
        $task = $this->grav['uri']->param('task');
        if ($task && $task === 'keep-alive') {
            exit;
        }
    }
	
	public function onGetPageTemplates(Event $event)
    {
        /** @var Types $types */
        $types = $event->types;
        $types->register('jsgrid');
    }
	    
    public function onPagesInitialized()
    {
        if (!$this->jsgridsSession or !\is_array($this->jsgridsSession))
        {
            return;
        }
        foreach($this->jsgridsSession as $id=>$jsgrid)
        {
            $jsgrid->backFromSession();
        }
    }
    
    public function onFormProcessed(Event $event)
    {
        $form   = $event['form'];
        $action = $event['action'];
        $params = $event['params'];
        
        if ($event['action'] <> 'jsgrid')
		{
			return;
		}
        $jsgridName = 'jsgrid_' . $form->get('name');
        $jsgrid     = isset($this->jsgridsSession[$jsgridName]) ? $this->jsgridsSession[$jsgridName] : [];
        
        $data = $jsgrid->onJsgridProcessed($form->getValue('data'));
        
        $this->jsgridsSession[$jsgridName] = $jsgrid;
        Grav::instance()['session']->setFlashObject('jsgrid',$this->jsgridsSession);
		
        $returnData['data']               = $data ? $data :  null;
        $returnData['__unique_form_id__'] = $jsgrid->getUniqueId();
        if ($this->isRequestJson())
		{
			header('Content-Type: application/json');
			echo json_encode($returnData);
			exit;	
		}
    }
    	
	public function onPageProcessed(Event $e)
    {
		$dataJsgrid = $this->pageIsJsgrid($e['page']);
		if (!$dataJsgrid)
		{
			return;
		}
        
        $jsgrid = new Jsgrid($dataJsgrid);
        
        if (!$jsgrid->getName())
        {
            return;
        }
        
        $this->jsgrids[$jsgrid->getName()] = $jsgrid;
        
        Grav::instance()['session']->setFlashObject('jsgrid',$this->jsgrids);
        
        $twig	= $this->grav['twig'];
        $twig->twig_vars['jsgrid'] = $jsgrid->getForTwig();
	}
    
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
	
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }
    
    public function addRegisterPage($pageJsgridHeader, $route)
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $page = $pages->dispatch($route);

        if (!$page) {
            $page = new Page();
            $page->init(new \SplFileInfo(__DIR__ . '/pages/jsgridform.md'));
            $page->slug(basename($route));
            $page->header($pageJsgridHeader);
            $page->frontmatter(Yaml::dump((array)$page->header()));
            $pages->addPage($page, $route);
        }
        return $page;
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

    public function isJsgridRoute()
    {
        $this->route = $this->grav['uri']->route();
		$this->base  = '/_jsgrid/_form/';
		return $this->base === substr($this->route,0,strlen($this->base));
    }
    
    public function isRequestJson()
    {
		$grav = Grav::instance();
        $request  = $grav['request'];
		foreach(explode(',',$request->getServerParams()['HTTP_ACCEPT']) as $accept)
		{
			$accept = trim($accept);
			if ($accept == 'application/json')
			{
				return true;
			}
		}
		return false;
	}
    
}
