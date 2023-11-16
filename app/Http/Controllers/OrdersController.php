<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Mail\OrderUpdated;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        dd(env('DB_USERNAME'));
        $allowedExtensions = [
            'image/jpeg', 'image/png', 'application/pdf',
            'xlsx', 'xlsm', 'xlsb', 'xltx', 'xltm', 'xls',
            'xlt', 'xls', 'xml', 'xml', 'xlam', 'xla',
            'xlw', 'xlr', 'doc', 'docm', 'docx', 'docx',
            'dot', 'dotm', 'dotx', 'odt', 'pdf', 'rtf',
            'txt', 'wps', 'xml', 'zip', 'rar', 'ovpn',
            'xml', 'xps', 'jpg', 'gif', 'png', 'pdf',
            'jpeg',
        ];

        $request->validate([
            'description' => ['required', 'max:255'],
            'campus' => ['required', 'max:100'],
            'email' => ['required', 'max:100'],
            'service_id' => ['required', 'max:100'],
            'attachment' => ['nullable', 'mimetypes:'.implode(',', $allowedExtensions)],
            'tag' => ['nullable', 'max:12'],
            'phone_number' => ['nullable', 'max:12'],
            'place' => ['nullable', 'max:12'],
        ]);
        $fileName = '';
        if ($request->hasFile('attachment')) {
            $attachment = $request->file('attachment');
            if (! Storage::exists('public/uploads')) {
                Storage::makeDirectory('public/uploads');
            }
            $fileName = $attachment->getClientOriginalName();
            if (Storage::exists('public/uploads/'.$attachment->getClientOriginalName())) {
                $fileName = uniqid().'_'.$fileName;
            }
            $path = $attachment->storeAs('public/uploads/', $fileName);
            if (! $path) {
                return redirect()->back()->withErrors(['attachment' => 'Erro ao salvar o arquivo.']);
            }
        }
        $service = Service::find($request->service_id);
        DB::beginTransaction();
        try {
            $data =
                [
                    'division_id' => $service->division->id,
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
                    'place' => $request->place,
                ];
            $order = Order::create($data);
            OrderStatusLog::create([
                'order_id' => $order->id,
                'status' => 'opened',
                'message' => 'Ocorrência liberada para que qualquer técnico possa atender.',
                'user_id' => auth()->user()->id,
            ]);
            DB::commit();

            Mail::to(auth()->user()->email)->send(new OrderUpdated(auth()->user()->name, $order, 'Sua solicitação foi realizada com sucesso.'));

            return redirect('/?page=ocorrencia&selecionar='.$order->id);
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withErrors(['flash_message' => 'Falha ao inserir dados.']);
        }
    }

    public function show(Order $order)
    {

        $order->load([
            'messages' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'messages.user',
            'statusLogs' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'division',
            'customer',
            'provider.division',
            'service.division',
        ]);
        $dataSolucao = $this->calcularHoraSolucao($order->created_at, $order->service->sla);
        $order->canEditService = $this->possoEditarServico($order);
        $order->canCancel = $this->canCancel($order);
        $order->canRespond = $this->possoAtender($order);
        $order->canClose = $this->possoFechar($order);
        $order->canRate = $this->possoAvaliar($order);
        $order->canReopen = $this->possoReabrir($order);
        $order->canReserve = $this->possoReservar($order);
        $order->canRelease = $this->possoLiberar($order);
        $order->canWait = $this->canWait($order);
        $order->canSendMessage = $this->possoEnviarMensagem($order);

        foreach ($order->messages as $mensagemForum) {
            $nome = $mensagemForum->user->name;

            $listaNome = explode(' ', $mensagemForum->user->name);
            if (isset($listaNome[0])) {
                $nome = ucfirst(strtolower($listaNome[0]));
            }
            if (isset($listaNome[1])) {
                if (strlen($listaNome[1]) <= 2) {
                    $nome .= ' '.strtolower($listaNome[1]);
                    if (isset($listaNome[2])) {
                        $nome .= ' '.ucfirst(strtolower($listaNome[2]));
                    }
                } else {
                    $nome .= ' '.ucfirst(strtolower($listaNome[1]));
                }
            }
            $mensagemForum->firstName = $nome;
        }
        $listColorStatus = [
            OrderStatus::opened()->value => '  notice-warning',
            OrderStatus::inProgress()->value => '  notice-info ',
            OrderStatus::closed()->value => 'notice-success ',
            OrderStatus::committed()->value => 'notice-success ',
            OrderStatus::canceled()->value => ' notice-warning ',
            OrderStatus::reopened()->value => '  notice-warning ',
            OrderStatus::reserved()->value => '  notice-warning ',
            OrderStatus::pendingResource()->value => '  notice-warning ',
            OrderStatus::pendingCustomerResponse()->value => ' notice-warning',
        ];

        return view('orders.show', $order);
    }
}
