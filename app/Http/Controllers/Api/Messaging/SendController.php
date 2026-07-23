<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Academic\ClassConfig;
use App\Models\Messaging\Contact;
use App\Models\Messaging\SmsBalance;
use App\Models\Messaging\SmsBatch;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use App\Services\Sms\InsufficientSmsBalanceException;
use App\Services\Sms\SmsSender;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SendController extends Controller
{
    public function send(Request $request, SmsSender $sender): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'audience_type' => ['required', Rule::in(['class', 'section', 'contact', 'custom_numbers'])],
            'message' => ['required', 'string', 'max:1000'],
            'template_id' => ['nullable', 'integer', Rule::exists('sms_templates', 'id')->where('branch_id', $branchId)],
            'class_id' => ['required_if:audience_type,class', 'integer', Rule::exists('classes', 'id')],
            'class_config_id' => ['required_if:audience_type,section', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'contact_ids' => ['required_if:audience_type,contact', 'array', 'min:1'],
            'contact_ids.*' => ['integer', Rule::exists('contacts', 'id')->where('branch_id', $branchId)],
            'numbers' => ['required_if:audience_type,custom_numbers', 'array', 'min:1'],
            'numbers.*.phone' => ['required', 'string', 'max:20'],
            'numbers.*.name' => ['nullable', 'string', 'max:150'],
        ]);

        $recipients = match ($data['audience_type']) {
            'class' => $this->recipientsForClass($data['class_id']),
            'section' => $this->recipientsForClassConfig($data['class_config_id']),
            'contact' => $this->recipientsForContacts($data['contact_ids']),
            'custom_numbers' => array_map(fn ($n) => ['phone' => $n['phone'], 'name' => $n['name'] ?? null], $data['numbers']),
        };

        if (empty($recipients)) {
            throw ValidationException::withMessages(['audience_type' => 'No recipients with a phone number were found for this audience.']);
        }

        try {
            $batch = $sender->send(
                branchId: $branchId,
                audienceType: $data['audience_type'],
                recipients: $recipients,
                message: $data['message'],
                templateId: $data['template_id'] ?? null,
                audienceFilter: collect($data)->only(['class_id', 'class_config_id', 'contact_ids'])->all(),
                createdBy: auth()->id(),
            );
        } catch (InsufficientSmsBalanceException $e) {
            return ApiResponse::error($e->getMessage(), 'INSUFFICIENT_SMS_BALANCE', status: 422);
        }

        return ApiResponse::success($batch, 'SMS batch queued.', status: 201);
    }

    public function batches(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 200);
        $page = SmsBatch::with('template')->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success($page->items(), 'Batches retrieved.', ['pagination' => [
            'total' => $page->total(), 'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function show(int $id): JsonResponse
    {
        $batch = SmsBatch::with(['template', 'messages'])->findOrFail($id);

        return ApiResponse::success($batch, 'Batch retrieved.');
    }

    public function balance(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $balance = SmsBalance::firstOrCreate(['branch_id' => $branchId], ['balance' => 0]);

        return ApiResponse::success(['balance' => $balance->balance], 'Balance retrieved.');
    }

    public function topup(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);

        $balance = SmsBalance::firstOrCreate(['branch_id' => $branchId], ['balance' => 0]);
        $balance->increment('balance', $data['amount']);

        return ApiResponse::success(['balance' => $balance->fresh()->balance], 'Balance topped up.');
    }

    /** @return array<int, array{phone: string, name: ?string, student_id: int}> */
    private function recipientsForClass(int $classId): array
    {
        $classConfigIds = ClassConfig::where('class_id', $classId)->pluck('id');

        return Enrollment::with('student')->whereIn('class_config_id', $classConfigIds)->current()->get()
            ->map(fn (Enrollment $e) => $this->recipientFromStudent($e->student))
            ->filter()->values()->all();
    }

    private function recipientsForClassConfig(int $classConfigId): array
    {
        return Enrollment::with('student')->where('class_config_id', $classConfigId)->current()->get()
            ->map(fn (Enrollment $e) => $this->recipientFromStudent($e->student))
            ->filter()->values()->all();
    }

    private function recipientsForContacts(array $contactIds): array
    {
        return Contact::whereIn('id', $contactIds)->get()
            ->map(fn (Contact $c) => ['phone' => $c->phone, 'name' => $c->name, 'student_id' => $c->student_id, 'employee_id' => $c->employee_id])
            ->all();
    }

    private function recipientFromStudent(?Student $student): ?array
    {
        if (! $student) {
            return null;
        }
        $phone = $student->father_mobile ?: $student->mother_mobile ?: $student->mobile;
        if (! $phone) {
            return null;
        }

        return ['phone' => $phone, 'name' => $student->name, 'student_id' => $student->id];
    }
}
