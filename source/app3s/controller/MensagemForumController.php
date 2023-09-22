<?php

/**
 * Classe feita para manipulação do objeto MensagemForumController
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 */

namespace app3s\controller;


use app3s\dao\MensagemForumDAO;
use app3s\dao\UsuarioDAO;
use app3s\model\Ocorrencia;
use app3s\dao\OcorrenciaDAO;
use app3s\model\MensagemForum;
use app3s\model\Usuario;
use app3s\util\Mail;
use app3s\util\Sessao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MensagemForumController
{
    protected $dao;

    public function __construct()
    {
        $this->dao = new MensagemForumDAO();
    }



    public function possoEnviarMensagem($order)
    {
        $sessao = new Sessao();
        if(
            $order->status === StatusOcorrenciaController::STATUS_ATENDIMENTO
            || $order->status === StatusOcorrenciaController::STATUS_AGUARDANDO_ATIVO
            || $order->status === StatusOcorrenciaController::STATUS_AGUARDANDO_USUARIO
            &&
                (
                    $order->provider != null && $order->provider->id === $sessao->getIdUsuario()
                    ||
                    $order->customer != null && $order->customer->id === $sessao->getIdUsuario()
                )
            )
        {
            return true;
        } else {
            return false;
        }

    }

    public function mainOcorrencia($order)
    {
        $sessao = new Sessao();
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
                echo '<li>Anexo: <a href="./storage/uploads/'.$mensagemForum->message.'">Clique aqui</a></li>';
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
    <script>
    function changeField(id) {
        document.getElementById(\'chatDelete\').value = id;
    }
    </script>

';
    }



    public function enviaEmail(MensagemForum $mensagemForum, Ocorrencia $ocorrencia)
    {
        $mail = new Mail();

        $ocorrenciaDao = new OcorrenciaDAO($this->dao->getConnection());
        $ocorrenciaDao->fillById($ocorrencia);

        $assunto = "[3S] - Chamado Nº " . $ocorrencia->getId();



        $saldacao =  '<p>Prezado(a) ' . $ocorrencia->getUsuarioCliente()->getNome() . ' ,</p>';
        $corpo = '<p>Avisamos que houve uma mensagem nova na solicitação <a href="https://3s.unilab.edu.br/?page=ocorrencia&selecionar=' . $ocorrencia->getId() . '">Nº' . $ocorrencia->getId() . '</a></p>';

        $corpo .= '<ul>

                        <li>Corpo: ' . $mensagemForum->getMensagem() . '</li>
                        <li>Serviço Solicitado: ' . $ocorrencia->getServico()->getNome() . '</li>
                        <li>Descrição do Problema: ' . $ocorrencia->getDescricao() . '</li>
                        <li>Setor Responsável: ' . $ocorrencia->getServico()->getAreaResponsavel()->getNome() . ' -
                        ' . $ocorrencia->getServico()->getAreaResponsavel()->getDescricao() . '</li>
                        <li>Cliente: ' . $ocorrencia->getUsuarioCliente()->getNome() . '</li>
                </ul><br><p>Mensagem enviada pelo sistema 3S. Favor não responder.</p>';


        $destinatario = $ocorrencia->getEmail();
        $nome = $ocorrencia->getUsuarioCliente()->getNome();
        $mail->enviarEmail($destinatario, $nome, $assunto, $saldacao . $corpo);


        $usuarioDao = new UsuarioDAO($this->dao->getConnection());
        if ($ocorrencia->getIdUsuarioAtendente() != null) {

            $atendente = new Usuario();
            $atendente->setId($ocorrencia->getIdUsuarioAtendente());
            $usuarioDao->fillById($atendente);
            $destinatario = $atendente->getEmail();
            $nome = $atendente->getNome();

            $saldacao =  '<p>Prezado(a) ' . $nome . ' ,</p>';
            $mail->enviarEmail($destinatario, $nome, $assunto, $saldacao . $corpo);
        } else if ($ocorrencia->getIdUsuarioIndicado() != null) {

            $indicado = new Usuario();
            $indicado->setId($ocorrencia->getIdUsuarioIndicado());
            $usuarioDao->fillById($indicado);
            $destinatario = $indicado->getEmail();
            $nome = $indicado->getNome();

            $saldacao =  '<p>Prezado(a) ' . $nome . ' ,</p>';
            $mail->enviarEmail($destinatario, $nome, $assunto, $saldacao . $corpo);
        }
    }

    public function addAjax()
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

        $mensagemForum = new MensagemForum();
        $mensagemForum->setTipo($_POST['tipo']);
        $novoNome = "";

        if ($_POST['tipo'] == self::TIPO_TEXTO) {
            $mensagemForum->setMensagem($_POST['mensagem']);
        } else {

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
            $mensagemForum->setMensagem($novoNome);
        }

        $mensagemForum->setDataEnvio(date("Y-m-d G:i:s"));

        $mensagemForum->getUsuario()->setId($sessao->getIdUsuario());
        $ocorrencia = new Ocorrencia();
        $ocorrencia->setId($_POST['ocorrencia']);
        $order = DB::table('ocorrencia')->where('id', $ocorrencia->getId())->first();

        if ($order->status == 'f' || $order->status == 'g') {
            echo ':falha:O chamado já foi fechado.';
            return;
        }


        if ($this->dao->insert($mensagemForum, $order)) {
            echo ':sucesso:' . $ocorrencia->getId() . ':';
            $this->enviaEmail($mensagemForum, $ocorrencia);
        } else {
            echo ':falha';
        }
    }


    const TIPO_ARQUIVO = 'file';
    const TIPO_TEXTO = 'text';
}
