<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;
use \Grav\Common\Grav;
use \Grav\Common\Page\Page;

class JsgridPlugin extends Plugin
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onThemeInitialized' => ['onThemeInitialized', 0]
        ];
    }

    /**
     * Initialize configuration
     */
    public function onThemeInitialized()
    {
        if ($this->isAdmin()) {
            return;
        }

        $load_events = false;

        // if not always_load see if the theme expects to load bootstrap plugin
        if (!$this->config->get('plugins.Jsgrid.always_load')) {
            $theme = $this->grav['theme'];
            if (isset($theme->load_bootstrapper_plugin) && $theme->load_bootstrapper_plugin) {
                $load_events = true;
            }
        } else {
            $load_events = true;
        }

        if ($load_events) {
            $this->enable([
                'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
            ]);
        }
    }

    /**
     * if enabled on this page, load the JS + CSS and set the selectors.
     */
    public function onTwigSiteVariables()
    {
        $config = $this->config->get('plugins.Jsgrid');
        $version = $config['version'];
        $mode = $config['mode'] == 'production' ? '.min' : '';

        $jsgrid_bits = [];
	
		$currentVersion = isset($config['version']) ? $config['version'] ? '1.5.3';
		$jsgridCDN = 'https://cdnjs.cloudflare.com/ajax/libs/';

        if ($config['use_cdn'])
		{
			$jsgrid_bits[] = "{$jsgridCDN}/jsgrid/{$currentVersion}/jsgrid{$mode}.js";
			$jsgrid_bits[] = "{$jsgridCDN}/jsgrid/{$currentVersion}/jsgrid-theme{$mode}.css";
			$jsgrid_bits[] = "{$jsgridCDN}/jsgrid/{$currentVersion}/jsgrid{$mode}.css";
        }
		else
		{
			$jsgrid_bits[] = "plugin://jsgrid/js/{$version}/jsgrid{$mode}.js";
			$jsgrid_bits[] = "plugin://jsgrid/css/{$version}/jsgrid-theme{$mode}.css";
			$jsgrid_bits[] = "plugin://jsgrid/css/{$version}/jsgrid{$mode}.css";
        }

        $assets = $this->grav['assets'];
        $assets->registerCollection('jsgrid', $jsgrid_bits);
        $assets->add('jsgrid', 100);
    }
}
