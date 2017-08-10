<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午4:27
 */

namespace test;


use app\Models\Coupon;
use app\Models\Goods;
use app\Models\GoodsPhase;
use app\Models\Shop;
use Server\Test\TestCase;

class ModelTest extends TestCase
{
    /**
     * @var Coupon
     */
    protected $Coupon;

    /**
     * @var Shop
     */
    protected $Shop;
    /**
     * @var Goods
     */
    protected $Goods;

    /**
     * @var GoodsPhase
     */
    protected $GoodsPhase;

    protected $shop_id;
    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function setUpBeforeClass()
    {
        $this->Coupon = $this->loader->model('Coupon',$this);
        $this->Goods = $this->loader->model('Goods',$this);
        $this->Shop = $this->loader->model('Shop',$this);
        $this->GoodsPhase = $this->loader->model('GoodsPhase',$this);
    }

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function tearDownAfterClass()
    {
        yield $this->Coupon->removeCoupon('test');
        yield $this->Shop->delShop($this->shop_id);
        yield $this->Goods->delShopAllGoods($this->shop_id);
        yield $this->GoodsPhase->delAllShopId($this->shop_id);
    }

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public function setUp()
    {
        // TODO: Implement setUp() method.
    }

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public function tearDown()
    {
        // TODO: Implement tearDown() method.
    }

    public function test_createCouponBox()
    {
        yield $this->Coupon->createCouponBox('test',100);
        $count = yield $this->Coupon->countCoupon('test');
        $this->assertEquals(100,$count);
    }

    public function test_getCoupon()
    {
        $result = yield $this->Coupon->getCoupon('test',20);
        $this->assertCount(20,$result);
        $count = yield $this->Coupon->countCoupon('test');
        $this->assertEquals(80,$count);
    }

    public function test_createShop()
    {
        $result = yield $this->Shop->createShop([
            'wid'=>'test',
            'shop_icon'=>'test',
            'shop_desc'=>'test',
            'shop_name'=>'myshop'
        ]);
        $this->assertTrue($result['result']);
        $this->assertEquals(1,$result['affected_rows']);
        $this->shop_id = $result['insert_id'];
        return $this->shop_id;
    }

    /**
     * @depends test_createShop
     * @param $shop_id
     * @return \Generator
     */
    public function test_getShopInfo($shop_id)
    {
        $result = yield $this->Shop->getShopInfo($shop_id);
        $this->assertEquals($shop_id,$result['shop_id']);
    }

    /**
     * 创建Goods
     * @depends test_createShop
     * @param $shop_id
     * @return mixed
     */
    public function test_createGoods($shop_id)
    {
        $result = yield $this->Goods->createGoods([
            'shop_id'=> $shop_id,
            'goods_name'=>"iphone",
            'goods_des'=>"a",
            'goods_money'=>6999,
            'goods_icons'=>"test",
            'is_audit_through'=>1
        ]);
        $this->assertTrue($result['result']);
        $this->assertEquals(1,$result['affected_rows']);
        $goods_id = $result['insert_id'];
        return $goods_id;
    }

    /**
     * @depends test_createGoods
     * @param $goods_id
     * @return mixed
     */
    public function test_getGoodsInfo($goods_id)
    {
        $result = yield $this->Goods->getGoodsInfo($goods_id);
        $this->assertEquals($goods_id,$result['goods_id']);
        return $result;
    }

    /**
     * @depends test_createGoods
     * @depends test_getGoodsInfo
     * @param $goods_id
     * @param $goodInfo
     * @return mixed
     */
    public function test_createNextPhase($goods_id,$goodInfo)
    {
        $goodsPhaseInfo = yield $this->GoodsPhase->createNextPhase($goods_id);
        $result = yield $this->Coupon->countCoupon($goodsPhaseInfo['coupon_id']);
        $this->assertEquals($goodInfo['goods_money'],$result);
        return $goodsPhaseInfo;
    }

    /**
     * @depends test_createNextPhase
     * @param $goodsPhaseInfo
     */
    public function test_getGoodsPhaseInfo($goodsPhaseInfo)
    {
        $result = yield $this->GoodsPhase->getGoodsPhaseInfo($goodsPhaseInfo['goods_id'],$goodsPhaseInfo['phase']);
        $this->assertEquals($goodsPhaseInfo['goods_id'],$result['goods_id']);
    }

}