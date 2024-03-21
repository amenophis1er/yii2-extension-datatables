<?php

namespace amenophis1er\yii2datatables;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;

class DatatablesBootstrap implements BootstrapInterface
{
    /**
     * @throws InvalidConfigException
     * @param \yii\web\Application $app
     */
    public function bootstrap($app)
    {
        /* @var $app \yii\web\Application */
        $app->set('datatables', DataTablesComponent::class);
        /* @property DataTablesComponent $datatables */
        
        $app->controllerMap['datatables'] = DatatablesController::class;
        
        $app->getUrlManager()->addRules([
            'datatables/ssp-<key:\w+>' => 'datatables/endpoint',
        ], false);
    }
}
