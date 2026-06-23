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
use Illuminate\Support\Facades\Cache;
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
        if ($order->user_id != $request->user()->id) {
            return response()->json(['message' => 'not found'], 404);
        }

        $order->update(['status' => 'confirmed']);

        return new OrderResource($order->load('items.product', 'payment'));
    }

   
    public function createWithRaceCondition(Request $request)
    {
        try {
            $user = $request->user();
            
            $result = DB::transaction(function () use ($user) {
                $cart = Cart::where('user_id', $user->id)->with('items')->first();

                if (!$cart || $cart->items->isEmpty()) {
                    throw new \RuntimeException('Cart is empty');
                }

                foreach ($cart->items as $item) {
                    $product = Product::find($item->product_id); 

                    if (!$product || $product->quantity < $item->quantity) {
                        throw new \RuntimeException("Product {$product->name} out of stock!");
                    }

                    
                    sleep(3); 

                   
                    $product->quantity -= $item->quantity;
                    $product->save();
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'total_price' => $cart->total_price,
                ]);

                $cart->items()->delete();
                return $order;
            });

            return response()->json(['message' => 'Order created (Race condition allowed)', 'order_id' => $result->id]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    
    public function createWithOptimisticLock(Request $request)
    {
        try {
            $user = $request->user();

            $order = DB::transaction(function () use ($user) {
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
                    
                    $product = Product::find($item->product_id);

                    if (!$product || $product->quantity < $item->quantity) {
                        throw new \RuntimeException("Product {$product->name} out of stock!");
                    }

                    
                    $updated = Product::where('id', $product->id)
                        ->where('version', $product->version) 
                        ->update([
                            'quantity' => $product->quantity - $item->quantity,
                            'version'  => $product->version + 1  
                        ]);

                    
                    if (!$updated) {
                        throw new \RuntimeException("Concurrency Conflict: Product [{$product->name}] was modified by another thread. Transaction Rollbacked.");
                    }

                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $item->product_id,
                        'quantity'   => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal'   => $item->subtotal,
                    ]);
                }

                $cart->items()->delete();
                return $order;
            });

            return response()->json(['message' => 'Success with Optimistic Locking', 'order_id' => $order->id], 201);

        } catch (\Exception $e) {
          
            return response()->json(['message' => $e->getMessage()], 409); 
        }
    }

   
    public function createWithPessimisticLock(Request $request)
    {
        try {
            $user = $request->user();

            $order = DB::transaction(function () use ($user) {
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
                        throw new \RuntimeException("Product {$product->name} out of stock!");
                    }

                    $product->decrement('quantity', $item->quantity);

                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $item->product_id,
                        'quantity'   => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal'   => $item->subtotal,
                    ]);
                }

                $cart->items()->delete();
                return $order;
            });

            return response()->json(['message' => 'Success with Pessimistic Locking', 'order_id' => $order->id], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

 
    public function checkout(Request $request)
    {
        $user = $request->user();
        $lock = Cache::lock('checkout_user_' . $user->id, 10);

        if (!$lock->get()) {
            return response()->json(['message' => 'Please wait, your previous request is still processing.'], 423);
        }

        try {
            $result = DB::transaction(function () use ($user) {
                $cart = Cart::where('user_id', $user->id)->with('items')->first();

                if (!$cart || $cart->items->isEmpty()) {
                    throw new \RuntimeException("Cart is empty");
                }

          
                foreach ($cart->items as $cartItem) {
                    $product = Product::where('id', $cartItem->product_id)->lockForUpdate()->first();
                    if (!$product || $product->quantity < $cartItem->quantity) {
                        throw new \RuntimeException("Product {$product->name} is out of stock.");
                    }
                    $product->decrement('quantity', $cartItem->quantity);
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'total_price' => $cart->total_price,
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

            return $result;

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } finally {
            $lock->release();
        }
    }

    private function simulateExternalPayment($user, $amount)
    {
        return [
            'success' => true,
            'transaction_id' => 'TXN-' . uniqid()
        ];
    }
}