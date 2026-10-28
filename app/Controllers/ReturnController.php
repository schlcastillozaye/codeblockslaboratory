<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessException;
use App\Core\Flash;
use App\Core\ValidationException;
use App\Models\BorrowTransaction;
use App\Models\ReturnRecord;
use App\Services\ReturnService;

final class ReturnController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'condition' => (string) $this->input('condition', ''),
            'from' => (string) $this->input('from', ''),
            'to' => (string) $this->input('to', ''),
            'user_id' => is_admin() ? null : (int) Auth::id(),
        ];

        $paginator = ReturnRecord::paginate($filters, max(1, $this->intInput('page', 1)), 10);

        $this->render('returns/index', [
            'title' => 'Returns & Condition Reports',
            'activeNav' => 'returns',
            'filters' => $filters,
            'paginator' => $paginator,
            'isAdmin' => is_admin(),
        ]);
    }

    public function create(): void
    {
        $transactionId = $this->intInput('transaction', 0);
        $transaction = BorrowTransaction::find($transactionId);

        if (!$transaction) {
            Flash::set('error', 'Select an open borrowing transaction to process a return.');
            redirect(url('transactions'));
        }
        if ($transaction['status'] === 'returned') {
            Flash::set('error', 'That transaction has already been fully returned.');
            redirect(url('transactions'));
        }
        if (!in_array($transaction['status'], BorrowTransaction::OPEN_STATUSES, true)) {
            Flash::set('error', 'That transaction is not open for return.');
            redirect(url('transactions'));
        }

        $this->render('returns/create', [
            'title' => 'Process Return',
            'activeNav' => 'transactions',
            'transaction' => $transaction,
            'previousReturns' => ReturnRecord::forTransaction($transactionId),
        ]);
    }

    public function store(): void
    {
        $transactionId = $this->intInput('transaction_id', 0);
        $transaction = BorrowTransaction::find($transactionId);

        if (!$transaction) {
            Flash::set('error', 'Borrowing transaction not found.');
            redirect(url('transactions'));
        }

        try {
            $data = $this->validate([
                'transaction_id' => 'required|integer|min:1',
                'quantity' => 'required|integer|min:1',
                'return_date' => 'required|date|before_or_equal:today',
                'condition' => 'required|in:good,damaged,needs_maintenance,incomplete',
                'notes' => 'nullable|max:500',
            ]);

            (new ReturnService())->process($transactionId, [
                'quantity' => (int) $data['quantity'],
                'return_date' => (string) $data['return_date'],
                'condition' => (string) $data['condition'],
                'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
            ], (int) Auth::id());
        } catch (ValidationException $e) {
            // Re-render the form with HTTP 422; no database writes occurred.
            http_response_code(422);
            Flash::setErrors($e->errors());
            Flash::setOld($_POST);
            Flash::set('error', $e->getMessage());
            $this->render('returns/create', [
                'title' => 'Process Return',
                'activeNav' => 'transactions',
                'transaction' => $transaction,
                'previousReturns' => ReturnRecord::forTransaction($transactionId),
            ]);
            return;
        } catch (BusinessException $e) {
            Flash::set('error', $e->getMessage());
            redirect(url('returns/create', ['transaction' => $transactionId]));
        }

        Flash::set('success', 'Return recorded. Inventory and condition history were updated atomically.');
        redirect(url('returns'));
    }
}
