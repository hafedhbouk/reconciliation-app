<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHolidayRequest;
use App\Http\Requests\Admin\UpdateHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Holiday::class, 'holiday');
    }

    public function index(): View
    {
        $holidays = Holiday::query()->orderByDesc('holiday_date')->paginate(15);

        return view('admin.holidays.index', compact('holidays'));
    }

    public function create(): View
    {
        return view('admin.holidays.create');
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        Holiday::query()->create($request->validated());

        return redirect()->route('admin.holidays.index')->with('status', __('Jour férié créé avec succès.'));
    }

    public function edit(Holiday $holiday): View
    {
        return view('admin.holidays.edit', compact('holiday'));
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->validated());

        return redirect()->route('admin.holidays.index')->with('status', __('Jour férié mis à jour avec succès.'));
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->route('admin.holidays.index')->with('status', __('Jour férié supprimé avec succès.'));
    }
}
