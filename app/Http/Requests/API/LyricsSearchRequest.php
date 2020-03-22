<?php

namespace App\Http\Requests\API;

/**
 * @property string $title
 * @property string $artist
 * @property string $provider
 * @property int $resultIndex
 */
class LyricsSearchRequest extends Request
{
    public function authorize(): bool
    {
        return true;
        //return $this->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string',
            'artist' => 'nullable|string',
            'provider' => 'required|string',
            'resultIndex' => 'required|int',
        ];
    }
}
