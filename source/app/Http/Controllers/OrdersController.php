<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Mail\OrderUpdated;
use App\Models\Division;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class OrdersController extends Controller
{


    public function __construct()
    {
        $this->authorizeResource(Order::class, 'order');
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {

        $allowedExtensions = [
            'image/jpeg', 'image/png', 'application/pdf',
            'xlsx', 'xlsm', 'xlsb', 'xltx', 'xltm', 'xls',
            'xlt', 'xls', 'xml', 'xml', 'xlam', 'xla',
            'xlw', 'xlr', 'doc', 'docm', 'docx', 'docx',
            'dot', 'dotm', 'dotx', 'odt', 'pdf', 'rtf',
            'txt', 'wps', 'xml', 'zip', 'rar', 'ovpn',
            'xml', 'xps', 'jpg', 'gif', 'png', 'pdf',
            'jpeg'
        ];

        $request->validate([
            'description' => ['required', 'max:255'],
            'campus' => ['required', 'max:100'],
            'email' => ['required', 'max:100'],
            'service_id' => ['required', 'max:100'],
            'attachment' => ['nullable', 'mimetypes:' . implode(',', $allowedExtensions)],
            'tag' => ['nullable', 'max:12'],
            'phone_number' => ['nullable', 'max:12'],
            'place' => ['nullable', 'max:12'],
        ]);
        $fileName = "";
        if ($request->hasFile('attachment')) {
            $attachment = $request->file('attachment');
            if (!Storage::exists('public/uploads')) {
                Storage::makeDirectory('public/uploads');
            }
            $fileName = $attachment->getClientOriginalName();
            if (Storage::exists('public/uploads/' . $attachment->getClientOriginalName())) {
                $fileName = uniqid() . '_' . $fileName;
            }
            $path = $attachment->storeAs('public/uploads/', $fileName);
            if (!$path) {
                return redirect()->back()->withErrors(['attachment' => 'Erro ao salvar o arquivo.']);
            }
        }
        $service = Service::find($request->service_id);
        DB::beginTransaction();
        try {
            $data =
                [
                    'division_id' =>  $service->division->id,
                    'service_id' => $service->id,
                    'division_sig_id' => auth()->user()->division_sig_id,
                    'division_sig' => auth()->user()->division_sig,
                    'customer_user_id' => auth()->user()->id,
                    'description' => $request->description,
                    'campus' => $request->campus,
                    'tag' => $request->tag,
                    'phone_number' => $request->phone_number,
                    'status' => 'opened',
                    'email' => $request->email,
                    'attachment' => $fileName,
                    'place' => $request->place
                ];
            $order = Order::create($data);
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => 'opened',
                'message' => "Ocorrência liberada para que qualquer técnico possa atender.",
                'user_id' => auth()->user()->id
            ]);
            DB::commit();
            Mail::to(auth()->user()->email)->send(new OrderUpdated(auth()->user()->name, $order, "Sua solicitação foi realizada com sucesso."));
            return redirect('/?page=ocorrencia&selecionar='.$order->id);
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->withErrors(['flash_message' => 'Falha ao inserir dados.']);
        }
    }

}
