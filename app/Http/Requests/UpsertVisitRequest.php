<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('visit') !== null;

        $allowedStatuses = $isUpdate
            ? ['Draft', 'Planned', 'Confirmed', 'Completed', 'Cancelled']
            : ['Draft', 'Planned'];

        $allowedSupportInitials = User::where('is_active', true)
            ->whereNotNull('initial')
            ->where('initial', '!=', '')
            ->where('role', '!=', 'sales')
            ->pluck('initial')
            ->map(fn ($init) => strtoupper(trim((string) $init)))
            ->unique()
            ->values()
            ->all();

        return [
            'title' => 'required|string|max:255',
            'visit_date' => 'required|date',
            'start_time' => 'required|string',
            'visit_type' => 'required|string',
            'visit_purpose' => 'nullable|string',
            'customer_id' => 'required|exists:customers,id',
            'customer_pic_id' => 'nullable|exists:customer_pics,id',
            'support_marketing' => 'nullable|array',
            'support_marketing.*' => [
                'string',
                Rule::in($allowedSupportInitials),
            ],
            'agenda' => 'nullable|string',
            'previsit_notes' => 'nullable|string',
            'status' => [
                'required',
                Rule::in($allowedStatuses),
            ],
            'postvisit_result' => 'nullable|string',

            'principal_reviews' => 'required|array|min:1',
            'principal_reviews.*.principal_id' => [
                'required',
                'integer',
                Rule::exists('principals', 'id')->where('is_active', true),
            ],
            'principal_reviews.*.principal_name' => 'nullable|string',
            'principal_reviews.*.exposure_type' => 'nullable|string',
            'principal_reviews.*.opportunity_status' => 'nullable|string',
            'principal_reviews.*.notes' => 'nullable|string',
            'principal_reviews.*.products' => 'nullable|array',
            'principal_reviews.*.products.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'action_plans' => 'nullable|array',
            'action_plans.*.task_description' => 'required|string',
            'action_plans.*.owner_user_id' => 'nullable|exists:users,id',
            'action_plans.*.due_date' => 'nullable|date',
            'action_plans.*.priority' => 'nullable|string',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('status') !== 'Completed') {
                    return;
                }

                if (trim((string) $this->input('postvisit_result', '')) === '') {
                    $validator->errors()->add(
                        'postvisit_result',
                        'Post-visit Result wajib dilengkapi jika status kunjungan Completed.'
                    );
                }

                $actionPlans = $this->input('action_plans', []);
                if (! is_array($actionPlans) || empty($actionPlans)) {
                    $validator->errors()->add(
                        'action_plans',
                        'Action Plan wajib diisi jika status kunjungan Completed.'
                    );
                }
            },
        ];
    }
}
