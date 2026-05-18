<?php
namespace App\Providers;
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

    public function handle()
    {
        Order::where('status', 'confirmed')
            ->whereDate('created_at', today())
            ->chunkById(100, function ($orders) {
                DB::transaction(function () use ($orders) {
                    foreach ($orders as $order) {
                        InventoryLog::create([
        'product_id'        => $order->product_id,
         'previous_quantity' => isset($order->quantity_before_order) ? $order->quantity_before_order : 100,
          'new_quantity'      => isset($order->quantity_after_order) ? $order->quantity_after_order : 90,
           'operation_type'    => 'Batch Processing (Daily Sales Job)',
         'created_by'        => isset($order->user_id) ? $order->user_id : 1,
                        ]);
                    }
                });
            });

    }
}
