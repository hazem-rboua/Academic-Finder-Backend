<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exam_code' => 'required|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'exam_code.required' => __('validation.required', ['attribute' => 'exam code']),
            'exam_code.string' => __('validation.string', ['attribute' => 'exam code']),
            'exam_code.max' => __('validation.max.string', ['attribute' => 'exam code', 'max' => 255]),
        ];
    }
}

