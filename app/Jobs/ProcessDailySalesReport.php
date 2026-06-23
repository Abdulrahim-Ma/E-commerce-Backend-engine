<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\InventoryLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDailySalesReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * المتطلب 4: معالجة وجرد المبيعات اليومية الضخمة على دفعات (Chunks)
     */
    public function handle()
    {
        Log::info("Batch Processing: Starting daily sales report generation...");
        
        $processedCount = 0;

        // سحب الطلبات المؤكدة اليوم على دفعات (كل دفعة تحتوي على 100 طلب فقط لحماية الذاكرة)
        Order::where('status', 'confirmed')
            ->whereDate('created_at', today())
            ->with('items.product') // سحب العلاقات مسبقاً لتجنب مشكلة الـ N+1 Query المتعبة للمعالج
            ->chunkById(100, function ($orders) use (&$processedCount) {
                
                // تأطير الدفعة الحالية داخل Transaction لضمان سلامة ACID
                DB::transaction(function () use ($orders, &$processedCount) {
                    foreach ($orders as $order) {
                        
                        // الجرد يمر عبر تفاصيل المنتجات داخل الطلب (Order Items)
                        foreach ($order->items as $item) {
                            
                            InventoryLog::create([
                                'product_id'        => $item->product_id,
                                'previous_quantity' => $item->product->quantity + $item->quantity, // الكمية الافتراضية قبل البيع
                                'new_quantity'      => $item->product->quantity,                   // الكمية الحالية بعد البيع
                                'operation_type'    => 'Batch Processing (Daily Sales Job)',
                                'created_by'        => $order->user_id ?? 1,
                            ]);
                        }
                        $processedCount++;
                    }
                });
                
                Log::info("Batch Processing: Successfully processed a chunk of " . $orders->count() . " orders.");
                unset($orders); // تحرير الذاكرة العشوائية فوراً بعد انتهاء معالجة الدفعة الحالية
            });

        Log::info("Batch Processing: Daily sales report completed. Total orders aggregated: {$processedCount}");
    }
}