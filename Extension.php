<?php

namespace Bolt\Extension\DesignSpike\ConfigValidator;

use Bolt;
use RomaricDrigon\MetaYaml\MetaYaml;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\HttpFoundation\Response;

class Extension extends \Bolt\BaseExtension
{

    public  $config;

    private $configDirectory;
    private $schemaDirectory;
    private $path;

    const NAME = "ConfigValidator";

    public function initialize()
    {
        $this->configDirectory = $this->app['resources']->getPath('config');
        $this->schemaDirectory = __DIR__ . '/schemas';

        $this->path = $this->app['config']->get('general/branding/path') . '/extensions/config-validator';
        $this->app->match($this->path, [$this, 'ConfigValidator']);

        // Add template namespace to twig
        $this->app['twig.loader.filesystem']->addPath(__DIR__.'/views', 'ConfigValidator');

        // Add menu item
        $this->addMenuOption('Validate config files', $this->app['resources']->getUrl('bolt') . 'extensions/config-validator', 'fa:check');

    }

    public function ConfigValidator()
    {
        // Config file to validate => schema file to use
        // TODO: scan extensions to see if any of them requests config validation
        $tests = [
            ['config_file' => 'taxonomy.yml',     'schema_doc' => 'taxonomy_schema.yml'],
            ['config_file' => 'config.yml',       'schema_doc' => 'config_schema.yml'],
            ['config_file' => 'config_local.yml', 'schema_doc' => 'config_schema.yml'],
            ['config_file' => 'contenttypes.yml', 'schema_doc' => 'contenttypes_schema.yml'],
            ['config_file' => 'menu.yml',         'schema_doc' => 'menu_schema.yml'],
            ['config_file' => 'permissions.yml',  'schema_doc' => 'permissions_schema.yml'],
            ['config_file' => 'routing.yml',      'schema_doc' => 'routing_schema.yml'],
        ];
        
        // they would be added to the above array with a key for extension name or something
        $extensions = $this->app['extensions']->getEnabled();

        $yaml_parser = new Parser();
        $messages = [];
        foreach ($tests as $test) {
            // Create yaml validator based on a yaml schema document
            $schema_path = $this->schemaDirectory . '/' . $test['schema_doc'];
            $schema_data = $yaml_parser->parse(file_get_contents($schema_path));
            $validator = new MetaYaml($schema_data);

            // Get the config data directly from .yml files rather than the config in memory, because live config will
            // not keep unrecognized keys, and the user should know about such keys
            $config_path = $this->configDirectory . '/' . $test['config_file'];

            // See if the file exists and is writable
            if (!file_exists($config_path)) {

                $this->app['session']->getFlashBag()->add('warning',
                    $test['config_file'] . " doesn't exist (" . $config_path . ")");

            } else if (file_exists($config_path) and ! is_readable($config_path)) {

                $this->app['session']->getFlashBag()->add('error',
                    $test['config_file'] . " exists but isn't readable (" . $config_path . ")");

            } else {
                try {
                    // Parse the config file if it was found
                    $config_data = $yaml_parser->parse(file_get_contents($config_path));

                    if (! is_array($config_data)) {
                        throw new \UnexpectedValueException($test['config_file'] . " was read, but couldn't be parsed.");
                    }
                    // Try and validate the config
                    $result = $validator->validate($config_data);
                    if ($result === true) {
                        $this->app['session']->getFlashBag()->add('success', $test['config_file'] . ' valid.');
                    }
                } catch (\Exception $e) {
                    $this->app['session']->getFlashBag()->add('error',
                        $test['config_file'] . ' is invalid: ' . $e->getMessage());
                }
            }
        }

        return new Response($this->app['twig']->render('@ConfigValidator/base.twig', [
            'messages' => $messages
        ]));
    }

    public function getName()
    {
        return Extension::NAME;
    }

}
