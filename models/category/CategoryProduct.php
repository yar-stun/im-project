<?php

namespace app\models\category;
use app\models\product\Product;
use yii\caching\TagDependency;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "category_product".
 *
 * @property int $id
 * @property string $name
 * @property string $uname
 * @property int $parent_id
 * @property int $is_visible 1-visible 2-not visible
 * @property CategoryProduct $parent
 * @property CategoryProduct[] $categoryProducts
 */
class CategoryProduct extends ActiveRecord
{

    const VISIBLE_YES = 1;
    const VISIBLE_NO = 0;
    const CACHE_TAG_DEPENDENCY = "cache_product_category";



    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'product_category';
    }



    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required',  'message' => getRuleMessage('required')],
            ['parent_id', 'default', 'value' => null],
            [['id', 'parent_id', 'is_visible'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => CategoryProduct::className(), 'targetAttribute' => ['parent_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'parent_id' => 'Родительская категория',
            'parent' => 'Родительская категория',
            'position' => 'Позиция',
						'is_visible' => 'Видимость',
						'uname' => 'Уникальное имя'
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(CategoryProduct::className(), ['id' => 'parent_id'])
					->alias('parent');
    }

		/**
     * @return \yii\db\ActiveQuery
     */
    public function getCategoryProducts()
    {
        return $this->hasMany(CategoryProduct::className(), ['parent_id' => 'id']);
    }

		public static function getParents(){
				return self::find()->where(['parent_id' => null])->cache(0, new TagDependency(['tags'=>self::CACHE_TAG_DEPENDENCY]))->all();
		}


		public static function getChildsAssoc() {
        $arr  = [];
        $parents = self::getParents();
        foreach($parents as $parent) {
            $children = self::getParentChilds($parent->id);
            foreach($children as $child) {
                $arr[] = $child->name;
            }
        }
        return $arr;
    }
    public static function getParentChilds($parent_id){
        return self::find()->where("parent_id=:parent_id", ["parent_id"=>$parent_id])->cache(0, new TagDependency(['tags'=>self::CACHE_TAG_DEPENDENCY]))->all();
    }
    public static function getCategoriesAsList() {
        $arr ['catList']=[];
        $arr ['options']=[];
        $parents = self::getParents();
        if (empty($parents)) return;
        foreach($parents as $parent)
        {
            $arr['catList'][$parent->id] = $parent->name;
            $arr['options'][$parent->id] = ['class' => 'cat-option-parent'];

            $children = self::getParentChilds($parent->id);
            foreach($children as $child) {
                $arr['catList'][$child->id] = $child->name;
                $arr['options'][$child->id] = ['class' => 'cat-option-child'];
            }
        }
        return $arr;
    }
    public static function getCategoriesAsList2() {
        $arr =[];
        $childs = [];

        $parents = self::getParents();
        if (empty($parents)) return;
        foreach($parents as $parent)
        {
            $children = self::getParentChilds($parent->id);
            foreach($children as $child) {

                $childs[$child->id] = $child->name;

                $subs = self::find()->where("parent_id=:child_id", ["child_id"=>$child->id])->cache(0, new TagDependency(['tags'=>self::CACHE_TAG_DEPENDENCY]))->all();
                if(!empty($subs)){
                    foreach ($subs as $sub){
                        $childs[$sub->id] = $sub->name;
                    }
                }
            }
            $arr[$parent->name] = $childs;
            $childs = [];
        }
        return $arr;
    }

    public function beforeSave($insert)
    {
				if($this->parent_id == null) {
								$this->parent_id = 0; //корневая категория
				}

				if($this->uname == null){
						$i = 0;
						do{
								if($i != 0)
												$name = db_transliterate($this->name).'-'.$i;
								else
												$name = db_transliterate($this->name);
								$i++;
						}while(CategoryProduct::findOne(["uname"=>$name]));

						$this->uname = $name;
				}
				return parent::beforeSave($insert); // TODO: Change the autogenerated stub
    }





    public function getParentName() {
        $parent = $this->parent;
        return  $parent ? $parent->name : 'Корневая категория';

    }


    public function getProductsAmount() {

        return $this->is_visible ? count($this->getAllProducts()) : 0;
    }


    public static function getParentsList()
    {
        $parents = CategoryProduct::find()
            ->select(['c.id', 'c.name'])
            ->join('JOIN', 'product_category c', 'product_category.parent_id = c.id')
            ->distinct(true)
            ->all();
        return ArrayHelper::map($parents, 'id', 'name');
    }



}