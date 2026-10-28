<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessException;
use App\Core\Flash;
use App\Core\ValidationException;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Category;
use App\Models\Equipment;
use App\Services\BorrowService;

final class BorrowController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'status' => (string) $this->input('status', ''),
            'user_id' => is_admin() ? null : (int) Auth::id(),
        ];

        $paginator = BorrowRequest::paginate($filters, max(1, $this->intInput('page', 1)), 10);

        $this->render('borrow/index', [
            'title' => is_admin() ? 'All Borrowing Requests' : 'My Borrowing Requests',
            'activeNav' => 'borrow',
            'filters' => $filters,
            'paginator' => $paginator,
            'isAdmin' => is_admin(),
        ]);
    }

    public function create(): void
    {
        $equipmentId = $this->intInput('equipment_id', 0);

        $this->render('borrow/create', [
            'title' => 'New Borrowing Request',
            'activeNav' => 'borrow',
            'equipmentList' => Equipment::availableForSelect(),
            'categories' => Category::all(),
            'selectedEquipmentId' => $equipmentId,
            'maxDays' => (int) config('borrowing.max_days', 14),
            'maxQuantity' => (int) config('borrowing.max_quantity', 20),
        ]);
    }

    public function store(): void
    {
        try {
            $data = $this->validate([
                'equipment_id' => 'required|integer|min:1',
                'quantity' => 'required|integer|min:1|max:20',
                'purpose' => 'required|min:10|max:1000',
                'expected_return_date' => 'required|date|after_or_equal:today',
            ]);

            $requestId = (new BorrowService())->submit((int) Auth::id(), $data);
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'borrow/create');
        } catch (BusinessException $e) {
            $this->businessFailed($e, 'borrow/create');
        }

        $request = BorrowRequest::find($requestId);
        Flash::set('success', 'Request ' . ($request['request_code'] ?? '') . ' submitted. It is pending laboratory personnel approval.');
        redirect(url('borrow/' . $requestId));
    }

    public function show(string $id): void
    {
        $request = BorrowRequest::find((int) $id);
        if (!$request) {
            $this->notFound('That borrowing request does not exist.');
        }
        $this->requireOwnerOrAdmin((int) $request['user_id']);

        $transactions = BorrowTransaction::forRequest((int) $request['id']);

        $this->render('borrow/show', [
            'title' => 'Request ' . $request['request_code'],
            'activeNav' => 'borrow',
            'request' => $request,
            'items' => BorrowRequest::items((int) $request['id']),
            'transactions' => $transactions,
        ]);
    }

    public function approve(string $id): void
    {
        try {
            $result = (new BorrowService())->approve((int) $id, (int) Auth::id());
            $codes = array_map(static fn (array $t): string => (string) ($t['transaction_code'] ?? ''), $result['transactions']);
            Flash::set('success', 'Request approved. Checkout recorded: ' . implode(', ', array_filter($codes)) . '.');
        } catch (BusinessException $e) {
            Flash::set('error', $e->getMessage());
        }

        redirect(url('borrow/' . (int) $id));
    }

    public function reject(string $id): void
    {
        try {
            $this->validate(['rejection_reason' => 'nullable|max:500']);
            (new BorrowService())->reject((int) $id, (int) Auth::id(), (string) $this->input('rejection_reason', ''));
            Flash::set('success', 'Request rejected. The borrower has been notified in-app.');
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'borrow/' . (int) $id);
        } catch (BusinessException $e) {
            Flash::set('error', $e->getMessage());
        }

        redirect(url('borrow/' . (int) $id));
    }

    public function cancel(string $id): void
    {
        try {
            (new BorrowService())->cancel((int) $id, (int) Auth::id());
            Flash::set('success', 'Your request was cancelled.');
        } catch (BusinessException $e) {
            Flash::set('error', $e->getMessage());
        }

        redirect(url('borrow/' . (int) $id));
    }

    public function approvals(): void
    {
        $filters = [
            'status' => 'pending',
            'q' => (string) $this->input('q', ''),
        ];
        $paginator = BorrowRequest::paginate($filters, max(1, $this->intInput('page', 1)), 10);

        $this->render('borrow/approvals', [
            'title' => 'Pending Approvals',
            'activeNav' => 'approvals',
            'filters' => $filters,
            'paginator' => $paginator,
        ]);
    }

    public function transactions(): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'status' => (string) $this->input('status', ''),
            'overdue' => $this->input('overdue', ''),
            'user_id' => is_admin() ? null : (int) Auth::id(),
        ];

        $paginator = BorrowTransaction::paginate($filters, max(1, $this->intInput('page', 1)), 10);

        $this->render('borrow/transactions', [
            'title' => is_admin() ? 'Borrowing Transactions' : 'My Borrowed Equipment',
            'activeNav' => 'transactions',
            'filters' => $filters,
            'paginator' => $paginator,
            'isAdmin' => is_admin(),
        ]);
    }
}
