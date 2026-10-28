<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessException;
use App\Core\Flash;
use App\Core\ValidationException;
use App\Models\BorrowTransaction;
use App\Models\Category;
use App\Models\ConditionHistory;
use App\Models\Equipment;
use App\Services\EquipmentService;

final class EquipmentController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'category_id' => $this->intInput('category_id'),
            'availability' => (string) $this->input('availability', ''),
            'condition' => (string) $this->input('condition', ''),
            'status' => (string) $this->input('status', ''),
            'sort' => (string) $this->input('sort', 'name'),
        ];

        if (!is_admin()) {
            $filters['status'] = 'active';
        }

        $paginator = Equipment::paginate($filters, max(1, $this->intInput('page', 1)), 9);

        $this->render('equipment/index', [
            'title' => 'Equipment Catalog',
            'activeNav' => 'equipment',
            'filters' => $filters,
            'paginator' => $paginator,
            'categories' => Category::all(),
            'resultCount' => $paginator->total,
        ]);
    }

    public function show(string $id): void
    {
        $equipment = Equipment::find((int) $id);
        if (!$equipment) {
            $this->notFound('That equipment record does not exist.');
        }

        $this->render('equipment/show', [
            'title' => $equipment['name'],
            'activeNav' => 'equipment',
            'equipment' => $equipment,
            'transactions' => BorrowTransaction::forEquipment((int) $equipment['id'], 12),
            'conditionHistory' => ConditionHistory::forEquipment((int) $equipment['id'], 15),
        ]);
    }

    public function create(): void
    {
        $this->render('equipment/form', [
            'title' => 'Add Equipment',
            'activeNav' => 'equipment',
            'equipment' => null,
            'categories' => Category::all(),
        ]);
    }

    public function store(): void
    {
        try {
            $data = $this->validate($this->catalogRules());
            $id = (new EquipmentService())->create($data, (int) Auth::id());
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'equipment/create');
        }

        Flash::set('success', 'Equipment record created. It is now available in the catalog.');
        redirect(url('equipment/' . $id));
    }

    public function edit(string $id): void
    {
        $equipment = Equipment::find((int) $id);
        if (!$equipment) {
            $this->notFound('That equipment record does not exist.');
        }

        $this->render('equipment/form', [
            'title' => 'Edit ' . $equipment['name'],
            'activeNav' => 'equipment',
            'equipment' => $equipment,
            'categories' => Category::all(),
        ]);
    }

    public function update(string $id): void
    {
        $equipmentId = (int) $id;
        $equipment = Equipment::find($equipmentId);
        if (!$equipment) {
            $this->notFound('That equipment record does not exist.');
        }

        try {
            $data = $this->validate($this->catalogRules() + [
                'record_status' => 'required|in:active,archived',
            ]);
            (new EquipmentService())->update($equipmentId, $data, (int) Auth::id());
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'equipment/' . $equipmentId . '/edit');
        }

        Flash::set('success', 'Equipment record updated.');
        redirect(url('equipment/' . $equipmentId));
    }

    public function archive(string $id): void
    {
        try {
            (new EquipmentService())->archive((int) $id, (int) Auth::id());
            Flash::set('success', 'Equipment archived. It is no longer borrowable.');
        } catch (BusinessException $e) {
            Flash::set('error', $e->getMessage());
        }
        redirect(url('equipment/' . (int) $id));
    }

    public function restore(string $id): void
    {
        try {
            (new EquipmentService())->restore((int) $id, (int) Auth::id());
            Flash::set('success', 'Equipment restored to the active catalog.');
        } catch (BusinessException $e) {
            Flash::set('error', $e->getMessage());
        }
        redirect(url('equipment/' . (int) $id));
    }

    public function adjustCondition(string $id): void
    {
        $equipmentId = (int) $id;
        if (!Equipment::find($equipmentId)) {
            $this->notFound('That equipment record does not exist.');
        }

        try {
            $this->validate([
                'from_bucket' => 'required|in:available,damaged,under_repair,lost',
                'to_bucket' => 'required|in:available,damaged,under_repair,lost',
                'quantity' => 'required|integer|min:1',
                'notes' => 'nullable|max:500',
            ]);

            (new EquipmentService())->adjustCondition(
                $equipmentId,
                (string) $this->input('from_bucket'),
                (string) $this->input('to_bucket'),
                $this->intInput('quantity'),
                (string) $this->input('notes', ''),
                (int) Auth::id()
            );
            Flash::set('success', 'Condition updated. Inventory buckets were adjusted; stock was not double-counted.');
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'equipment/' . $equipmentId);
        } catch (BusinessException $e) {
            $this->businessFailed($e, 'equipment/' . $equipmentId);
        }

        redirect(url('equipment/' . $equipmentId));
    }

    /** @return array<string, string> */
    private function catalogRules(): array
    {
        return [
            'equipment_code' => 'required|max:40|regex:/^[A-Za-z0-9][A-Za-z0-9_-]{1,39}$/',
            'name' => 'required|min:2|max:150',
            'category_id' => 'required|integer|min:1',
            'description' => 'nullable|max:2000',
            'total_quantity' => 'required|integer|min:1|max:10000',
            'storage_location' => 'required|min:2|max:150',
            'barcode' => 'nullable|max:100|regex:/^[A-Za-z0-9_.-]+$/',
        ];
    }
}
