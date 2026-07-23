<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\Contact;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contact::query();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }

        $perPage = min((int) $request->query('per_page', 200), 500);
        $page = $query->orderBy('name')->paginate($perPage);

        return ApiResponse::success($page->items(), 'Contacts retrieved.', ['pagination' => [
            'total' => $page->total(), 'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate($this->rules($branchId));

        $contact = Contact::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);

        return ApiResponse::success($contact, 'Contact created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $contact = Contact::findOrFail($id);
        $data = $request->validate($this->rules($branchId));

        $contact->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($contact, 'Contact updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Contact::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Contact deleted.');
    }

    private function rules(int $branchId): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'type' => ['required', Rule::in(['student', 'guardian', 'employee', 'custom'])],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('branch_id', $branchId)],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
