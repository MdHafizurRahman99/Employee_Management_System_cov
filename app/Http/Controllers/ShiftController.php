<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view(
            'admin.shift.list',
            [
                'shifts' => Shift::all(),
            ]
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $start_time = Carbon::createFromFormat('H:i', $request->start_time)->format('h:i A');
        $end_time = Carbon::createFromFormat('H:i', $request->end_time)->format('h:i A');
        // return $request;
        $shift_name = $start_time . '-' . $end_time;


        // return $shift_name;

        $shift = Shift::create([
            'shift_name' => $shift_name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'break_time' => $request->break_time,
        ]);
        return back()->with('message', 'Shift Added Successfully');
        // return redirect()->route('staffschedule.index')->with('message', 'Shift Added Successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Shift $shift)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Shift $shift)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        // return $request;
        $shift = Shift::where('id', $request->id)->first();
        // $start_time = Carbon::createFromFormat('H:i:s', $request->start_time)->format('h:i A');
        // $end_time = Carbon::createFromFormat('H:i:s', $request->end_time)->format('h:i A');

        // // Create shift_name using the parsed times
        // $shift_name = $start_time . '-' . $end_time;

        $start_time = Carbon::createFromFormat('H:i', $request->start_time)->format('h:i A');
        $end_time = Carbon::createFromFormat('H:i', $request->end_time)->format('h:i A');
        // return $request;
        $shift_name = $start_time . '-' . $end_time;
        $shift->update([
            'shift_name' => $shift_name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'break_time' => $request->break_time,
        ]);
        return redirect()->route('shift.index')->with('message', 'Shift Updated Successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shift $id)
    {
        $id->delete();
        return redirect()->route('shift.index')->with('message', 'Shift Deleted Successfully!');
        // return $id;
    }
    public function getShiftData(Request $request)
    {
        // $staffId = $request->input('staff_id');
        $shiftId = $request->input('shift_id');

        // return $schedule;
        $shift = Shift::where('id', $shiftId)
            ->first();

        // Retrieve schedule data based on staff ID and shift ID
        // $schedule = Schedule::where('staff_id', $staffId)
        //     ->where('shift_id', $shiftId)
        //     ->first();

        // You might want to add additional error handling here if the schedule is not found

        return response()->json(['data' => $shift]);
    }
}
