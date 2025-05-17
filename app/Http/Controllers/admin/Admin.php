<?php

namespace App\Http\Controllers\admin;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\Config;
use App\Models\ConfigPromptpay;
use App\Models\Menu;
use App\Models\MenuOption;
use App\Models\Orders;
use App\Models\OrdersDetails;
use App\Models\Pay;
use App\Models\PayGroup;
use BaconQrCode\Encoder\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PromptPayQR\Builder;

class Admin extends Controller
{
    public function dashboard()
    {
        $data['function_key'] = __FUNCTION__;
        $data['orderday'] = Orders::select(DB::raw("SUM(total)as total"))->where('status', 3)->whereDay('created_at', date('d'))->first();
        $data['ordermouth'] = Orders::select(DB::raw("SUM(total)as total"))->where('status', 3)->whereMonth('created_at', date('m'))->first();
        $data['orderyear'] = Orders::select(DB::raw("SUM(total)as total"))->where('status', 3)->whereYear('created_at', date('Y'))->first();
        $data['ordertotal'] = Orders::count();

        $menu = Menu::select('id', 'name')->get();
        $item_menu = array();
        $item_order = array();
        if (count($menu) > 0) {
            foreach ($menu as $rs) {
                $item_menu[] = $rs->name;
                $menu_order = OrdersDetails::Join('orders', 'orders.id', '=', 'orders_details.order_id')->where('orders.status', 3)->where('menu_id', $rs->id)->groupBy('menu_id')->count();
                $item_order[] = $menu_order;
            }
        }

        $item_mouth = array();
        for ($i = 1; $i < 13; $i++) {
            $query = Orders::select(DB::raw("SUM(total)as total"))->where('status', 3)->whereMonth('created_at', date($i))->first();
            $item_mouth[] = $query->total;
        }
        $data['item_menu'] = $item_menu;
        $data['item_order'] = $item_order;
        $data['item_mouth'] = $item_mouth;
        $data['config'] = Config::first();
        return view('dashboard', $data);
    }

    public function ListOrder()
    {
        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];
        $order = DB::table('orders as o')
            ->select(
                'o.table_id',
                DB::raw('SUM(o.total) as total'),
                DB::raw('MAX(o.created_at) as created_at'),
                DB::raw('MAX(o.status) as status'),
                DB::raw('MAX(o.remark) as remark'),
                DB::raw('SUM(CASE WHEN o.status = 1 THEN 1 ELSE 0 END) as has_status_1')
            )
            ->whereNotNull('o.table_id')
            ->whereIn('o.status', [1, 2])
            ->groupBy('o.table_id')
            ->orderByDesc('has_status_1') // ถ้ามี status = 1 จะได้ค่ามากกว่า → ขึ้นก่อน
            ->orderByDesc(DB::raw('MAX(o.created_at)')) // จัดเรียงวันที่ในกลุ่มด้วย
            ->get();

        if (count($order) > 0) {
            $info = [];
            foreach ($order as $rs) {
                $status = '';
                $pay = '';
                if ($rs->has_status_1 == 1) {
                    $status = '<button type="button" class="btn btn-sm btn-primary update-status" data-id="' . $rs->table_id . '">กำลังทำอาหาร</button>';
                }
                if ($rs->has_status_1 == 0) {
                    $status = '<button class="btn btn-sm btn-success">ออเดอร์สำเร็จแล้ว</button>';
                }

                if ($rs->status != 3) {
                    $pay = '<button data-id="' . $rs->table_id . '" data-total="' . $rs->total . '" type="button" class="btn btn-sm btn-outline-success modalPay">ชำระเงิน</button>';
                }
                $flag_order = '<button class="btn btn-sm btn-success">สั่งหน้าร้าน</button>';
                $action = '<button data-id="' . $rs->table_id . '" type="button" class="btn btn-sm btn-outline-primary modalShow m-1">รายละเอียด</button>' . $pay;
                $info[] = [
                    'flag_order' => $flag_order,
                    'table_id' => $rs->table_id,
                    'total' => $rs->total,
                    'remark' => $rs->remark,
                    'status' => $status,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    public function listOrderDetail(Request $request)
    {
        $orders = Orders::where('table_id', $request->input('id'))
            ->whereIn('status', [1, 2])
            ->get();
        $info = '';
        foreach ($orders as $order) {
            $info .= '<div class="mb-3">';
            $info .= '<div class="row"><div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $order->id . '</h5></div>
            <div class="col-auto d-flex align-items-start">';
            if ($order->status != 2) {
                $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-primary updatestatusOrder m-1" data-id="' . $order->id . '">อัพเดทออเดอร์สำเร็จแล้ว</button>';
                $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelOrderSwal m-1" data-id="' . $order->id . '">ยกเลิกออเดอร์</button>';
            }
            $info .= '</div></div>';
            $orderDetails = OrdersDetails::where('order_id', $order->id)->get()->groupBy('menu_id');
            foreach ($orderDetails as $details) {
                $menuName = optional($details->first()->menu)->name ?? 'ไม่พบชื่อเมนู';
                $info .= '<ul class="list-group mb-1 shadow-sm rounded">';
                foreach ($details as $detail) {
                    $option = MenuOption::find($detail->option_id);
                    $optionType = $option ? $menuName . ' ' .  $option->type : 'ไม่มีตัวเลือก';
                    $priceTotal = number_format($detail->quantity * $detail->price, 2);
                    $info .= '<li class="list-group-item d-flex bd-highlight align-items-center">';
                    $info .= '<div class="flex-grow-1 bd-highlight"><small class="text-muted">' . htmlspecialchars($optionType) . '</small> — <span class="fw-medium">จำนวน ' . $detail->quantity . '</span></div>';
                    $info .= '<button class="btn btn-sm btn-primary bd-highlight">' . $priceTotal . ' บาท</button>';
                    $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger bd-highlight m-1 cancelMenuSwal" data-id="' . $detail->id . '">ยกเลิก</button>';
                    $info .= '</li>';
                }
                $info .= '</ul>';
            }
            $info .= '</div>';
        }
        echo $info;
    }

    public function config()
    {
        $data['function_key'] = __FUNCTION__;
        $data['config'] = Config::first();
        $promptpay = ConfigPromptpay::where('config_id', $data['config']->id)->first();
        if ($promptpay) {
            $promptpay = $promptpay->promptpay;
        } else {
            $promptpay = '';
        }
        $data['promptpay'] = $promptpay;
        return view('config', $data);
    }

    public function ConfigSave(Request $request)
    {
        $input = $request->input();
        $config = Config::find($input['id']);
        $config->name = $input['name'];
        $config->color1 = $input['color1'];
        $config->color2 = $input['color2'];
        $config->color_font = $input['color_font'];
        $config->color_category = $input['color_category'];

        if ($request->hasFile('image_bg')) {
            $file = $request->file('image_bg');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('image', $filename, 'public');
            $config->image_bg = $path;
        }
        if ($request->hasFile('image_qr')) {
            $file = $request->file('image_qr');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('image', $filename, 'public');
            $config->image_qr = $path;
        }
        if ($config->save()) {
            $promptpay = ConfigPromptpay::where('config_id', $input['id'])->first();
            if ($promptpay) {
                $promptpay->promptpay = $input['promptpay'];
            } else {
                $promptpay = new ConfigPromptpay();
                $promptpay->config_id = $input['id'];
                $promptpay->promptpay = $input['promptpay'];
            }
            $promptpay->save();
            return redirect()->route('config')->with('success', 'บันทึกรายการเรียบร้อยแล้ว');
        }
        return redirect()->route('config')->with('error', 'ไม่สามารถบันทึกข้อมูลได้');
    }

    public function confirm_pay(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ชำระเงินไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $total = DB::table('orders as o')
                ->select(
                    'o.table_id',
                    DB::raw('SUM(o.total) as total'),
                )
                ->whereNot('table_id')
                ->groupBy('o.table_id')
                ->where('table_id', $id)
                ->whereIn('status', [1, 2])
                ->first();
            $pay = new Pay();
            $pay->payment_number = $this->generateRunningNumber();
            $pay->table_id = $id;
            $pay->total = $total->total;
            if ($pay->save()) {
                $order = Orders::where('table_id', $id)->whereIn('status', [1, 2])->get();
                foreach ($order as $rs) {
                    $rs->status = 3;
                    if ($rs->save()) {
                        $paygroup = new PayGroup();
                        $paygroup->pay_id = $pay->id;
                        $paygroup->order_id = $rs->id;
                        $paygroup->save();
                    }
                }
                $data = [
                    'status' => true,
                    'message' => 'ชำระเงินเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }


    function DateThai($strDate)
    {
        $strYear = date("Y", strtotime($strDate)) + 543;
        $strMonth = date("n", strtotime($strDate));
        $strDay = date("j", strtotime($strDate));
        $time = date("H:i", strtotime($strDate));
        $strMonthCut = array("", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม");
        $strMonthThai = $strMonthCut[$strMonth];
        return "$strDay $strMonthThai $strYear" . " " . $time;
    }

    public function generateQr(Request $request)
    {
        $config = Config::first();
        $config_promptpay = ConfigPromptpay::where('config_id', $config->id)->first();
        $qr = false;
        if ($config_promptpay) {
            if ($config_promptpay->promptpay != '') {
                $total = $request->total;
                $qr = Builder::staticMerchantPresentedQR($config_promptpay->promptpay)->setAmount($total)->toSvgString();
                echo '<div class="row g-3 mb-3">
                <div class="col-md-12">
                    ' . $qr . '
                </div>
            </div>';
            }
        }
        if ($config->image_qr != '') {
            if ($qr == false) {
                echo '
        <div class="row g-3 mb-3">
            <div class="col-md-12">
            <img width="100%" src="' . url('storage/' . $config->image_qr) . '">
            </div>
        </div>';
            }
        }
    }

    public function order()
    {
        $data['function_key'] = 'order';
        $data['config'] = Config::first();
        return view('order', $data);
    }

    public function ListOrderPay()
    {
        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];
        $pay = Pay::whereNot('table_id')->get();

        if (count($pay) > 0) {
            $info = [];
            foreach ($pay as $rs) {
                $action = '<a href="' . route('printReceipt', $rs->id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ออกใบเสร็จฉบับย่อ</a>
                <button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalTax m-1">ออกใบกำกับภาษี</button>
                <button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalShowPay m-1">รายละเอียด</button>';
                $info[] = [
                    'payment_number' => $rs->payment_number,
                    'table_id' => $rs->table_id,
                    'total' => $rs->total,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    public function printReceipt($id)
    {
        $config = Config::first();
        $pay = Pay::find($id);
        $paygroup = PayGroup::where('pay_id', $id)->get();
        $order_id = array();
        foreach ($paygroup as $rs) {
            $order_id[] = $rs->order_id;
        }
        $order = OrdersDetails::whereIn('order_id', $order_id)
            ->with('menu', 'option')
            ->get();
        return view('tax', compact('config', 'pay', 'order'));
    }

    public function printReceiptfull($id)
    {
        $get = $_GET;

        $config = Config::first();
        $pay = Pay::find($id);
        $paygroup = PayGroup::where('pay_id', $id)->get();
        $order_id = array();
        foreach ($paygroup as $rs) {
            $order_id[] = $rs->order_id;
        }
        $order = OrdersDetails::whereIn('order_id', $order_id)
            ->with('menu', 'option')
            ->get();
        return view('taxfull', compact('config', 'pay', 'order', 'get'));
    }

    function generateRunningNumber($prefix = '', $padLength = 7)
    {
        $latest = Pay::orderBy('id', 'desc')->first();

        if ($latest && isset($latest->payment_number)) {
            $number = (int) ltrim($latest->payment_number, '0');
            $next = $number + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad($next, $padLength, '0', STR_PAD_LEFT);
    }

    public function listOrderDetailPay(Request $request)
    {
        $paygroup = PayGroup::where('pay_id', $request->input('id'))->get();
        $info = '';
        foreach ($paygroup as $pg) {
            $orderDetailsGrouped = OrdersDetails::where('order_id', $pg->order_id)
                ->with('menu', 'option')
                ->get()
                ->groupBy('menu_id');
            if ($orderDetailsGrouped->isNotEmpty()) {
                $info .= '<div class="mb-3">';
                $info .= '<div class="row"><div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $pg->order_id . '</h5></div></div>';
                foreach ($orderDetailsGrouped as $details) {
                    $menuName = optional($details->first()->menu)->name ?? 'ไม่พบชื่อเมนู';
                    $info .= '<ul class="list-group mb-1 shadow-sm rounded">';
                    foreach ($details as $detail) {
                        $option = $detail->option;
                        $optionType = $option ? $menuName . ' ' . $option->type : 'ไม่มีตัวเลือก';
                        $priceTotal = number_format($detail->quantity * $detail->price, 2);
                        $info .= '<li class="list-group-item d-flex bd-highlight align-items-center">';
                        $info .= '<div class="flex-grow-1 bd-highlight"><small class="text-muted">' . htmlspecialchars($optionType) . '</small> — <span class="fw-medium">จำนวน ' . $detail->quantity . '</span></div>';
                        $info .= '<button class="btn btn-sm btn-primary bd-highlight">' . $priceTotal . ' บาท</button>';
                        $info .= '</li>';
                    }
                    $info .= '</ul>';
                }
                $info .= '</div>';
            }
        }
        echo $info;
    }
    public function cancelOrder(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ลบข้อมูลไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $menu = Orders::where('id', $id)->first();
            if ($menu->delete()) {
                $order = OrdersDetails::where('order_id', $id)->delete();
                $data = [
                    'status' => true,
                    'message' => 'ลบข้อมูลเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    public function cancelMenu(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ลบข้อมูลไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $menu = OrdersDetails::where('id', $id)->first();
            $count = OrdersDetails::where('order_id', $menu->order_id)->count();
            $total = $menu->price * $menu->quantity;
            if ($menu->delete()) {
                if ($count == 1) {
                    $order = Orders::where('id', $menu->order_id)->delete();
                } else {
                    $order = Orders::where('id', $menu->order_id)->first();
                    $order->total = $order->total - $total;
                    $order->save();
                }
                $data = [
                    'status' => true,
                    'message' => 'ลบข้อมูลเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    public function updatestatus(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'อัพเดทสถานะไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $order = Orders::where('table_id', $id)->get();
            foreach ($order as $rs) {
                $rs->status = 2;
                $rs->save();
            }
            $data = [
                'status' => true,
                'message' => 'อัพเดทสถานะเรียบร้อยแล้ว',
            ];
        }
        return response()->json($data);
    }

    public function updatestatusOrder(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'อัพเดทสถานะไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $order = Orders::find($id);
            $order->status = 2;
            if ($order->save()) {
                $data = [
                    'status' => true,
                    'message' => 'อัพเดทสถานะเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }
}
