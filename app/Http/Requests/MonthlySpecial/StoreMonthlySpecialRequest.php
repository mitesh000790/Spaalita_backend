<?php

namespace App\Http\Requests\MonthlySpecial;

use App\Http\Requests\ApiFormRequest;
use App\Rules\Truthy;
use App\Support\Js;
use Illuminate\Validation\Validator;

class StoreMonthlySpecialRequest extends ApiFormRequest
{
    protected ?string $failureMessage = 'All fields are required';

    public function rules(): array
    {
        return [
            'month' => [new Truthy],
        ];
    }

    /**
     * The image may be sent as a field (base64 data URI or URL) or as an
     * uploaded "image" file (multipart, stored by the upload middleware).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasImage = Js::truthy($this->validationData()['image'] ?? null)
                    || $this->attributes->get('uploaded_file') !== null;

                if (! $hasImage) {
                    $validator->errors()->add('image', $this->failureMessage);
                }
            },
        ];
    }
}
