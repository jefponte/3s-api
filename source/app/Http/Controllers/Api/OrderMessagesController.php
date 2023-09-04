<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Models\OrderMessage;
use Illuminate\Http\Request;

class OrderMessagesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $ordermessages = OrderMessage::latest()->paginate(25);

        return $ordermessages;
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
        
        $ordermessage = OrderMessage::create($request->all());

        return response()->json($ordermessage, 201);
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
        $ordermessage = OrderMessage::findOrFail($id);

        return $ordermessage;
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
        
        $ordermessage = OrderMessage::findOrFail($id);
        $ordermessage->update($request->all());

        return response()->json($ordermessage, 200);
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
        OrderMessage::destroy($id);

        return response()->json(null, 204);
    }
}
