<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Order;
use App\Mail\OrderConfirmedMail; // سنقوم بإنشائه بعد قليل
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $order;

    // استقبال البيانات من تابع checkout
    public function __construct(User $user, Order $order)
    {
        $this->user = $user;
        $this->order = $order;
    }

    public function handle()
    {
        // إرسال الإيميل الفعلي للمستخدم
        Mail::to($this->user->email)->send(new OrderConfirmedMail($this->order));
    }
}
