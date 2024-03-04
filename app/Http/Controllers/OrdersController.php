<?php

namespace App\Http\Controllers;

use app3s\util\Mail;
use app3s\util\Sessao;
use App\Enums\OrderStatus;
use App\Models\Division;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Order::class, 'order');
    }
    public function panelTable(Request $request)
    {
        $sessao = new Sessao();
        $firstName = $sessao->getNome();
        $arr = explode(' ', $sessao->getNome());
        if (isset($arr[0])) {
            $firstName = $arr[0];
        }
        $firstName = ucfirst(strtolower($firstName));
        $divisionsQuery = Division::select('id', 'name');

        $queryPendding = Order::select(
            'orders.id as id',
            'orders.description as descricao',
            'services.sla as tempo_sla',
            'orders.created_at as data_abertura',
            'orders.status as status',
            'orders.campus',
            'divisions.name as division_name'
        )
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->join('divisions', 'orders.division_id', '=', 'divisions.id')
            ->whereIn(
                'status',
                [
                    OrderStatus::opened()->value,
                    OrderStatus::reopened()->value,
                    OrderStatus::reserved()->value,
                ]
            )->orderByDesc('orders.id');

        if ($request->has('division') && is_array($request->input('division'))) {
            $divisionsFilter = $request->input('division');
            $queryPendding->whereIn('orders.division_id', $divisionsFilter);
            $divisionsQuery->whereIn('id', $divisionsFilter);
        }
        $divisions = $divisionsQuery->get();
        $penddingList = $queryPendding->limit(120)->get();
        $matrix = array();
        foreach ($penddingList as $order) {

            if (!isset($matrix[$order->campus][$order->division_name])) {
                $matrix[$order->campus][$order->division_name] = 1;
            } else {
                $matrix[$order->campus][$order->division_name]++;
            }
        }

        $data = [
            'orders' => $penddingList,
            'divisions' => $divisions,
            'divisionSig' => $sessao->getUnidade(),
            'userFirstName' => $firstName,
            'matrix' => $matrix
        ];

        if ($request->just_content === "1") {
            return view('admin.partials.table-panel-content', $data);
        } else {
            return view('admin.table-panel', $data);
        }
    }



    public function kanban(Request $request)
    {
        $divisions = $request->input('division', []);

        $sessao = new Sessao();
        $firstName = $sessao->getNome();
        $arr = explode(' ', $sessao->getNome());
        if (isset($arr[0])) {
            $firstName = $arr[0];
        }
        $firstName = ucfirst(strtolower($firstName));
        $divisions = Division::select('id', 'name')->get();


        $queryPendding = Order::select(
            'orders.id as id',
            'orders.description as descricao',
            'services.sla as tempo_sla',
            'orders.created_at',
            'orders.status as status',
            'customer.name as customer_name',
            'provider.name as provider_name',
            'orders.finished_at'
        )
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->leftJoin('users as customer', 'orders.customer_user_id', '=', 'customer.id')
            ->leftJoin('users as provider', 'orders.provider_user_id', '=', 'provider.id')
            ->whereIn(
                'orders.status',
                [
                    OrderStatus::opened()->value,
                    OrderStatus::reopened()->value,
                    OrderStatus::reserved()->value,
                ]
            )->orderByDesc('orders.id');
        $queryProgress = Order::select(
            'orders.id as id',
            'orders.description as descricao',
            'services.sla as tempo_sla',
            'orders.created_at',
            'orders.status as status',
            'customer.name as customer_name',
            'provider.name as provider_name',
            'orders.finished_at'
        )
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->join('users as customer', 'orders.customer_user_id', '=', 'customer.id')
            ->join('users as provider', 'orders.provider_user_id', '=', 'provider.id')
            ->whereIn(
                'orders.status',
                [

                    OrderStatus::pendingResource()->value,
                    OrderStatus::pendingCustomerResponse()->value,
                    OrderStatus::inProgress()->value
                ]
            )->orderByDesc('orders.id');
        $queryFinished = Order::select(
            'orders.id as id',
            'orders.description as descricao',
            'services.sla as tempo_sla',
            'orders.created_at',
            'orders.status as status',
            'customer.name as customer_name',
            'provider.name as provider_name',
            'orders.finished_at'
        )
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->join('users as customer', 'orders.customer_user_id', '=', 'customer.id')
            ->join('users as provider', 'orders.provider_user_id', '=', 'provider.id')
            ->whereIn('orders.status', [
                OrderStatus::closed()->value,
                OrderStatus::committed()->value,
                OrderStatus::canceled()->value,
            ])->orderByDesc('orders.id');


        if ($request->has('division') && is_array($request->input('division'))) {
            $divisions = $request->input('division');
            $queryPendding->whereIn('orders.division_id', $divisions);
            $queryProgress->whereIn('orders.division_id', $divisions);
            $queryFinished->whereIn('orders.division_id', $divisions);
        }
        $penddingList = $queryPendding->limit(120)->get();
        $progressList = $queryProgress->limit(120)->get();
        $finishedList = $queryFinished->limit(120)->get();


        $data = [
            'userFirstName' => ucfirst(strtolower($firstName)),
            'divisionSig' => $sessao->getUnidade(),
            'divisions' => $divisions,
            'penddingList' => $penddingList,
            'progressList' => $progressList,
            'finishedList' => $finishedList
        ];

        if ($request->just_content === "1") {
            return view('admin.partials.kanban-panel-content', $data);
        } else {
            return view('admin.kanban-panel', $data);
        }
    }
    /**
     * Store a newly created resource in storage.
     *
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
            'jpeg',
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
        $fileName = '';
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

            $mail = new Mail();
            $text = 'Sua solicitação foi realizada com sucesso.';

            $body = '    <p>Prezado(a) ' . auth()->user()->name . ',</p>
            <p>' . $text . '</p>
            <p><a href="' . env('APP_URL') . '?page=ocorrencia&selecionar='.$order->id.'">Ocorrência Nº ' . $order->id . '</a></p>
            <ul>
                <li>Serviço Solicitado: ' . $order->service->name . '</li>
                <li>Descrição do Problema: ' . $order->description . '</li>
                <li>Setor Responsável: ' . $order->service->division->name . ' - ' . $order->service->division->description . '</li>
                <li>Cliente: ' . $order->customer->name . '</li>
            </ul><br>
            <p>Mensagem enviada pelo sistema 3S. Favor não responder.</p>';


            $mail->send(auth()->user()->email, auth()->user()->name, '[3S] - Chamado Nº ' . $order->id, $body);
            return redirect('/?page=ocorrencia&selecionar=' . $order->id);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Erro ao enviar e-mail: ' . $e->getMessage());
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
                    $nome .= ' ' . strtolower($listaNome[1]);
                    if (isset($listaNome[2])) {
                        $nome .= ' ' . ucfirst(strtolower($listaNome[2]));
                    }
                } else {
                    $nome .= ' ' . ucfirst(strtolower($listaNome[1]));
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
