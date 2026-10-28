<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;

final class AuditController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'action' => (string) $this->input('action', ''),
            'user_id' => $this->intInput('user_id'),
            'from' => (string) $this->input('from', ''),
            'to' => (string) $this->input('to', ''),
        ];

        $paginator = AuditLog::paginate($filters, max(1, $this->intInput('page', 1)), 15);

        $this->render('audit/index', [
            'title' => 'Audit Trail',
            'activeNav' => 'audit',
            'filters' => $filters,
            'paginator' => $paginator,
            'actions' => AuditLog::actions(),
            'users' => User::paginate([], 1, 100)->items,
        ]);
    }
}
