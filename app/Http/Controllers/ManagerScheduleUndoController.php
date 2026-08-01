<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Scheduling\ScheduleUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerScheduleUndoController extends Controller
{
    public function __invoke(Request $request,ScheduleUndoService $undo,OdooManagerPlanningService $planning): RedirectResponse
    {
        $data=$request->validate(['token'=>['required','uuid']]);
        try{$result=$undo->undo($data['token'],$planning);}
        catch(\Throwable $exception){return back()->withErrors(['manager_shift'=>$exception->getMessage()]);}
        return back()->with('success',$result['undone'].' Odoo shift(s) restored by undoing “'.$result['label'].'”.');
    }
}
