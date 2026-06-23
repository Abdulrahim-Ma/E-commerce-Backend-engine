<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Requests\Api\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache; 

class ProductController extends Controller
{
    
    public function index()
    {
        
        $page = request()->get('page', 1);
        $cacheKey = "products_page_{$page}";

      
        $products = Cache::remember($cacheKey, 600, function () {
            return Product::latest()->paginate(15);
        });

        return ProductResource::collection($products);
    }

    
    public function show(Product $product)
    {
        $cacheKey = "product_single_{$product->id}";

        $cachedProduct = Cache::remember($cacheKey, 600, function () use ($product) {
            return $product;
        });

        return new ProductResource($cachedProduct);
    }

    
    public function store(ProductStoreRequest $request)
    {
        $product = $this->processCreate($request->validated());

       
        $this->clearProductCache();

        return new ProductResource($product);
    }

  
    public function update(ProductUpdateRequest $request, Product $product)
    {
        $product = $this->processUpdate($product, $request->validated());

      
        $this->clearProductCache($product->id);

        return new ProductResource($product);
    }

   
    public function destroy(Product $product)
    {
        $productId = $product->id;
        $product->delete();

      
        $this->clearProductCache($productId);

        return response()->json([
            'message' => 'Product deleted successfully'
        ], 200);
    }

    
    
    private function processCreate(array $data)
    {
        return Product::create($data);
    }

   
    private function processUpdate(Product $product, array $data)
    {
        $product->update($data);

        return $product;
    }

 
    
    private function clearProductCache($productId = null)
    {
      
        if ($productId) {
            Cache::forget("product_single_{$productId}");
        }
        
       
        for ($i = 1; $i <= 5; $i++) {
            Cache::forget("products_page_{$i}");
        }
    }
}