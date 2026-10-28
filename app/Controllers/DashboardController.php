<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService();

        if (Auth::isAdmin()) {
            $this->render('dashboard/admin', [
                'title' => 'Dashboard',
                'activeNav' => 'dashboard',
            ] + $service->admin());
            return;
        }

        $this->render('dashboard/borrower', [
            'title' => 'Dashboard',
            'activeNav' => 'dashboard',
        ] + $service->borrower((int) Auth::id()));
    }
}
