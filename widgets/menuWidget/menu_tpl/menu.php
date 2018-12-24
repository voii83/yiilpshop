<li>
    <a href="<?= \yii\helpers\Url::to(['shop/category/view', 'alias' => $category['alias']]) ?>">
        <?= $category['name'] ?>
        <?php if(isset($category['childs'])) : ?>
            <span class="badge pull-right"><i class="fa fa-plus"></i></span>
        <?php endif; ?>
    </a>
    <?php if(isset($category['childs'])) : ?>
        <ul>
            <?= $this->getMenuHtml($category['childs']); ?>
        </ul>
    <?php endif; ?>
</li>