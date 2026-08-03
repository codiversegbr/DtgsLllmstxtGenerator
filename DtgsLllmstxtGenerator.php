<?php

namespace DtgsLllmstxtGenerator;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DtgsLllmstxtGenerator extends Plugin
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('dtgs_lllmstxt_generator.plugin_dir', $this->getPath());
        parent::build($container);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $installMessage = 'Vielen Dank, dass Sie unser Plugin nutzen! Ihre llms.txt ist ab sofort unter /llms.txt in Ihrem Shop erreichbar.';
        $context->scheduleMessage($installMessage);
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        if ($context->keepUserData()) {
            return;
        }

        parent::uninstall($context);
        $context->scheduleClearCache(UninstallContext::CACHE_LIST_ALL);
    }
}
