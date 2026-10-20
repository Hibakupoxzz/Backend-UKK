<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    // GET /trips/{trip}/expenses
    public function index(Trip $trip)
    {
        $this->authorize('view', $trip);

        $expenses = $trip->expenses()->with('receipts')->orderBy('tanggal')->get();

        return ExpenseResource::collection($expenses)->additional([
            'total_biaya' => (float) $expenses->sum('jumlah'),
        ]);
    }

    // POST /trips/{trip}/expenses
    public function store(StoreExpenseRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('manageExpenses', $trip);

        $expense = $trip->expenses()->create($request->validated());

        return (new ExpenseResource($expense->load('receipts')))
            ->response()->setStatusCode(201);
    }

    // GET /expenses/{expense}
    public function show(Expense $expense)
    {
        $this->authorize('view', $expense->trip);

        return new ExpenseResource($expense->load('receipts'));
    }

    // PUT /expenses/{expense}
    public function update(StoreExpenseRequest $request, Expense $expense)
    {
        $this->authorize('manageExpenses', $expense->trip);

        $expense->update($request->validated());

        return new ExpenseResource($expense->load('receipts'));
    }

    // DELETE /expenses/{expense}
    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('manageExpenses', $expense->trip);

        foreach ($expense->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->file_path);
        }
        $expense->delete();

        return response()->json(['message' => 'Pengeluaran dihapus.']);
    }

    // POST /expenses/{expense}/receipts  (form-data: file)
    public function uploadReceipt(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('manageExpenses', $expense->trip);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        $receipt = $expense->receipts()->create([
            'file_path' => $request->file('file')->store("expenses/{$expense->id}", 'public'),
        ]);

        return response()->json([
            'id'  => $receipt->id,
            'url' => Storage::disk('public')->url($receipt->file_path),
        ], 201);
    }

    // DELETE /receipts/{receipt}
    public function deleteReceipt(ExpenseReceipt $receipt): JsonResponse
    {
        $this->authorize('manageExpenses', $receipt->expense->trip);

        Storage::disk('public')->delete($receipt->file_path);
        $receipt->delete();

        return response()->json(['message' => 'Bukti dihapus.']);
    }
}
