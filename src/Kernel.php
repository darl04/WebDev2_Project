<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    
    public function boot(): void
    {
        parent::boot();
        
        // Set timezone to Philippine time (Asia/Manila)
        // You can override this via APP_TIMEZONE environment variable
        $timezone = $_ENV['APP_TIMEZONE'] ?? $_SERVER['APP_TIMEZONE'] ?? 'Asia/Manila';
        
        // Validate timezone
        try {
            new \DateTimeZone($timezone);
            date_default_timezone_set($timezone);
        } catch (\Exception $e) {
            // Fallback to Asia/Manila if timezone is invalid
            date_default_timezone_set('Asia/Manila');
        }
    }
}
