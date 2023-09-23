<?php

/**
 * Classe feita para manipulação do objeto OcorrenciaController
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 */

namespace app3s\controller;

use Illuminate\Support\Facades\Http;
use app3s\dao\OcorrenciaDAO;
use app3s\model\Ocorrencia;
use app3s\util\Mail;
use app3s\util\Sessao;
use App\Models\Division;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderStatusLog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OcorrenciaController
{

	protected $selecionado;
	protected $sessao;
	protected $dao;

	public function __construct()
	{
		$this->dao = new OcorrenciaDAO();
	}
	public function main()
	{

		echo '

<div class="card mb-4">
        <div class="card-body">';
		if (isset($_GET['selecionar'])) {
			$this->show();
		} else if (isset($_GET['cadastrar'])) {
			$this->create();
		} else {
			$this->index();
		}

		echo '
	</div>
</div>
';
	}
	public function index()
	{


		$this->sessao = new Sessao();
		$listaAtrasados = array();
		$lista = array();

		$queryPendding = Order::select(
			'orders.id as id',
			'orders.description as descricao',
			'services.sla as tempo_sla',
			'orders.created_at as data_abertura',
			'orders.status as status'
		)
			->join('services', 'orders.service_id', '=', 'services.id')
			->whereIn(
				'status',
				[
					self::STATUS_ABERTO,
					self::STATUS_AGUARDANDO_ATIVO,
					self::STATUS_AGUARDANDO_USUARIO,
					self::STATUS_ATENDIMENTO,
					self::STATUS_REABERTO,
					self::STATUS_RESERVADO
				]
			)->orderByDesc('orders.id');
		$queryFinished = Order::select(
			'orders.id as id',
			'orders.description as descricao',
			'services.sla as tempo_sla',
			'orders.created_at as data_abertura',
			'orders.status as status'
		)
			->join('services', 'orders.service_id', '=', 'services.id')
			->whereIn('status', [
				self::STATUS_FECHADO,
				self::STATUS_FECHADO_CONFIRMADO,
				self::STATUS_CANCELADO
			])->orderByDesc('orders.id');

		$queryPendding = $this->applyFilters($queryPendding);
		$queryFinished = $this->applyFilters($queryFinished);

		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
			$queryPendding = $queryPendding->where('customer_user_id', $this->sessao->getIdUsuario());
			$queryFinished = $queryFinished->where('customer_user_id', $this->sessao->getIdUsuario());
		}
		$lista = $queryPendding->limit(300)->get();
		$lista2 = $queryFinished->limit(300)->get();


		$listaAtrasados = array();
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
			$listNoLate = $lista;
		} else {
			$listNoLate = array();
			foreach ($lista as $ocorrencia) {
				if ($this->atrasado($ocorrencia)) {
					$listaAtrasados[] = $ocorrencia;
				} else {
					$listNoLate[] = $ocorrencia;
				}
			}
		}


		//Painel principal
		echo '

		<div class="row">
			<div class="col-md-8 blog-main">
				<div class="panel-group" id="accordion">';


		if (count($listaAtrasados) > 0) {

			echo view(
				'partials.index-orders',
				[
					'orders' => $listaAtrasados,
					'id' => 'collapseAtraso',
					'title' => 'Ocorrências Em Atraso (' . count($listaAtrasados) . ')',
					'strShow' => "show"
				]
			);
		}
		$this->painel($listNoLate, 'Ocorrências Em Aberto(' . count($listNoLate) . ')', 'collapseAberto', 'show');
		$this->painel($lista2, "Ocorrências Encerradas", 'collapseEncerrada');
		echo '
			</div>
		</div>';

		//Painel Lateral
		echo '
		<aside class="col-md-4 blog-sidebar">';
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_ADM || $this->sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO) {
			$userDivision = Division::where('id', request()->user()->division_id)->first();
			$attendents = User::where('role', Sessao::NIVEL_ADM)
				->orWhere('role', Sessao::NIVEL_TECNICO)->get();
			$allUsers = User::get();
			$applicants = Order::select('place as division_sig', 'division_sig_id as division_sig_id')->distinct()->limit(400)->get();
			$divisions = Division::select('id', 'name')->get();

			echo '
                <div class="p-4 mb-3 bg-light rounded">
                    <h4 class="font-italic">Filtros</h4>';

			echo view('partials.form-basic-filter', ['userDivision' => $userDivision, 'attendents' => $attendents, 'allUsers' => $allUsers]);
			echo view('partials.form-advanced-filter', ['divisions' => $divisions, 'applicants' => $applicants]);
			echo view('partials.form-campus-filter');
			echo '</div>';
		}
		echo view('partials.card-info');
		echo '</aside>
		</div>
		';
	}


	public function fimDeSemana($data)
	{
		$diaDaSemana = intval(date('w', strtotime($data)));
		return ($diaDaSemana == 6 || $diaDaSemana == 0);
	}

	public function foraDoExpediente($data)
	{
		$hora = date('H', strtotime($data));
		$hora = intval($hora);
		if ($hora >= 17) {
			return true;
		}
		if ($hora < 8) {
			return true;
		}
		if ($hora == 11) {
			return true;
		}
		return false;
	}
	public function calcularHoraSolucao($dataAbertura, $tempoSla)
	{
		if ($dataAbertura == null) {
			return "Indefinido";
		}
		while ($this->fimDeSemana($dataAbertura)) {
			$dataAbertura = date("Y-m-d 08:00:00", strtotime('+1 day', strtotime($dataAbertura)));
		}
		while ($this->foraDoExpediente($dataAbertura)) {
			$dataAbertura = date("Y-m-d H:00:00", strtotime('+1 hour', strtotime($dataAbertura)));
		}
		$timeEstimado = strtotime($dataAbertura);
		$tempoSla++;
		for ($i = 0; $i < $tempoSla; $i++) {
			$timeEstimado = strtotime('+' . $i . ' hour', strtotime($dataAbertura));
			$horaEstimada = date("Y-m-d H:i:s", $timeEstimado);
			while ($this->fimDeSemana($horaEstimada)) {
				$horaEstimada = date("Y-m-d 08:00:00", strtotime('+1 day', strtotime($horaEstimada)));
				$i = $i + 24;
				$tempoSla += 24;
			}

			while ($this->foraDoExpediente($horaEstimada)) {
				$horaEstimada = date("Y-m-d H:i:s", strtotime('+1 hour', strtotime($horaEstimada)));
				$i++;
				$tempoSla++;
			}
		}
		$horaEstimada = date('Y-m-d H:i:s', $timeEstimado);
		return $horaEstimada;
	}




	public function getColorStatus($siglaStatus)
	{
		$strCartao = ' alert-warning ';
		if ($siglaStatus == self::STATUS_ABERTO) {
			$strCartao = '  notice-warning';
		} else if ($siglaStatus == self::STATUS_ATENDIMENTO) {
			$strCartao = '  notice-info ';
		} else if ($siglaStatus == self::STATUS_FECHADO) {
			$strCartao = 'notice-success ';
		} else if ($siglaStatus == self::STATUS_FECHADO_CONFIRMADO) {
			$strCartao = 'notice-success ';
		} else if ($siglaStatus == self::STATUS_CANCELADO) {
			$strCartao = ' notice-warning ';
		} else if ($siglaStatus == self::STATUS_REABERTO) {
			$strCartao = '  notice-warning ';
		} else if ($siglaStatus == self::STATUS_RESERVADO) {
			$strCartao = '  notice-warning ';
		} else if ($siglaStatus == self::STATUS_AGUARDANDO_USUARIO) {
			$strCartao = '  notice-warning ';
		} else if ($siglaStatus == self::STATUS_AGUARDANDO_ATIVO) {
			$strCartao = ' notice-warning';
		}
		return $strCartao;
	}
	public function canCancel($order)
	{
		return $this->sessao->getIdUsuario() == $order->customer_user_id && $order->status == self::STATUS_ABERTO;
	}
	public function canWait($currentStatus)
	{
		return $this->sessao->getNivelAcesso()
			!= Sessao::NIVEL_COMUM && $currentStatus == 'e'
			&& $this->sessao->getIdUsuario() != $this->selecionado->getIdUsuarioAtendente();
	}
	public function show()
	{

		if (!isset($_GET['selecionar'])) {
			return;
		}

		$sessao = new Sessao();
		$this->sessao = new Sessao();
		$selected = Order::findOrFail($_GET['selecionar']);

		if (!$this->parteInteressada($selected)) {
			echo '
            <div class="alert alert-danger" role="alert">
                Você não é cliente deste chamado, nem técnico para atendê-lo.
            </div>
            ';
			return;
		}


		$selected->load([
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
			'service.division'
		]);




		$listaUsuarios = DB::table('users')->whereIn('role', [
			Sessao::NIVEL_TECNICO,
			Sessao::NIVEL_ADM
		])->get();
		$services = Service::whereIn('role', ['customer', 'provider'])->get();
		$divisions = Division::get();

		$dataSolucao = $this->calcularHoraSolucao($selected->created_at, $selected->service->sla);

		$canEditTag = $this->possoEditarPatrimonio($selected);
		$canEditSolution = $this->possoEditarSolucao($selected);
		$selected->service_name = $selected->service->name;
		$canEditService = $this->possoEditarServico($selected);
		$isClient = ($sessao->getNivelAcesso() == Sessao::NIVEL_COMUM);
		$timeNow = time();
		$timeSolucaoEstimada = strtotime($dataSolucao);
		$isLate = $timeNow > $timeSolucaoEstimada;

		$selected->canCancel = $this->canCancel($selected);
		$selected->canRespond = $this->possoAtender($selected);
		$selected->canClose = $this->possoFechar($selected);
		$selected->canRate = $this->possoAvaliar($selected);
		$selected->canReopen = $this->possoReabrir($selected);
		$selected->canReserve = $this->possoReservar($selected);
		$selected->canRelease = $this->possoLiberar($selected);
		$selected->canWait = $this->possoEditarSolucao($selected);


		$providerName = '';
		if ($selected->provider != null) {
			$providerName = $selected->provider->name;
		}
		foreach ($selected->statusLogs as $status) {
			$status->color = $this->getColorStatus($status->status);
		}


		echo '<div class="row">';
		echo view('partials.modal-form-status', ['services' => $services, 'providers' => $listaUsuarios, 'divisions' => $divisions, 'order' => $selected]);


		echo view('partials.panel-status', ['selected' => $selected]);


		echo view('partials.show-order', [
			'order' => $selected,
			'canEditTag' => $canEditTag,
			'canEditSolution' => $canEditSolution,
			'canEditService' => $canEditService,
			'isLevelClient' => $isClient,
			'isLate' => $isLate,
			'dataSolucao' => $dataSolucao,
			'providerDivision' => $selected->division->name . ' - ' . $selected->division->description,
			'providerName' => $providerName
		]);

		$this->mainMessagesOcorrencia($selected);
	}


	public function possoEnviarMensagem($order)
	{
		$sessao = new Sessao();
		if (
			$order->status === OcorrenciaController::STATUS_ATENDIMENTO
			|| $order->status === OcorrenciaController::STATUS_AGUARDANDO_ATIVO
			|| $order->status === OcorrenciaController::STATUS_AGUARDANDO_USUARIO
			&&
			($order->provider != null && $order->provider->id === $sessao->getIdUsuario()
				||
				$order->customer != null && $order->customer->id === $sessao->getIdUsuario()
			)
		) {
			return true;
		} else {
			return false;
		}
	}

	public function mainMessagesOcorrencia($order)
	{

		echo '


        <!-- Modal -->
        <div class="modal fade" id="modalDeleteChat" tabindex="-1" aria-labelledby="modalDeleteChatLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="modalDeleteChatLabel">Apagar Mensagem</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                Tem certeza que deseja apagar esta mensagem?
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form action="" method="post">
                    <input type="hidden" id="chatDelete" name="chatDelete" value=""/>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </form>

              </div>
            </div>
          </div>
        </div>


<div class="container">
		<div class="row">
			<div class="chatbox chatbox22">
				<div class="chatbox__title">
					<h5 class="text-white">#<span id="id-ocorrencia">' . $order->id . '</span></h5>
					<!--<button class="chatbox__title__tray">
            <span></span>
        </button>-->

				</div>
				<div id="corpo-chat" class="chatbox__body">';


		$ultimoId = 0;

		foreach ($order->messages as $mensagemForum) {
			$ultimoId = $mensagemForum->id;
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


			echo '



            			<div class="chatbox__body__message chatbox__body__message--left">

            				<div class="chatbox_timing">
            					<ul>
            						<li><a href="#"><i class="fa fa-calendar"></i> ' . date("d/m/Y", strtotime($mensagemForum->created_at)) . '</a></li>
            						<li><a href="#"><i class="fa fa-clock-o"></i> ' . date("H:i", strtotime($mensagemForum->created_at)) . '</a></a></li>
            					</ul>
            				</div>
            				<div class="clearfix"></div>
            				<div class="ul_section_full">
            					<ul class="ul_msg">
                                    <li><strong>' . $nome . '</strong></li>';
			if ($mensagemForum->type == self::TIPO_ARQUIVO) {
				echo '<li>Anexo: <a href="./storage/uploads/' . $mensagemForum->message . '">Clique aqui</a></li>';
			} else {
				echo '
                        <li>' . nl2br(htmlspecialchars($mensagemForum->message)) . '</li>';
			}
			echo '

            					</ul>
            					<div class="clearfix"></div>

            				</div>

            			</div>';
		}
		echo '<span id="ultimo-id-post" class="escondido">' . $ultimoId . '</span>';
		echo '


				</div>
				<div class="panel-footer">';
		if ($this->possoEnviarMensagem($order)) {
			echo '<form id="insert_form_mensagem_forum" class="user" method="post">
            <input type="hidden" name="enviar_mensagem_forum" value="1">
            <input type="hidden" name="ocorrencia" value="' . $order->id . '">
            <input type="hidden" id="campo_tipo" name="tipo" value="' . self::TIPO_TEXTO . '">

            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" name="muda-tipo" id="muda-tipo">
              <label class="custom-control-label" for="muda-tipo">Enviar Arquivo</label>
            </div>
            <div class="custom-file mb-3 escondido" id="campo-anexo">
                  <input type="file" class="custom-file-input" name="anexo" id="anexo" accept="application/msword, application/vnd.ms-excel, application/vnd.ms-powerpoint, text/plain, application/pdf, image/*, application/zip,application/rar, .ovpn, .xlsx">
                  <label class="custom-file-label" for="anexo" data-browse="Anexar">Anexar um Arquivo</label>
            </div>
            <div class="input-group">
                <textarea style="resize: none;" name="mensagem" rows="3" cols="40"  name="mensagem" id="campo-texto" t></textarea>
                <span class="input-group-btn"> <button class="btn bt_bg btn-sm" id="botao-enviar-mensagem">Enviar</button></span>
            </div>
        </form>';
		}

		echo '
				</div>
			</div>
		</div>
	</div>

';
	}
	public function possoCancelar($order)
	{
		return $this->sessao->getIdUsuario() === $order->customer->id
			&&
			($order->status == self::STATUS_REABERTO || $order->status == self::STATUS_ABERTO);
	}
	public function possoLiberar($order)
	{
		if (
			$this->sessao->getNivelAcesso() === Sessao::NIVEL_ADM
			&&
			($order->status == self::STATUS_ATENDIMENTO
				||
				$order->status == self::STATUS_RESERVADO
				||
				$order->status == self::STATUS_AGUARDANDO_ATIVO
				||
				$order->status == self::STATUS_AGUARDANDO_USUARIO
			)
		) {
			return true;
		} else if ($this->possoEditarSolucao($order)) {
			return true;
		}
		return false;
	}

	public function possoAtender($order)
	{
		if (
			$this->sessao->getNivelAcesso() === Sessao::NIVEL_ADM
			|| $this->sessao->getNivelAcesso() === Sessao::NIVEL_TECNICO
			&&
			($order->status == self::STATUS_ABERTO
				||
				$order->status == self::STATUS_REABERTO
				||
				$order->status == self::STATUS_RESERVADO
				||
				$order->status == self::STATUS_AGUARDANDO_USUARIO
				||
				$order->status == self::STATUS_AGUARDANDO_ATIVO
			)
		) {
			return true;
		}
		return false;
	}

	public function possoFechar($order)
	{
		if (trim($order->solution) == "") {
			return false;
		}
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
			return false;
		}
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_DESLOGADO) {
			return false;
		}

		if ($order->status == Self::STATUS_ATENDIMENTO) {
			if ($this->sessao->getIdUsuario() == $order->provider->id) {
				return true;
			}
		}

		return false;
	}


	public function possoEditarServico($order)
	{
		$this->sessao = new Sessao();
		if (
			$order->provider != null && $this->sessao->getIdUsuario() === $order->provider->id
			&& $order->status != self::STATUS_ATENDIMENTO
		) {
			return true;
		}
		return false;
	}
	public function possoEditarAreaResponsavel($order)
	{

		$this->sessao = new Sessao();
		if ($this->sessao->getNivelAcesso() != Sessao::NIVEL_ADM) {
			return false;
		}

		if ($order->status == self::STATUS_ABERTO) {
			return true;
		}
		if ($order->status == self::STATUS_REABERTO) {
			return true;
		}
		return false;
	}


	public function possoEditarSolucao($order)
	{
		$this->sessao = new Sessao();
		if (
			$order->status === self::STATUS_ATENDIMENTO
			&& $order->provider != null &&
			$this->sessao->getIdUsuario() === $order->provider->id
		) {
			return true;
		}
		return false;
	}

	public function possoReservar($order)
	{
		if (
			$this->sessao->getNivelAcesso() === Sessao::NIVEL_ADM
			&& ($order->statos === self::STATUS_ABERTO
				||
				$order->statos === self::STATUS_REABERTO
				||
				$order->statos === self::STATUS_ATENDIMENTO
				||
				$order->statos === self::STATUS_AGUARDANDO_ATIVO
				||
				$order->statos === self::STATUS_AGUARDANDO_USUARIO
			)
		) {
			return true;
		}
		return false;
	}

	public function possoEditarPatrimonio($order)
	{
		$this->sessao = new Sessao();

		if ($order->status == self::STATUS_FECHADO) {
			return false;
		}
		if ($order->status == self::STATUS_CANCELADO) {
			return false;
		}
		if ($order->status == self::STATUS_FECHADO_CONFIRMADO) {
			return false;
		}
		if ($this->sessao->getIdUsuario() == $order->customer->id) {
			return true;
		}
		if ($order->provider != null && $this->sessao->getIdUsuario() == $order->provider->id) {
			return true;
		}
	}
	public function possoAvaliar($order)
	{
		if (
			$order->status === self::STATUS_FECHADO
			&& $this->sessao->getIdUsuario() === $order->customer->id
			&& $order->customer != null
		) {
			return true;
		}

		return false;
	}
	public function possoReabrir($order)
	{
		if (
			$order->customer != null
			&&
			$this->sessao->getIdUsuario() === $order->customer->id
			&&
			$order->status === self::STATUS_FECHADO
		) {
			return true;
		}
		return false;
	}
	const STATUS_ABERTO = 'opened';
	const STATUS_RESERVADO = 'reserved';
	const STATUS_AGUARDANDO_USUARIO = 'pending customer response';
	const STATUS_ATENDIMENTO = 'in progress';
	const STATUS_FECHADO = 'closed';
	const STATUS_FECHADO_CONFIRMADO = 'committed';
	const STATUS_CANCELADO = 'canceled';
	const STATUS_AGUARDANDO_ATIVO = 'pending it resource';
	const STATUS_REABERTO = 'reopened';

	public function mudarNivel()
	{
		$sessao = new Sessao();
		if (
			$sessao->getNIvelOriginal() != Sessao::NIVEL_TECNICO
			&& $sessao->getNIvelOriginal() != Sessao::NIVEL_ADM
		) {
			echo ':falha:';
			return;
		}
		if (
			$sessao->getNIvelOriginal() === Sessao::NIVEL_TECNICO
			&& $_POST['nivel'] === Sessao::NIVEL_ADM
		) {
			echo ':falha:';
			return;
		}
		$sessao->setNivelDeAcesso($_POST['nivel']);
		echo ':sucess:' . $sessao->getNivelAcesso();
		return;
	}

	public function painel($lista, $strTitulo, $id, $strShow = "")
	{
		echo view(
			'partials.index-orders',
			[
				'orders' => $lista,
				'id' => $id,
				'title' => $strTitulo,
				'strShow' => $strShow
			]
		);
	}

	public function arrayStatusPendente()
	{
		$arrStatus = array();
		$arrStatus[] = self::STATUS_ABERTO;
		$arrStatus[] = self::STATUS_AGUARDANDO_ATIVO;
		$arrStatus[] = self::STATUS_AGUARDANDO_USUARIO;
		$arrStatus[] = self::STATUS_ATENDIMENTO;
		$arrStatus[] = self::STATUS_REABERTO;
		$arrStatus[] = self::STATUS_RESERVADO;
		return $arrStatus;
	}
	public function arrayStatusFinalizado()
	{

		$arrStatus = array();
		$arrStatus[] = self::STATUS_FECHADO;
		$arrStatus[] = self::STATUS_FECHADO_CONFIRMADO;
		$arrStatus[] = self::STATUS_CANCELADO;
		return $arrStatus;
	}

	public function atrasado($ocorrencia)
	{
		if ($ocorrencia->tempo_sla < 1) {
			return false;
		}
		$horaEstimada = $this->calcularHoraSolucao($ocorrencia->data_abertura, $ocorrencia->tempo_sla);
		$timeHoje = time();
		$timeSolucaoEstimada = strtotime($horaEstimada);
		return $timeHoje > $timeSolucaoEstimada;
	}

	public function applyFilters($query)
	{
		if (isset($_GET['setor'])) {
			$divisionId = intval($_GET['setor']);
			$query = $query->where('orders.division_id', $divisionId);
		}
		if (isset($_GET['demanda'])) {
			$query = $query->where(function ($query) {
				$query->where('provider_user_id', $this->sessao->getIdUsuario());
			});
		}
		if (isset($_GET['solicitacao'])) {
			$query = $query->where('customer_user_id', $this->sessao->getIdUsuario());
		}
		if (isset($_GET['tecnico'])) {
			$query = $query->where(function ($query) {
				$query->where('provider_user_id', intval($_GET['tecnico']));
			});
		}
		if (isset($_GET['requisitante'])) {
			$query = $query->where('customer_user_id', intval($_GET['requisitante']));
		}
		if (isset($_GET['data_abertura1'])) {
			$data1 = date("Y-m-d", strtotime($_GET['data_abertura1']));
			$query = $query->where('orders.created_at', '>=', $data1);
		}
		if (isset($_GET['data_abertura2'])) {
			$data2 = date("Y-m-d", strtotime($_GET['data_abertura2']));
			$query = $query->where('orders.created_at', '<=', $data2);
		}
		if (isset($_GET['campus'])) {
			$campusArr = explode(",", $_GET['campus']);
			$query = $query->whereIn('campus', $campusArr);
		}
		if (isset($_GET['setores_responsaveis'])) {
			$divisions = explode(",", $_GET['setores_responsaveis']);
			$query = $query->whereIn('orders.division_id', $divisions);
		}
		if (isset($_GET['setores_requisitantes'])) {
			$divisionsSig = explode(",", $_GET['setores_requisitantes']);
			$query = $query->whereIn('division_sig_id', $divisionsSig);
		}

		return $query;
	}



    public function emailNotificationMessage($orderMessage, $order)
    {
        $mail = new Mail();


        $assunto = "[3S] - Chamado Nº " .  $order->id;




        $corpo = '<p>Avisamos que houve uma mensagem nova na solicitação <a href="https://3s.unilab.edu.br/?page=ocorrencia&selecionar=' . $order->id. '">Nº' . $order->id . '</a></p>';

        $corpo .= '<ul>

                        <li>Corpo: ' . $orderMessage->message . '</li>
                        <li>Serviço Solicitado: ' . $order->service->name . '</li>
                        <li>Descrição do Problema: ' . $order->description . '</li>
                        <li>Setor Responsável: ' . $order->division->name . ' -
                        ' . $order->division->description . '</li>
                        <li>Cliente: ' . $order->customer->name . '</li>
                </ul><br><p>Mensagem enviada pelo sistema 3S. Favor não responder.</p>';


        //cutomer
        $saldacao =  '<p>Prezado(a) ' .$order->customer->name . ' ,</p>';
        $mail->enviarEmail($order->email, $order->customer->name, $assunto, $saldacao . $corpo);
        if ($order->provider != null) {
            //Provider
            $saldacao =  '<p>Prezado(a) ' . $order->provider->name . ' ,</p>';
            $mail->enviarEmail($order->provider->email, $order->provider->name, $assunto, $saldacao . $corpo);
        }
    }

    public function addMensagemAjax()
    {

        $sessao = new Sessao();
        if (!isset($_POST['enviar_mensagem_forum'])) {
            return;
        }
        if (!(isset($_POST['tipo'])
            && isset($_POST['mensagem'])
            && isset($_POST['ocorrencia']))) {
            echo ':incompleto';
            return;
        }
        $order = Order::findOrFail(intval($_POST['ocorrencia']));
        $order->load([
            'division',
            'customer',
            'provider.division',
            'service.division'
        ]);
        if ($order->status == 'closed' || $order->status == 'commited') {
            echo ':falha:O chamado já foi fechado.';
            return;
        }
        $messageData = [];
        $messageData['message'] = $_POST['mensagem'];
        $novoNome = "";

        if ($_POST['tipo'] == self::TIPO_TEXTO) {
            $messageData['type'] = self::TIPO_TEXTO;
        } else {

            $messageData['type'] = self::TIPO_ARQUIVO;
            if (request()->hasFile('anexo')) {
                $anexo = request()->file('anexo');
                if (!Storage::exists('public/uploads')) {
                    Storage::makeDirectory('public/uploads');
                }
                $novoNome = $anexo->getClientOriginalName();

                if (Storage::exists('public/uploads/' . $anexo->getClientOriginalName())) {
                    $novoNome = uniqid() . '_' . $novoNome;
                }

                $extensaoArr = explode('.', $novoNome);
                $extensao = strtolower(end($extensaoArr));

                $extensoes_permitidas = [
                    'xlsx', 'xlsm', 'xlsb', 'xltx', 'xltm', 'xls', 'xlt', 'xls', 'xml', 'xml', 'xlam', 'xla', 'xlw', 'xlr',
                    'doc', 'docm', 'docx', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'pdf', 'rtf', 'txt', 'wps', 'xml', 'zip', 'rar', 'ovpn',
                    'xml', 'xps', 'jpg', 'gif', 'png', 'pdf', 'jpeg'
                ];

                if (!in_array($extensao, $extensoes_permitidas)) {
                    echo ':falha:Extensão não permitida. Lista de extensões permitidas a seguir. ';
                    echo '(' . implode(", ", $extensoes_permitidas) . ')';
                    return;
                }


                if (!$anexo->storeAs('public/uploads/', $novoNome)) {
                    echo ':falha:arquivo não pode ser enviado';
                    return;
                }
            }

			if($novoNome === null || $novoNome === "") {
				echo ':falha:Nome do arquivo está nulo';
			}
            $messageData['message'] = $novoNome;
        }
        $messageData['user_id'] = $sessao->getIdUsuario();
        $messageData['order_id'] = $_POST['ocorrencia'];


        $orderMessage = OrderMessage::create($messageData);
        if ($orderMessage && $orderMessage->id) {
            echo ':sucesso:' . $order->id . ':';
            $this->emailNotificationMessage($orderMessage, $order);
        } else {
            echo ':falha';
        }
    }

	public function create()
	{
		$this->sessao = new Sessao();
		$listaNaoAvaliados = Order::where('customer_user_id', $this->sessao->getIdUsuario())->where(
			'status',
			self::STATUS_FECHADO
		)->get();

		echo '
            <div class="row">
                <div class="col-md-12 blog-main">';


		$queryService = DB::table('services');
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
			$queryService->where('role', Sessao::NIVEL_COMUM);
		}
		if ($this->sessao->getNivelAcesso() == Sessao::NIVEL_ADM
			|| $this->sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO) {
			$queryService->whereIn('role', [Sessao::NIVEL_COMUM, Sessao::NIVEL_TECNICO]);
		}
		$services = $queryService->get();

		if (count($listaNaoAvaliados) == 0) {
			echo '<h3 class="pb-4 mb-4 font-italic border-bottom">Cadastrar Ocorrência</h3>';
			echo view('partials.form-insert-order', ['services' => $services, 'email' => $this->sessao->getEmail()]);
		} else {

			echo view(
				'partials.index-orders',
				[
					'orders' => $listaNaoAvaliados,
					'title' => 'Para continuar confirme os chamados fechados.',
					'id' => 'collapseToConfirm',
					'strShow' => 'show'
				]
			);
		}
		echo '
                </div>
            </div>';
	}

    public function mainApiMessage()
    {
        $sessao = new Sessao();
        if ($sessao->getNivelAcesso() == Sessao::NIVEL_DESLOGADO) {
            return;
        }
        header('Content-type: application/json');
        $this->get();
    }

    private function parteInteressada($order)
    {
        $sessao = new Sessao();
        if (
            $sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO
            || $sessao->getNivelAcesso() == Sessao::NIVEL_ADM
            || $order->customer->id === $sessao->getIdUsuario()
            ) {
            return true;
        }
        return false;
    }

    private function get()
    {

        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            return;
        }

        if (!isset($_REQUEST['api'])) {
            return;
        }

        $url = explode("/", $_REQUEST['api']);
        if (count($url) == 0 || $url[0] == "") {
            return;
        }
        if (!isset($url[1])) {
            return;
        }
        if ($url[1] != 'mensagem_forum') {
            return;
        }
        if (!isset($url[2])) {
            return;
        }
        if (isset($url[2]) == "") {
            return;
        }

        $id = intval($url[2]);
        $order = Order::findOrFail($id);
        if (!$this->parteInteressada($order)) {
            echo "{Acesso Negado}";
            return;
        }


        if (isset($url[3]) && $url[3] != '') {
            $idM = intval($url[3]);
            $query = OrderMessage::where('order_id', '=', $order->id)->where('id', '>', $idM)->orderBy('id');

        } else {
            $query = OrderMessage::where('order_id', '=', $order->id)->orderBy('id');
        }

        $list = $query->get();

        if ($list->count() === 0) {
            echo "[]";
            return;
        }


        $listagem = array();
        foreach ($list as $linha) {
            $listagem[] = array(
                'id' => $linha->id,
                'tipo' => $linha->type,
                'mensagem' => strip_tags($linha->message),
                'data_envio' => $linha->created_at,
                'nome_usuario' => $linha->user->name
            );
        }
        echo json_encode($listagem);
    }
	public function passwordVerify()
	{
		$this->sessao = new Sessao();
		if (!isset($_POST['senha'])) {
			return false;
		}
		$login = $this->sessao->getLoginUsuario();
		$senha = $_POST['senha'];
		$data = ['login' =>  $login, 'senha' => $senha];
		$response = Http::post(env('UNILAB_API_ORIGIN') . '/authenticate', $data);
		$responseJ = json_decode($response->body());

		$idUsuario  = 0;

		if (isset($responseJ->id)) {
			$idUsuario = intval($responseJ->id);
		}
		if ($idUsuario === 0) {
			return false;
		}
		if ($responseJ->id != $this->sessao->getIdUsuario()) {
			echo ":falha:Senha Incorreta.";
			return false;
		}
		return true;
	}
	public function mainAjaxStatus()
	{
		if (!isset($_POST['status_acao'])) {
			echo ':falha:Ação não especificada';
			return;
		}
		if (!$this->passwordVerify()) {
			echo ':falha:Senha incorreta';
			return;
		}

		$this->sessao = new Sessao();

		$order = Order::findOrFail($_POST['id_ocorrencia']);
		$order->load([
			'messages.user' => function ($query) {
				$query->orderBy('id', 'asc');
			},
			'statusLogs' => function ($query) {
				$query->orderBy('id', 'asc');
			},
			'division',
			'customer',
			'provider.division',
			'service.division'
		]);
		$status = false;
		$mensagem = "";
		switch ($_POST['status_acao']) {
			case 'cancelar':
				$status = $this->ajaxCancelar($order);
				$mensagem = '<p>Chamado cancelado</p>';
				break;
			case 'atender':
				$status = $this->ajaxAtender($order);
				$mensagem = '<p>Chamado em atendimento</p>';
				break;
			case 'fechar':
				$status = $this->ajaxFechar($order);
				$mensagem = '<p>Chamado fechado</p>';
				break;
			case 'reservar':
				$status = $this->ajaxReservar($order);
				$mensagem = '<p>Chamado reservado</p>';
				break;
			case 'liberar_atendimento':
				$status = $this->ajaxLiberar($order);
				$mensagem = '<p>Chamado Liberado para atendimento</p>';
				break;
			case 'avaliar':
				$status = $this->ajaxAvaliar($order);
				$mensagem = '<p>Chamado avaliado</p>';
				break;
			case 'reabrir':
				$status = $this->ajaxReabrir($order);
				$mensagem = '<p>Chamado reaberto</p>';
				break;
			case 'editar_servico':
				$status = $this->ajaxEditarServico($order);
				$mensagem = '<p>Serviço alterado</p>';
				break;
			case 'editar_solucao':
				$status = $this->ajaxEditarSolucao($order);
				$mensagem = '<p>Solução editada</p>';
				break;
			case 'aguardar_ativos':
				$status = $this->ajaxAguardandoAtivo($order);
				$mensagem = '<p>Aguardando ativo de TI</p>';
				break;
			case 'aguardar_usuario':
				$status = $this->ajaxAguardandoUsuario($order);
				$mensagem = '<p>Aguardando resposta do cliente</p>';
				break;
			case 'editar_patrimonio':
				$status = $this->ajaxEditarPatrimonio($order);
				$mensagem = '<p>Patrimônio editado.</p>';
				break;
			default:
				echo ':falha:Ação não encontrada';

				break;
		}
		if ($status) {
			$this->enviarEmail($order, $mensagem);
		}
	}

	public function ajaxAtender($order)
	{
		$this->sessao = new Sessao();
		if (!isset($_POST['status_acao'])) {
			return false;
		}
		if ($_POST['status_acao'] != 'atender') {
			return false;
		}
		if (!isset($_POST['id_ocorrencia'])) {
			return false;
		}
		if (!isset($_POST['senha'])) {
			return false;
		}

		if (!$this->possoAtender($order)) {
			echo ':falha:Não é possível atender este chamado.';
			return false;
		}

		DB::beginTransaction();
		try {
			OrderStatusLog::create([
				'order_id' => $order->id,
				'status' => self::STATUS_ATENDIMENTO,
				'message' => 'Chamado em atendimento',
				'user_id' => auth()->user()->id
			]);
			$order->status = self::STATUS_ATENDIMENTO;
			$order->division_id = auth()->user()->division_id;
			$order->provider_user_id = auth()->user()->id;
			$order->save();
			DB::commit();
			echo ':sucesso:' . $order->id . ':Chamado am atendimento!';
		} catch (\Exception $e) {
			DB::rollback();
			echo ':falha:Falha ao tentar inserir histórico.';
		}
	}


	public function ajaxCancelar($order)
	{
		if (!isset($_POST['status_acao'])) {
			return false;
		}
		// if ($_POST['status_acao'] != 'cancelar') {
		// 	return false;
		// }
		// if (!isset($_POST['id_ocorrencia'])) {
		// 	return false;
		// }
		// if (!isset($_POST['senha'])) {
		// 	return false;
		// }

		// $order = Order::findOrFail($_POST['id_ocorrencia']);

		// if (!$this->possoCancelar($order)) {
		// 	echo ":falha:Este chamado não pode ser cancelado.";
		// 	return false;
		// }


		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_CANCELADO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_CANCELADO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Ocorrência cancelada pelo usuário");


		// $ocorrenciaDao->getConnection()->beginTransaction();

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();
		echo ':sucesso:' . $order->id . ':Chamado cancelado com sucesso!';
		return true;
	}

	public function ajaxFechar($order)
	{
		if (!$this->possoFechar($order)) {
			echo ':falha:Não é possível fechar este chamado.';
			return false;
		}

		// $usuario = new Usuario();
		// $usuario->setId($this->sessao->getIdUsuario());

		// $usuarioDao = new UsuarioDAO($this->dao->getConnection());
		// $usuarioDao->fillById($usuario);
		// $this->ocorrencia->getAreaResponsavel()->setId($usuario->getIdSetor());


		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_FECHADO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_FECHADO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Ocorrência fechada pelo atendente");


		// $this->ocorrencia->setDataFechamento(date("Y-m-d H:i:s"));


		// $ocorrenciaDao->getConnection()->beginTransaction();

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();
		echo ':sucesso:' . $order->id . ':Chamado fechado com sucesso!';

		return true;
	}
	public function ajaxAvaliar($order)
	{
		// if (!isset($_POST['avaliacao'])) {
		// 	echo ':falha:Faça uma avaliação';
		// 	return false;
		// }
		// if (!$this->possoAvaliar($order)) {
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_FECHADO_CONFIRMADO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_FECHADO_CONFIRMADO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Atendimento avaliado pelo cliente");

		// $this->ocorrencia->setDataFechamentoConfirmado(date("Y-m-d H:i:s"));
		// $this->ocorrencia->setAvaliacao($_POST['avaliacao']);

		// $ocorrenciaDao->getConnection()->beginTransaction();



		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Atendimento avaliado com sucesso!';
		return true;
	}
	public function ajaxReabrir($order)
	{
		// if (!$this->possoReabrir($order)) {
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_REABERTO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_REABERTO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Ocorrência Reaberta pelo cliente");
		// if (isset($_POST['mensagem-status'])) {
		// 	$this->statusOcorrencia->setMensagem($_POST['mensagem-status']);
		// }


		// $ocorrenciaDao->getConnection()->beginTransaction();



		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Atendimento reaberto com sucesso!';
		return true;
	}



	public function ajaxReservar($order)
	{
		// if (!isset($_POST['tecnico'])) {
		// 	echo ':falha:Técnico especificado';
		// 	return false;
		// }
		// if (!$this->possoReservar($order)) {
		// 	echo ':falha:Você não pode reservar esse chamado.';
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $ocorrenciaDao->fillById($this->ocorrencia);

		// $usuario = new Usuario();
		// $usuario->setId($_POST['tecnico']);

		// $usuarioDao = new UsuarioDAO($this->dao->getConnection());
		// $usuarioDao->fillById($usuario);

		// $this->ocorrencia->getAreaResponsavel()->setId($usuario->getIdSetor());
		// $this->ocorrencia->setStatus(self::STATUS_RESERVADO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_RESERVADO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Atendimento reservado para ' . $usuario->getNome());


		// $ocorrenciaDao->getConnection()->beginTransaction();
		// $this->ocorrencia->setIdUsuarioIndicado($usuario->getId());
		// $this->ocorrencia->getAreaResponsavel()->setId($usuario->getIdSetor());


		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Reservado com sucesso!';
		return true;
	}

	public function ajaxEditarPatrimonio($order)
	{
		// if (!$this->possoEditarPatrimonio($this->ocorrencia)) {
		// 	echo ':falha:Este patrimônio não pode ser editado.';
		// 	return false;
		// }
		// if (!isset($_POST['patrimonio'])) {
		// 	echo ':falha:Digite um patrimônio.';
		// 	return false;
		// }
		// if (trim($_POST['patrimonio']) == "") {
		// 	echo ':falha:Digite um patrimônio.';
		// 	return false;
		// }



		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $status = new Status();
		// $status->setSigla($this->ocorrencia->getStatus());

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Técnico editou o Patrimônio para: ' . $_POST['patrimonio'] . '.');

		// $this->ocorrencia->setPatrimonio(strip_tags($_POST['patrimonio']));
		// $ocorrenciaDao->getConnection()->beginTransaction();


		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do patrimonio da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Patrimonio editado com sucesso!';
		return true;
	}

	public function ajaxAguardandoAtivo($order)
	{

		// if (!$this->possoEditarSolucao($order)) {
		// 	echo ':falha:Esta solução não pode ser editada.';
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_AGUARDANDO_ATIVO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_AGUARDANDO_ATIVO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Aguardando ativo de TI");


		// $ocorrenciaDao->getConnection()->beginTransaction();

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();
		echo ':sucesso:' . $order->id . ':Alterado para aguardando ativo de ti!';
		return true;
	}
	public function ajaxAguardandoUsuario($order)
	{
		// if (!$this->possoEditarSolucao($this->ocorrencia)) {
		// 	echo ':falha:Esta solução não pode ser editada.';
		// 	return false;
		// }
		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_AGUARDANDO_USUARIO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_AGUARDANDO_USUARIO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem("Aguardando Usuário");


		// $ocorrenciaDao->getConnection()->beginTransaction();

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();
		echo ':sucesso:' . $order->id . ':Alterado para aguardando usuário!';
		return true;
	}

	public function ajaxEditarSolucao($order)
	{
		// if (!$this->possoEditarSolucao($this->ocorrencia)) {
		// 	echo ':falha:Esta solução não pode ser editada.';
		// 	return false;
		// }
		// if (!isset($_POST['solucao'])) {
		// 	echo ':falha:Digite uma solução.';
		// 	return false;
		// }
		// if (trim($_POST['solucao']) == "") {
		// 	echo ':falha:Digite uma solução.';
		// 	return false;
		// }



		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $status = new Status();
		// $status->setSigla($this->ocorrencia->getStatus());

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Técnico editou a solução. ');

		// $this->ocorrencia->setSolucao(strip_tags($_POST['solucao']));
		// $ocorrenciaDao->getConnection()->beginTransaction();


		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Solução editada com sucesso!';
		return true;
	}


	public function ajaxEditarServico($order)
	{
		// if (!$this->possoEditarServico($this->ocorrencia)) {
		// 	echo ':falha:Este serviço não pode ser editado.';
		// 	return false;
		// }

		// if (!isset($_POST['id_servico'])) {
		// 	echo ':falha:Selecione um serviço.';
		// 	return false;
		// }


		// $servico = new Servico();
		// $servico->setId($_POST['id_servico']);

		// $servicoDao = new ServicoDAO($this->dao->getConnection());
		// $servicoDao->fillById($servico);


		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());


		// $status = new Status();
		// $status->setSigla($this->ocorrencia->getStatus());

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Técnico editou o serviço ');

		// $this->ocorrencia->getAreaResponsavel()->setId($servico->getAreaResponsavel()->getId());
		// $this->ocorrencia->getServico()->setId($servico->getId());



		// $ocorrenciaDao->getConnection()->beginTransaction();




		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->id . ':Serviço editado com sucesso!';
		return true;
	}

	public function ajaxLiberar($order)
	{

		// if (!$this->possoLiberar($order)) {
		// 	echo ':falha:Não é possível liberar esse atendimento.';
		// 	return false;
		// }

		// $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
		// $this->ocorrencia->setStatus(self::STATUS_ABERTO);

		// $status = new Status();
		// $status->setSigla(self::STATUS_ABERTO);

		// $statusDao = new StatusDAO($this->dao->getConnection());
		// $statusDao->fillBySigla($status);

		// $this->statusOcorrencia = new StatusOcorrencia();
		// $this->statusOcorrencia->setOcorrencia($this->ocorrencia);
		// $this->statusOcorrencia->setStatus($status);
		// $this->statusOcorrencia->setDataMudanca(date("Y-m-d G:i:s"));
		// $this->statusOcorrencia->getUsuario()->setId($this->sessao->getIdUsuario());
		// $this->statusOcorrencia->setMensagem('Liberado para atendimento');


		// $ocorrenciaDao->getConnection()->beginTransaction();
		// $this->ocorrencia->setIdUsuarioIndicado(null);
		// $this->ocorrencia->setIdUsuarioAtendente(null);

		// $servicoDao = new ServicoDAO($this->dao->getConnection());
		// $servicoDao->fillById($this->ocorrencia->getServico());


		// $this->ocorrencia->getAreaResponsavel()->setId($this->ocorrencia->getServico()->getAreaResponsavel()->getId());

		// if (!$ocorrenciaDao->update($this->ocorrencia)) {
		// 	echo ':falha:Falha na alteração do status da ocorrência.';
		// 	$ocorrenciaDao->getConnection()->rollBack();
		// 	return false;
		// }

		// if (!$this->dao->insert($this->statusOcorrencia)) {
		// 	echo ':falha:Falha ao tentar inserir histórico.';
		// 	return false;
		// }
		// $ocorrenciaDao->getConnection()->commit();

		echo ':sucesso:' . $order->getId() . ':Liberado com sucesso!';

		return true;
	}


	public function enviarEmail($order, $mensagem = "")
	{
		$mail = new Mail();
		$assunto = "[3S] - Chamado Nº " . $order->id;



		$saldacao =  '<p>Prezado(a),</p>';

		$corpo = '<p>Avisamos que houve uma mudança no status da solicitação
		<a href="https://3s.unilab.edu.br/?page=ocorrencia&selecionar=' .
			$order->id . '">Nº' . $order->id . '</a></p>';
		$corpo .= $mensagem;
		$corpo .= '<ul>
                        <li>Serviço Solicitado: ' . $order->service->name . '</li>
                        <li>Descrição do Problema: ' . $order->description . '</li>
                        <li>Setor Responsável: ' . $order->division->name . ' -
                        ' . $order->division->description . '</li>
                        <li>Cliente: ' . $order->customer->name . '</li>
                </ul><br><p>Mensagem enviada pelo sistema 3S. Favor não responder.</p>';


		$destinatario = $order->email;
		$nome = $order->customer->name;
		//Cliente do chamado
		$mail->enviarEmail(auth()->user()->email, auth()->user()->name, $assunto, $saldacao . $corpo);



		//Area responsável
		$destinatario = $order->division->email;
		$nome = $order->division->name;
		$mail->enviarEmail($destinatario, $nome, $assunto, $saldacao . $corpo); //Email para area responsavel
		if ($order->provider != null) {
			//O atendente;
			$destinatario = $order->provider->email;
			$nome = $order->provider->name;
			$mail->enviarEmail($destinatario, $nome, $assunto, $saldacao . $corpo);
		}
	}
	const TIPO_ARQUIVO = 'file';
	const TIPO_TEXTO = 'text';
}
