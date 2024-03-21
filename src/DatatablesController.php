<?php

namespace amenophis1er\yii2datatables;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class DatatablesController extends Controller
{
    public function actionEndpoint()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $key = Yii::$app->request->get('key');
        $sessionKey = '__datatables-'.$key;
        
        if (empty($key)) {
            return ['error' => 'Invalid session key.'];
        }
        
        $datatable = Yii::$app->session->get($sessionKey);
        if (!$datatable) {
            return ['error' => 'An error occurred or invalid session key.'];
        }
        
        $method  = strtolower(Yii::$app->request->getMethod());
        $payload = $method === 'get' ? Yii::$app->request->get() : Yii::$app->request->post();
        
        return Yii::$app->datatables->process($datatable, $payload);
    }
}
