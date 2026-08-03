<?php

use DtgsLllmstxtGenerator\Components\LlmsTxtGenerator;

class Shopware_Controllers_Frontend_Llmstxt extends Enlight_Controller_Action
{
    /**
     * Disable view rendering, we output raw text.
     */
    public function preDispatch()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
    }

    /**
     * Outputs the generated llms.txt as plain text.
     */
    public function indexAction()
    {
        $config = $this->container->get('shopware.plugin.cached_config_reader')
            ->getByPluginName('DtgsLllmstxtGenerator', $this->container->get('shop'));

        if (empty($config['enable_plugin'])) {
            $this->Response()->setHttpResponseCode(404);
            return;
        }

        /** @var LlmsTxtGenerator $generator */
        $generator = $this->container->get('dtgs_lllmstxt_generator.components.llms_txt_generator');
        $content = $generator->generate();

        $this->Response()->setHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->Response()->setBody($content);
    }
}
