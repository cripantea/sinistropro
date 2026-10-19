<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Le pagine web rispondono agli errori di validazione con un redirect (vedi bootstrap/app.php):
 * gli endpoint chiamati da axios usano questo, così gli errori tornano in JSON 422.
 */
trait ValidaJson
{
    protected function validaJson(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            abort(response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422));
        }

        return $validator->validated();
    }
}
