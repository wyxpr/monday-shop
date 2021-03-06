<?php

namespace App\Admin\Controllers;

use App\Admin\Transforms\OrderDetailTransform;
use App\Admin\Transforms\OrderTransform;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Displayers\Actions;
use Encore\Admin\Grid\Filter;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('订单列表')
            ->description('description')
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header('Detail')
            ->description('description')
            ->body($this->detail($id));
    }




    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Order);

        $grid->model()->withTrashed()->latest();

        $grid->column('id');
        $grid->column('no', '流水号');
        $grid->column('user.name', '用户');
        $grid->column('total', '总价');
        $grid->column('status', '状态')->display(function ($status) {

            return OrderTransform::getInstance()->transStatus($status);
        });
        $grid->column('type', '订单类型')->display(function ($type) {

            return OrderTransform::getInstance()->transType($type);
        });
        $grid->column('pay_no', '支付流水号');
        $grid->column('pay_time', '支付时间');
        $grid->column('consignee_name', '收货人姓名');
        $grid->column('consignee_phone', '收货人手机');
        $grid->column('consignee_address', '收货地址');
        $grid->column('pay_refund_fee', '退款金额');
        $grid->column('pay_trade_no', '退款流水号');
        $grid->column('deleted_at', '是否删除')->display(function ($is) {

            return OrderTransform::getInstance()->transDeleted($is);
        });
        $grid->column('created_at', '创建时间');
        $grid->column('updated_at', '修改时间');

        $grid->disableRowSelector();
        $grid->disableCreateButton();
        $grid->actions(function (Actions $actions) {
            $actions->disableEdit();
        });

        $grid->filter(function (Filter $filter) {

            $filter->disableIdFilter();
            $filter->like('no', '流水号');
            $filter->where(function ($query) {

                $users = User::query()->where('name', 'like', "%{$this->input}%")->pluck('id');
                $query->whereIn('user_id', $users->all());
            }, '用户');
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Order::query()->withTrashed()->findOrFail($id));

        $show->field('id');
        $show->field('no', '流水号');
        $show->field('user', '用户')->as(function ($user) {
            return optional($user)->name;
        });
        $show->field('total', '总计');
        $show->field('status', '状态')->as(function ($status) {

            return OrderTransform::getInstance()->transStatus($status);
        });
        $show->field('type', '订单类型')->as(function ($type) {

            return OrderTransform::getInstance()->transType($type);
        });
        $show->field('address', '收货地址');
        $show->field('pay_no', '支付单号');
        $show->field('pay_time', '支付时间');
        $show->field('created_at', '创建时间');
        $show->field('updated_at', '修改时间');

        // 详情
        $show->details('详情', function (Grid $details) {

            $details->column('id');
            $details->column('product.name', '商品名字');
            $details->column('price', '单价');
            $details->column('number', '数量');
            $details->column('is_commented', '是否评论')->display(function ($is) {

                return OrderDetailTransform::getInstance()->transCommented($is);
            });
            $details->column('total', '小计');

            $details->disableRowSelector();
            $details->disableCreateButton();
            $details->disableFilter();
            $details->disableActions();
        });

        return $show;
    }

    /**
     * 后台删除订单就是真的删除了
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {

            DB::transaction(function () use ($id) {
                /**
                 * @var $order Order
                 */
                $order = Order::withTrashed()->findOrFail($id);
                $order->details()->delete();
                $order->forceDelete();
            });

            $data = [
                'status'  => true,
                'message' => trans('admin.delete_succeeded'),
            ];
        } catch (\Throwable $e) {
            $data = [
                'status'  => false,
                'message' => trans('admin.delete_failed') . $e->getMessage(),
            ];
        }

        return response()->json($data);
    }
}
