<?php

namespace App\Http\Controllers\Api;
use App\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Requests\Api\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // عرض المنتجات مع الترقيم (Pagination)
    public function index()
    {
        // استخدمنا paginate لضمان أداء سريع عند كثرة البيانات
        $products = Product::latest()->paginate(15);
        return ProductResource::collection($products);
    }

    // عرض منتج واحد
    public function show(Product $product)
    {
        return new ProductResource($product);
    }

    // إضافة منتج جديد
    public function store(ProductStoreRequest $request)
    {
        $product = $this->processCreate($request->validated());

        return new ProductResource($product);
    }

    // تحديث منتج
    public function update(ProductUpdateRequest $request, Product $product)
    {
        $product = $this->processUpdate($product, $request->validated());

        return new ProductResource($product);
    }

    // حذف منتج
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ], 200); // غيرناه لـ 200 ليرجع رسالة تأكيد واضحة
    }

    // ---------------------------------------------------------
    // التوابع المدمجة (التي كانت في الـ Service)
    // ---------------------------------------------------------

    /**
     * منطق إنشاء المنتج
     */
    private function processCreate(array $data)
    {
        return Product::create($data);
    }


    private function processUpdate(Product $product, array $data)
    {
        $product->update($data);

        return $product;
    }
}
