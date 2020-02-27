<?php
namespace Grav\Plugin\Jsgrid;

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Yaml;
use RocketTheme\Toolbox\File\JsonFile;

class Jsgrid
{
    protected $jsgridName   = '';
    
    protected $pathData     = '';
    
    protected $fields       = [];
    
    protected $options      = [];
    
    protected $formId       = '';
    
    protected $formName     = '';
    
    protected $formRoute    = '';
    
    protected $nonce        = '';
    
    protected $nonceName    = '';
    
    protected $uniqueId     = '';
    
    protected $jsgridInit   = false;
    
    protected $jsgridValide = true;
    
    protected $page;
    
    protected $fileConf = [];
    
    protected $fileData = [];
    
    protected $fullFileName = '';
    
    public function __construct($dataJsgrid = [])
    {
        if($dataJsgrid)
        {
            $this->createJsgrid($dataJsgrid);
        }
    }
    
    public function createJsgrid(Array $dataJsgrid)
    {
        $this->jsgridInit = true;
        $jsgridName   = isset($dataJsgrid['name'])      ? $dataJsgrid['name']     : false;
        $pathData     = isset($dataJsgrid['pathData'])  ? $dataJsgrid['pathData'] : DS . 'jsgrid' . DS . $jsgridName;
        $fields       = isset($dataJsgrid['fields'])    ? $dataJsgrid['fields']   : false;
        $options      = isset($dataJsgrid['options'])   ? $dataJsgrid['options']  : [];
        
        if (!$jsgridName or !$pathData or !$fields or !\is_array($fields) or !\is_array($options))
		{
			return;
		}
        
        $this->jsgridName   = $jsgridName;
        $this->pathData     = $pathData;
        $this->fields       = $fields;
        $this->options      = $options;
        
        if (!$this->createPage())
        {
            return;
        }
        
        if (!$this->createForm())
        {
            return;
        }
        
    }
    
    protected function createPage()
    {
        
        $uniqid = str_replace('.','',uniqid());
        
        $formName    = $this->jsgridName . '_' . $uniqid;
        $formRoute   = '/_jsgrid/_form/' . $uniqid;
        $formProcess = ['jsgrid'=>['form_json_response'=>'p5:select']];
        
        $formFields = [];
        foreach($this->fields as $fieldName=>$fieldParam)
        {
            $fieldFormParam           = $fieldParam;
            $fieldFormParam['type']   = 'hidden';
            if (isset($fieldFormParam['validate']['required']))
            {
                unset($fieldFormParam['validate']['required']);
            }
            $formFields[$fieldName]   = $fieldFormParam;
        }
        
        $formFields['_id']['type']             = 'hidden';
        $formFields['_requestId']['default']   = $uniqid;
        $formFields['_requestId']['type']      = 'hidden';
        $formFields['_requestType']['type']    = 'hidden';
        
        $form['name']     = $formName;
        $form['action']   = $formRoute;
        $form['fields']   = $formFields;
        $form['process']  = $formProcess;
        
        $page = new Page();
        $page->init(new \SplFileInfo(__DIR__ . '/../pages/jsgridform.md'));
        $page->slug(basename($formRoute));
        $page->header(['form' => $form]);
        $page->frontmatter(Yaml::dump((array)$page->header()));
        
        // print_r($form);
        // print_r($page);
        $this->jsgridInit &= $this->addPage($page,$formRoute);
        if (!$this->jsgridInit)
        {
            return $this->jsgridInit;
        }
        
        $this->jsgridName  = 'jsgrid_' . $formName;
        $this->formName  = $formName;
        $this->formRoute = $formRoute;
        $this->page      = $page;
        
        return $this->jsgridInit;
    }
    
    protected function addPage($newPage,$route)
    {
        $pages = Grav::instance()['pages'];
        $page = $pages->dispatch($route);

        if (!$page)
        {
            $pages->addPage($newPage, $route);
        }
        return !empty($pages->dispatch($route));
    }
    
    protected function createForm()
    {
        $form = Grav::instance()['forms']->createPageForm($this->page,$this->formName);
        
        $this->uniqueId    = $form->getUniqueId();
        $this->nonce       = $form->getNonce();
        $this->nonceName   = $form->getNonceName();
        $this->formId      = $form->get('id');
        $this->formName    = $form->get('name');
        $this->jsgridInit &= (bool) $this->formId;
        return $this->jsgridInit;
    }
    
    public function onJsgridProcessed($data)
    {
        if (!$this->jsgridInit or !$this->pathData)
        {
            return false;
        }
        
        if (!isset($data['_requestType'],$data['_requestId']))
        {
            return false;
        }
        
        $returnData = [];
        // print_r($data);
        $requestId   = !empty($data['_requestId'])   ? $data['_requestId']   : null;
        $requestType = !empty($data['_requestType']) ? $data['_requestType'] : null;
        
        list($basicName,$uniqidForm) = explode('_',$this->formName);
        if (!$requestId or !$requestType or $requestId <> $uniqidForm)
        {
            return false;
        }
        
        unset($data['_requestId']);
        unset($data['_requestType']);
        
        $this->fileManagment();
        $this->createForm();

        if     ($requestType == 'get'   )
        {
            return $this->onJsgridGet($data);
        }
        elseif ($requestType == 'add'   )
        {
            return $this->onJsgridAdd($data);
        }
        elseif ($requestType == 'update')
        {
            return $this->onJsgridUpdate($data);
        }
        elseif ($requestType == 'delete')
        {
            return $this->onJsgridDelete($data);
        }
		
        return false;
    }
    
    public function getForTwig()
    {   
        $twig['jsgridName'] = $this->jsgridName;
        $twig['fields']     = $this->fields;
        $twig['options']    = $this->options;
        $twig['formId']     = $this->formId;
        $twig['formName']   = $this->formName;
        $twig['formRoute']  = $this->formRoute;
        $twig['uniqueId']   = $this->uniqueId;
        $twig['nonce']      = $this->nonce;
        $twig['nonceName']  = $this->nonceName;
        
        return $twig;
    }
    
    public function getName()
    {
        if ($this->jsgridInit)
        {
            return $this->jsgridName;
        }
        return false;
    }
    
    public function getFormName()
    {
        if ($this->formName)
        {
            return $this->formName;
        }
        return false;
    }
    
    public function getUniqueId()
    {
        if ($this->uniqueId)
        {
            return $this->uniqueId;
        }
        return false;
    }
    
    public function backFromSession()
    {
        if ($this->jsgridInit)
        {
            return $this->addPage($this->page,$this->formRoute);
        }
        return false;
    }
    
    protected function onJsgridGet($data)
    {
        $tableReturn = null;
        if ($this->fileData)
        {
            $tableReturn = [];
            foreach($this->fileData as $raw)
            {
                $tableRaw = [];
                foreach($raw as $key=>$value)
                {
                    $tableRaw[$key] = ($value == false or $value == 'false' ) ? null : $value;
                }
                $tableReturn[] = $tableRaw;
            }
        }
        return $tableReturn;
    }
    
    protected function onJsgridAdd($data)
    {
        // print_r($this->fileData);
        if($this->fileData)
        {
            // print_r(max((array) array_column($this->fileData,'_id')) + 1);
        }
        else
        {
            // print_r(1);
        }
        $data['_id'] = $this->fileData ? max(array_column($this->fileData,'_id')) + 1 : 1;
        // print_r($data);
        $this->fileData[] = $data;
        return $this->sauveFile($this->fileData);
    }
    
    protected function onJsgridUpdate($data)
    {
        if (!empty($data['_id']))
        {
            $tableIdData = array_flip(array_column($this->fileData,'_id'));
            $this->fileData[$tableIdData[$data['_id']]] = $data;
            return $this->sauveFile($this->fileData);
        }
        return false;
    }
    
    protected function onJsgridDelete($data)
    {
        if (!empty($data['_id']))
        {
            $tableIdData = array_flip(array_column($this->fileData,'_id'));
            $key = $tableIdData[$data['_id']];
            unset($this->fileData[$key]);
            return $this->sauveFile($this->fileData);
        }
        return false;
    }

    protected function fileManagment()
    {
        $pathGravData = Grav::instance()['locator']->findResource('user-data://', true);
        $fullFileName = $pathGravData . DS . ltrim($this->pathData,'/') . '.json';
        if (!realpath($fullFileName))
        {
            $this->fullFileName = $fullFileName;
            $this->createFile($fullFileName);
        }
        if (!realpath($fullFileName))
        {
            return false;
        }
        $this->fullFileName = $fullFileName;
        $dataFile = JsonFile::instance($fullFileName)->content();
        // print_r($dataFile);
        $resetData = false;
        $this->fileConf = $this->fields;
        if (!isset($dataFile['conf']) or !is_object($dataFile['conf']))
        {
            $this->fileConf = $this->fields;
            $resetData = true;
        }
        elseif ($dataFile['conf'] === $this->fields)
        {
            $this->fileConf = $this->fields;
        }
        elseif ($dataFile['conf'] !== $this->fields)
        {
            // $resetData = true;
            // Todo : Is case the Jsgrid config change only in developement state
            // Todo : managment of change name or type, for a next version
        }
        // print_r($dataFile['data']);
        // print_r($resetData);
        $this->fileData = !$resetData ? (array) $dataFile['data'] : $this->fileData;
        // print_r($this->fileData);
    }
    
    protected function createFile($arrayContent = [])
    {
        return file_put_contents($this->fullFileName, json_encode($arrayContent), LOCK_EX);
    }
    
    protected function sauveFile()
    {
        $save = file_put_contents($this->fullFileName, json_encode(['conf'=>$this->fileConf,'data'=>$this->fileData]));
        return $save;
    }
}
