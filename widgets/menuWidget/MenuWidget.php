<?php

namespace app\widgets\menuWidget;

use app\components\debug\Debug;
use Yii;
use app\models\ProductCategory;
use yii\base\Widget;

class MenuWidget extends Widget
{
    public $tpl;
    public $data;
    public $tree;
    public $menuHtml;

    public function init()
    {
        parent::init();

        switch ($this->tpl) {
            case 'menu' :
                $this->tpl .= '.php';
                break;
            case  'select' :
                $this->tpl .= '.php';
                break;
            default :
                $this->tpl = 'menu.php';
                break;
        }
    }

    public function run()
    {
        // get cache
        $menuAccordion = Yii::$app->cache->get('menuAccordion');
        if ($menuAccordion) return $menuAccordion;

        $this->data = ProductCategory::find()->indexBy('uid')->asArray()->all();
        $this->tree = $this->getTree();
        $this->menuHtml = $this->getMenuHtml($this->tree);

        //set cache
        Yii::$app->cache->set('menuAccordion', $this->menuHtml, 60);

        return $this->menuHtml;
    }

    protected function getTree()
    {
        $tree = [];
        foreach ($this->data as $uid=>&$node) {
            if (!$node['uid_parent']) {
                $tree[$uid] = &$node;
            }
            else {
                $this->data[$node['uid_parent']]['childs'][$node['uid']] = &$node;
            }
        }
        return $tree;
    }

    protected function getMenuHtml($tree)
    {
        $str = '';
        foreach ($tree as $category) {
            $str .= $this->catToTemplate($category);
        }
        return $str;
    }

    protected function catToTemplate($category)
    {
        ob_start();
        include __DIR__ . '/menu_tpl/' . $this->tpl;
        return ob_get_clean();
    }
}