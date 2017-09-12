<?php

namespace Bolt\Extension\DesignSpike\ConfigValidator;

use Bolt\Extension\SimpleExtension;
use RomaricDrigon\MetaYaml\Exception\NodeValidatorException;
use RomaricDrigon\MetaYaml\MetaYaml;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\HttpFoundation\Response;
use Bolt\Menu\MenuEntry;
use Silex\ControllerCollection;
use Silex\Application;

class ConfigValidatorExtension extends SimpleExtension
{
    protected function registerMenuEntries()
    {
        $menu = new MenuEntry('config-validator-menu', 'config-validator');
        $menu->setLabel('Validate config files')
            ->setIcon('fa:check')
            ->setPermission('settings')
        ;

        return [
            $menu,
        ];
    }

    protected function registerTwigPaths()
    {
        return [
            'views' => ['position' => 'prepend', 'namespace' => 'ConfigValidator']
        ];
    }

    protected function registerBackendRoutes(ControllerCollection $collection)
    {
        $collection->match("/extensions/config-validator", [$this, 'callbackConfigValidator']);
    }

    public function callbackConfigValidator(Application $app)
    {
        // Config file to validate => schema file to use
        // TODO: scan extensions to see if any of them requests config validation
        // they would be added to the above array with a key for extension name or something
        $tests = [
            ['config_file' => 'taxonomy.yml',     'schema_doc' => 'taxonomy_schema.yml'],
            ['config_file' => 'config.yml',       'schema_doc' => 'config_schema.yml'],
            ['config_file' => 'config_local.yml', 'schema_doc' => 'config_schema.yml'],
            ['config_file' => 'contenttypes.yml', 'schema_doc' => 'contenttypes_schema.yml'],
            ['config_file' => 'menu.yml',         'schema_doc' => 'menu_schema.yml'],
            ['config_file' => 'permissions.yml',  'schema_doc' => 'permissions_schema.yml'],
            ['config_file' => 'routing.yml',      'schema_doc' => 'routing_schema.yml'],
        ];

        $yaml_parser = new Parser();
        foreach ($tests as $test) {
            // Create yaml validator based on a yaml schema document
            $schema_path = __DIR__ . '/../schemas/' . $test['schema_doc'];
            $schema_data = $yaml_parser->parse(file_get_contents($schema_path));
            $validator = new MetaYaml($schema_data);

            // Get the config data directly from .yml files rather than the config in memory, because live config will
            // not keep unrecognized keys, and the user should know about such keys
            $config_path = $app['resources']->getPath('config') . '/' . $test['config_file'];

            // See if the file exists and is writable
            if (!file_exists($config_path)) {

                $app['session']->getFlashBag()->add('warning',
                    $test['config_file'] . " doesn't exist (" . $config_path . ")");

            } else if (file_exists($config_path) and ! is_readable($config_path)) {

                $app['session']->getFlashBag()->add('error',
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
                    $app['session']->getFlashBag()->add('success', $test['config_file'] . ' valid.');
                }
            } catch (NodeValidatorException $e) {
                $app['session']->getFlashBag()->add('error',
                    $test['config_file'] . ' is invalid: ' . $e->getMessage() . ' (Node path: ' . $e->getNodePath() . ')');
            } catch (\Exception $e) {
                $app['session']->getFlashBag()->add('error',
                    $test['config_file'] . ' is invalid: ' . $e->getMessage());
            }
        }
        }

        return new Response($app['twig']->render('@ConfigValidator/base.twig'));
    }
}
