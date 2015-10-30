<?php

namespace Bolt\Extension\DesignSpike\ConfigValidator;

if (isset($app)) {
    $app['extensions']->register(new Extension($app));
}
