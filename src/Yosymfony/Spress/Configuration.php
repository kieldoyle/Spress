<?php

/*
 * This file is part of the Yosymfony\Spress.
 *
 * (c) YoSymfony <http://github.com/yosymfony>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Yosymfony\Spress;

use Yosymfony\Silex\ConfigServiceProvider\Config;
use Yosymfony\Silex\ConfigServiceProvider\ConfigRepository;
use Yosymfony\Spress\Definition\ConfigDefinition;

 /**
  * Configuration manager
  * 
  * @author Victor Puertas <vpgugr@gmail.com>
  */
class Configuration
{
    private $configService;
    private $paths;
    private $version;
    private $repository;
    private $globalRepository;
    private $localRepository;
    private $envRepository;
    private $envName;
    
    /**
     * Constructor
     * 
     * @param Yosymfony\Silex\ConfigServiceProvider\ConfigRepository $configService Config service
     * @param array $paths Spress paths and filenames standard
     * @param string $version App version
     */
    public function __construct(Config $configService, array $paths, $version)
    {
        $this->configService = $configService;
        $this->paths = $paths;
        $this->version = $version;
        $this->envName = 'dev';
        $this->loadGlobalRepository();
        $this->repository = $this->globalRepository;
        $this->envRepository = $this->createBlankRepository();
    }
    
    /**
     * Load the local configuration, environtment included
     * 
     * @param string $localPath File configuration of the site
     * @param string $env Environment name
     */
    public function loadLocal($localPath = null, $env = 'dev')
    {
        $this->envName = $env;
        $this->loadLocalRepository($localPath);
        $this->loadEnvironmentRepository($localPath, $env);
        
        $tmpRepository = $this->localRepository->union($this->globalRepository);
        $this->repository = $this->envRepository->union($tmpRepository);
        
        $this->checkDefinitions($this->repository);
    }
    
    /**
     * Get a repository from string
     * 
     * @param string $config Configuration
     * 
     * @return Yosymfony\Silex\ConfigServiceProvider\ConfigRepository
     */
    public function getRepositoryInline($config)
    {
        return $this->configService->load($config, Config::TYPE_YAML);
    }
    
    /**
     * Create a blank config repository
     * 
     * @return Yosymfony\Silex\ConfigServiceProvider\ConfigRepository
     */
    public function createBlankRepository()
    {
        return new ConfigRepository();
    }
    
    /**
     * Get the local repository merged with global repository
     * 
     * @return Yosymfony\Silex\ConfigServiceProvider\ConfigRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }
     
    /**
     * Get the global repository
     * 
     * @return Yosymfony\Silex\ConfigServiceProvider\ConfigRepository
     */
    public function getGlobal()
    {
        return $this->globalRepository;
    }
    
    /**
     * Get the local repository
     * 
     * @return Yosymfony\Silex\ConfigServiceProvider\ConfigRepository
     */
    public function getLocal()
    {
        return $this->localRepository;
    }
    
    /**
     * Get the environment repository
     * 
     * @return Yosymfony\Silex\ConfigServiceProvider\ConfigRepository
     */
    public function getEnvironment()
    {
        return $this->envRepository;
    }
    
    /**
     * Get the config filename. Typically "config.yml"
     * 
     * @return string
     */
    public function getConfigFilename()
    {
        return $this->paths['config.file'];
    }
    
    /**
     * Get the config environment filename.
     * 
     * @return string Null if the environment is dev.
     */
    public function getConfigEnvironmentFilename()
    {
        return $this->getConfigEnvFilename($this->envName);
    }
    
    /**
     * Get the config environment filename with wildcard: "config_*.yml"
     * 
     * @return string
     */
    public function getConfigEnvironmentFilenameWildcard()
    {
        return str_replace(':env', '*', $this->paths['config.file_env']);
    }
    
    /**
     * Get Spress version
     * 
     * @return string
     */
    public function getAppVersion()
    {
        return $this->version;
    }
    
    /**
     * For internal purpose: get the standard paths and filenames of the app.
     * 
     * @return array
     */
    public function getPaths()
    {
        return $this->paths;
    }
    
    private function loadGlobalRepository()
    {
        $this->globalRepository = $this->configService->load($this->getConfigFilename());
        $this->checkDefinitions($this->globalRepository);
    }
    
    /**
     * @param string $localPath
     */
    private function loadLocalRepository($localPath)
    {
        $filename = $this->paths['config.file'];
        $localConfigPath = $this->resolveLocalPath($localPath, $filename);
        $this->localRepository = $this->configService->load($localConfigPath);
        $this->localRepository['source'] = $this->resolvePath($localPath);
    }
    
    /**
     * @param string $localPath
     * @param string $env
     */
    private function loadEnvironmentRepository($localPath, $env)
    {
        $filename = $this->getConfigEnvFilename($env);
        
        if($filename)
        {
            $localPath = $this->getLocalPathFilename($localPath, $filename);
            $resolvedPath = $this->resolvePath($localPath, false);
            
            if($resolvedPath)
            {
                $this->envRepository = $this->configService->load($resolvedPath);
            }
        }
    }
    
    /**
     * @param string $env
     * 
     * @return string
     */
    private function getConfigEnvFilename($env)
    {
        if('dev' === strtolower($env))
        {
            return;
        }
        
        $filenameTemplate = $this->paths['config.file_env'];
        $filename = str_replace(':env', $env, $filenameTemplate);
        
        return $filename;
    }
    
    /**
     * @param string $localPath
     * @param string $filename
     * 
     * @return string
     */
    private function getLocalPathFilename($localPath, $filename)
    {
        if($localPath)
        {
            return $localPath. '/' . $filename;
        }
        else
        {
            return $this->globalRepository['source'] . '/' . $filename;
        }
    }
    
    /**
     * @param string $localPath
     * @param string $filename
     * 
     * @return string
     */
    private function resolveLocalPath($localPath, $filename)
    {
        $path = $this->getLocalPathFilename($localPath, $filename);
        
        return $this->resolvePath($path);
    }
    
    /**
     * @param string $path
     * @param boolean $throwException
     * 
     * @return string
     */
    private function resolvePath($path, $throwException = true)
    {
        $realPath = realpath($path);
        
        if(false === $realPath && true === $throwException)
        {
            throw new \InvalidArgumentException(sprintf('Invalid path "%s"', $path));
        }
        
        return $realPath;
    }
    
    /**
     * @param ConfigRepository $repository
     */
    private function checkDefinitions(ConfigRepository $repository)
    {
        $intersection = $repository->intersection($this->globalRepository);
        $intersection->validateWith(new ConfigDefinition());
    }
}