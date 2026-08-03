<?php

namespace DtgsLllmstxtGenerator\Subscriber\Frontend;

use Enlight\Event\SubscriberInterface;

class Routing implements SubscriberInterface
{
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_RouteStartup' => 'onRouteStartup',
        ];
    }

    /**
     * Maps the request to /llms.txt to our frontend controller.
     *
     * @param \Enlight_Controller_EventArgs $args
     */
    public function onRouteStartup(\Enlight_Controller_EventArgs $args)
    {
        $request = $args->getRequest();
        $pathInfo = $request->getPathInfo();

        if (rtrim($pathInfo, '/') === '/llms.txt') {
            $request->setModuleName('frontend');
            $request->setControllerName('Llmstxt');
            $request->setActionName('index');
        }
    }
}
