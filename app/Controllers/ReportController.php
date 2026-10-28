<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Models\AuditLog;
use App\Models\Category;
use App\Services\ReportService;

final class ReportController extends Controller
{
    private const TYPES = ['inventory', 'borrowing', 'returns', 'pending', 'overdue', 'condition', 'borrowers'];

    public function index(): void
    {
        $service = new ReportService();
        $type = (string) $this->input('type', 'inventory');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'inventory';
        }

        $filters = [
            'q' => (string) $this->input('q', ''),
            'status' => (string) $this->input('status', ''),
            'condition' => (string) $this->input('condition', ''),
            'category_id' => $this->intInput('category_id'),
            'user_id' => $this->intInput('user_id'),
            'from' => (string) $this->input('from', ''),
            'to' => (string) $this->input('to', ''),
        ];

        $data = [
            'title' => 'Reports',
            'activeNav' => 'reports',
            'type' => $type,
            'filters' => $filters,
            'summary' => $service->summary(),
            'categories' => Category::all(),
            'users' => $service->usersForFilter(),
            'inventory' => null,
            'borrowing' => null,
            'returns' => null,
            'pending' => null,
            'overdue' => null,
            'conditionRegister' => null,
            'conditionHistory' => null,
            'borrowers' => null,
        ];

        if ($type === 'inventory') {
            $data['inventory'] = $service->inventory($filters);
        } elseif ($type === 'borrowing') {
            $data['borrowing'] = $service->borrowing($filters);
        } elseif ($type === 'returns') {
            $data['returns'] = $service->returns($filters);
        } elseif ($type === 'pending') {
            $data['pending'] = $service->pending();
        } elseif ($type === 'overdue') {
            $data['overdue'] = $service->overdue();
        } elseif ($type === 'condition') {
            $data['conditionRegister'] = $service->conditionRegister();
            $data['conditionHistory'] = $service->conditionHistory(100);
        } elseif ($type === 'borrowers') {
            $data['borrowers'] = $service->borrowerSummary();
        }

        AuditLog::record('report.viewed', 'report', null, 'Viewed ' . $type . ' report.');
        $this->render('reports/index', $data);
    }

    public function export(): void
    {
        $service = new ReportService();
        $type = (string) $this->input('type', 'inventory');
        $filters = [
            'q' => (string) $this->input('q', ''),
            'status' => (string) $this->input('status', ''),
            'condition' => (string) $this->input('condition', ''),
            'category_id' => $this->intInput('category_id'),
            'user_id' => $this->intInput('user_id'),
            'from' => (string) $this->input('from', ''),
            'to' => (string) $this->input('to', ''),
        ];

        $headers = [];
        $rows = [];

        if ($type === 'borrowing') {
            $headers = ['Request Code', 'Status', 'Borrower', 'School ID', 'Equipment', 'Category', 'Qty Requested', 'Qty Approved', 'Transaction', 'Borrowed At', 'Due At', 'Returned Qty'];
            foreach ($service->borrowing($filters) as $r) {
                $rows[] = [
                    $r['request_code'], $r['status'], $r['first_name'] . ' ' . $r['last_name'], $r['school_id'],
                    $r['equipment_name'], $r['category_name'], $r['quantity_requested'], $r['quantity_approved'],
                    $r['transaction_code'], $r['borrowed_at'], $r['due_at'], $r['quantity_returned'],
                ];
            }
        } elseif ($type === 'returns') {
            $headers = ['Return Code', 'Return Date', 'Equipment', 'Qty', 'Condition', 'Borrower', 'School ID', 'Transaction', 'Notes', 'Processed By'];
            foreach ($service->returns($filters) as $r) {
                $rows[] = [
                    $r['return_code'], $r['return_date'], $r['equipment_name'], $r['quantity_returned'],
                    $r['condition'], $r['first_name'] . ' ' . $r['last_name'], $r['school_id'],
                    $r['transaction_code'], $r['notes'], trim(($r['processor_first_name'] ?? '') . ' ' . ($r['processor_last_name'] ?? '')),
                ];
            }
        } elseif ($type === 'condition') {
            $headers = ['Equipment Code', 'Equipment', 'Category', 'Total', 'Available', 'Borrowed', 'Damaged', 'Under Repair', 'Lost', 'Condition'];
            foreach ($service->conditionRegister() as $r) {
                $rows[] = [
                    $r['equipment_code'], $r['name'], $r['category_name'], $r['total_quantity'], $r['available_quantity'],
                    $r['borrowed_quantity'], $r['damaged_quantity'], $r['under_repair_quantity'], $r['lost_quantity'], $r['condition_status'],
                ];
            }
        } elseif ($type === 'pending') {
            $headers = ['Request Code', 'Borrower', 'School ID', 'Role', 'Items', 'Expected Return', 'Submitted', 'Purpose'];
            foreach ($service->pending() as $r) {
                $rows[] = [
                    $r['request_code'], $r['first_name'] . ' ' . $r['last_name'], $r['school_id'], $r['role'],
                    $r['items_summary'], $r['expected_return_date'], $r['created_at'], $r['purpose'],
                ];
            }
        } elseif ($type === 'overdue') {
            $headers = ['Transaction Code', 'Borrower', 'School ID', 'Equipment', 'Equipment Code', 'Qty', 'Outstanding', 'Due At', 'Days Overdue', 'Request Code'];
            foreach ($service->overdue() as $r) {
                $rows[] = [
                    $r['transaction_code'], $r['first_name'] . ' ' . $r['last_name'], $r['school_id'], $r['equipment_name'],
                    $r['equipment_code'], $r['quantity'], (int) $r['quantity'] - (int) $r['quantity_returned'],
                    $r['due_at'], $r['days_overdue'], $r['request_code'],
                ];
            }
        } elseif ($type === 'borrowers') {
            $headers = ['Borrower', 'School ID', 'Role', 'Requests', 'Outstanding Units', 'Overdue Checkouts', 'Completed Returns'];
            foreach ($service->borrowerSummary() as $r) {
                $rows[] = [
                    $r['first_name'] . ' ' . $r['last_name'], $r['school_id'], $r['role'], $r['requests'],
                    $r['outstanding_units'], $r['overdue_count'], $r['completed_returns'],
                ];
            }
        } else {
            $headers = ['Equipment Code', 'Equipment', 'Category', 'Total', 'Available', 'Borrowed', 'Damaged', 'Under Repair', 'Lost', 'Condition', 'Location', 'Record Status'];
            foreach ($service->inventory($filters) as $r) {
                $rows[] = [
                    $r['equipment_code'], $r['name'], $r['category_name'], $r['total_quantity'], $r['available_quantity'],
                    $r['borrowed_quantity'], $r['damaged_quantity'], $r['under_repair_quantity'], $r['lost_quantity'],
                    $r['condition_status'], $r['storage_location'], $r['record_status'],
                ];
            }
        }

        AuditLog::record('report.exported', 'report', null, 'Exported ' . $type . ' report as CSV.');

        $filename = 'codeblocks-' . $type . '-report-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}
