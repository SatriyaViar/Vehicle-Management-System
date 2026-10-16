<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'requester_name' => ['required', 'string', 'max:120'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string'],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],
            'approver_1_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'approver')],
            'approver_2_id' => ['required', 'integer', 'different:approver_1_id', Rule::exists('users', 'id')->where('role', 'approver')],
        ];
    }

    public function bookingData(): array
    {
        $data = $this->validated();
        foreach (['start_at', 'end_at'] as $field) {
            $data[$field] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data[$field], 'Asia/Jakarta')->utc();
        }

        return $data;
    }
}
