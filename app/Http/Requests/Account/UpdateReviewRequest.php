<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateReviewRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{rating: int, title: string|null, comment: string|null}
     */
    public function reviewAttributes(): array
    {
        return [
            'rating' => $this->integer('rating'),
            'title' => $this->filled('title') ? $this->string('title')->toString() : null,
            'comment' => $this->filled('comment') ? $this->string('comment')->toString() : null,
        ];
    }
}
