<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\ShiftCloseRequest;
use App\Http\Requests\Shift\ShiftOpenRequest;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function current(): JsonResponse
    {
        $shift = Shift::current();

        if (! $shift) {
            return response()->json([
                'success' => false,
                'message' => __('shift.none_open'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'shift' => [
                'id' => $shift->id,
                'opened_by' => $shift->openedBy->getFullname(),
                'opening_float' => (float) $shift->opening_float,
                'opened_at' => $shift->opened_at->toIso8601String(),
                'total_payments_so_far' => $shift->totalPayments(),
                'expected_cash_so_far' => $shift->expectedCash(),
            ],
        ]);
    }

    public function open(ShiftOpenRequest $request): JsonResponse
    {
        if (Shift::current()) {
            return response()->json([
                'success' => false,
                'message' => __('shift.already_open'),
            ], 400);
        }

        $shift = Shift::create([
            'opened_by' => $request->user()->id,
            'opening_float' => $request->opening_float,
            'opened_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('shift.opened_successfully'),
            'shift_id' => $shift->id,
        ]);
    }

    public function close(ShiftCloseRequest $request): JsonResponse
    {
        $shift = Shift::current();

        if (! $shift) {
            return response()->json([
                'success' => false,
                'message' => __('shift.none_open'),
            ], 400);
        }

        $shift->update([
            'closed_by' => $request->user()->id,
            'closing_cash_counted' => $request->closing_cash_counted,
            'closed_at' => now(),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'expected_cash' => $shift->expectedCash(),
            'counted' => (float) $shift->closing_cash_counted,
            'variance' => $shift->variance(),
        ]);
    }

    public function index(): JsonResponse
    {
        $shifts = Shift::with(['openedBy', 'closedBy'])
            ->latest('opened_at')
            ->paginate(20);

        return response()->json($shifts);
    }
}