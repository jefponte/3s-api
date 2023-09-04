<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Models\OrderStatusLog;
use Illuminate\Http\Request;

class OrderStatusLogsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $orderstatuslogs = OrderStatusLog::latest()->paginate(25);

        return $orderstatuslogs;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        
        $orderstatuslog = OrderStatusLog::create($request->all());

        return response()->json($orderstatuslog, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $orderstatuslog = OrderStatusLog::findOrFail($id);

        return $orderstatuslog;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        
        $orderstatuslog = OrderStatusLog::findOrFail($id);
        $orderstatuslog->update($request->all());

        return response()->json($orderstatuslog, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        OrderStatusLog::destroy($id);

        return response()->json(null, 204);
    }
}
