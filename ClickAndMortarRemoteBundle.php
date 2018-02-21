<?php

namespace ClickAndMortar\RemoteBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Click And Mortar Remote bundle
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle
 */
class ClickAndMortarRemoteBundle extends Bundle
{
    /**
     * Build.
     *
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}