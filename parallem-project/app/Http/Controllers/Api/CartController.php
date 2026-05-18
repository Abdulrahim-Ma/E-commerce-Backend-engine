<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CartAddItemRequest;
use App\Http\Requests\Api\CartUpdateItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request)
    {
        try {
            $cart = $this->getOrCreateCart($request->user());

            return response()->json([
                'id' => $cart->id,
                'total_price' => $cart->total_price,
                'items' => $cart->load('items.product')->items
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function addItem(CartAddItemRequest $request)
    {
        $product = Product::findOrFail($request->product_id);
        $user = $request->user();

        $cart = $this->getOrCreateCart($user);

        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item) {
            $item->quantity += $request->quantity;
        } else {
            $item = new CartItem();
            $item->cart_id = $cart->id;
            $item->product_id = $product->id;
            $item->quantity = $request->quantity;
            $item->unit_price = $product->price;
        }

        $item->subtotal = $item->quantity * $item->unit_price;
        $item->save();

        $this->updateTotal($cart);

        return new CartResource($cart->load('items.product'));
    }

    public function updateItem(CartUpdateItemRequest $request, CartItem $cartItem)
    {
        $cartItem->quantity = $request->quantity;
        $cartItem->subtotal = $cartItem->unit_price * $request->quantity;
        $cartItem->save();

        $this->updateTotal($cartItem->cart);

        return new CartResource($cartItem->cart->load('items.product'));
    }

    public function removeItem(Request $request, CartItem $cartItem)
    {
        $cart = $cartItem->cart;
        $cartItem->delete();

        $this->updateTotal($cart);

        return new CartResource($cart->load('items.product'));
    }

    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user());
        $cart->items()->delete();
        $cart->total_price = 0;
        $cart->save();

        return new CartResource($cart);
    }


    private function getOrCreateCart(User $user)
    {
        return Cart::firstOrCreate([
            'user_id' => $user->id
        ], [
            'total_price' => 0
        ]);
    }

    private function updateTotal(Cart $cart)
    {
        $cart->total_price = $cart->items()->sum('subtotal');
        $cart->save();
    }
}
