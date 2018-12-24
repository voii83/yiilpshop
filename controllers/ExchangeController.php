<?php

namespace app\controllers;

use Yii;
use yii\rest\Controller;
use app\components\exchange\Exchange1c;

class ExchangeController extends Controller
{
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $type = $request->get('type');
        $mode = $request->get('mode');
        $filename = $request->get('filename');

        if (empty($type)) {
            return Yii::$app->response->redirect(['/']);
        }

        Exchange1c::getResponse($type, $mode, $filename);
    }
}