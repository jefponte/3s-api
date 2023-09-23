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
    /**
     * @deprecated
     *
     */
    protected $dao;

    public function __construct()
    {
        $this->dao = new MensagemForumDAO();
    }





    public function emailNotificationMessage(MensagemForum $mensagemForum, Ocorrencia $ocorrencia)
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
            $this->emailNotificationMessage($mensagemForum, $ocorrencia);
        } else {
            echo ':falha';
        }
    }


    const TIPO_ARQUIVO = 'file';
    const TIPO_TEXTO = 'text';
}
