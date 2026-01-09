<?php

namespace Timeax\FortiPlugin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JsonException;

final class FortiProcessController extends Controller
{
    /**
     * @throws JsonException
     */
    public function show(Request $request, int $processId): JsonResponse
    {
        // uses the helper above
        $data = read_forti_process_log($processId);

        return response()->json($data);
    }
}