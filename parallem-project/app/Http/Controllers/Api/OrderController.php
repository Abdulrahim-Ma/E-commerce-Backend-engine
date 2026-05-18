<?php
namespace App\Http\Controllers\Api;
use App\Jobs\GenerateInvoicePdf;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{

    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.product')
            ->latest()
            ->get();

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id != $request->user()->id) {
            return response()->json(['message' => 'not found'], 404);
        }

        return new OrderResource($order->load('items.product', 'payment'));
    }

    public function cancel(Request $request, Order $order)
    {
        if ($order->user_id != $request->user()->id) {
            return response()->json(['message' => 'not found'], 404);
        }

        $order->update(['status' => 'cancelled']);

        return new OrderResource($order->load('items.product', 'payment'));
    }

    public function confirm(Request $request, Order $order)
    {
        try {
            $user = $request->user();

            $order = DB::transaction(function () use ($user, $order) {
                return $this->processConfirm($user, $order);
            });
            return new OrderResource($order);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    private function processConfirm(User $user, Order $order)
    {
        if ($order->user_id != $user->id) {
            throw new \RuntimeException('Order not found');
        }
        $order->update(['status' => 'confirmed']);

        $cart = Cart::where('user_id', $user->id)->first();
        if ($cart) {
            $cart->items()->delete();
            $cart->update(['total_price' => 0]);
        }

        return $order->load('items.product', 'payment');
    }


    //  BEFORE OPTIMIZATION

    private function processCreateFromCartOld(User $user)
    {
        $cart = Cart::where('user_id', $user->id)->with('items')->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw new \RuntimeException('Cart is empty');
        }

        $order = Order::create(['user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => $cart->total_price
        ]);

        foreach ($cart->items as $item) {

            $product = Product::find($item->product_id);

            $currentQuantity = $product->quantity;

            sleep(2);

            $product->quantity = $currentQuantity - $item->quantity;
            $product->save();

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ]);
        }

        Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total_price,
            'status' => 'unpaid'
        ]);

        return $order->load('items.product', 'payment');
    }


    //  AFTER OPTIMIZATION

    public function createFromCart(Request $request)
    {
        try {
            $user = $request->user();

            $order = DB::transaction(function () use ($user) {
                return $this->processCreateFromCart($user);
            });

            return response()->json([
                'data' => new OrderResource($order)
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    private function processCreateFromCart(User $user)
    {
        $cart = Cart::where('user_id', $user->id)->with('items')->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw new \RuntimeException('Cart is empty');
        }

        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => $cart->total_price
        ]);

        foreach ($cart->items as $item) {

            $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

            if (!$product || $product->quantity < $item->quantity) {
                throw new \RuntimeException("Product out of stock");
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ]);

            $product->decrement('quantity', $item->quantity);
        }

        Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total_price,
            'status' => 'unpaid'
        ]);

        return $order->load('items.product', 'payment');
    }

    public function checkout()
    {
        $user = auth()->user();

        $cart = $user->cart;

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty. Please add items before checking out.'
            ], 400);
        }

        return DB::transaction(function () use ($user, $cart) {

            foreach ($cart->items as $item) {

                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || $product->quantity < $item->quantity) {
                    throw new \RuntimeException("Product out of stock");
                }

                $product->decrement('quantity', $item->quantity);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_price' => $cart->total_price,ً
            ]);

            $paymentGatewayResponse = $this->simulateExternalPayment($user, $order->total_price);

            if (!$paymentGatewayResponse['success']) {
                throw new \RuntimeException("Payment Failed");
            }

            Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'credit_card',
                'transaction_id' => $paymentGatewayResponse['transaction_id'],
                'amount' => $order->total_price,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $order->update(['status' => 'confirmed']);

            SendOrderConfirmationEmail::dispatch($user, $order);
            GenerateInvoicePdf::dispatch($order);

            $cart->items()->delete();

            return response()->json([
                'message' => 'Success',
                'order_id' => $order->id
            ]);
        });
    }
    private function simulateExternalPayment($user, $amount)
    {
        return [
            'success' => true,
            'transaction_id' => 'TXN-' . uniqid()
        ];
    }
}
