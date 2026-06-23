<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServerMonitorAspect
{
   
    public function handle(Request $request, Closure $next): Response
    {
       
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

       
        $response = $next($request);

       
        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = round(($endTime - $startTime) * 1000, 2); 
        $memoryUsed = round(($endMemory - $startMemory) / 1024, 2); 
        
        $method = $request->method();
        $url = $request->fullUrl();
        $status = $response->getStatusCode();
        $pid = getmypid();

        
        $logMessage = sprintf(
            "\n============ [AOP SERVER MONITOR (PID: %d)] ============\n" .
            "🕒 Time: %s\n" .
            "🔄 Action: %s %s\n" .
            "🚦 Status: %d\n" .
            "⚡ Response Time: %s ms\n" .
            "💾 Memory Overhead: %s KB\n" .
            "========================================================\n",
            $pid,
            now()->toDateTimeString(),
            $method,
            $url,
            $status,
            $executionTime,
            $memoryUsed
        );

      
        file_put_contents('php://stdout', $logMessage);

        return $response;
    }
}