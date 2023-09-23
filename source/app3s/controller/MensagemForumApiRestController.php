<?php

/**
 * Classe feita para manipulação do objeto MensagemForumApiRestController
 * @author Jefferson Uchôa Ponte <j.pontee@gmail.com>
 */

namespace app3s\controller;

use app3s\util\Sessao;
use App\Models\Order;
use App\Models\OrderMessage;

class MensagemForumApiRestController
{


    public function main()
    {
        $sessao = new Sessao();
        if ($sessao->getNivelAcesso() == Sessao::NIVEL_DESLOGADO) {
            return;
        }
        header('Content-type: application/json');
        $this->get();
    }

    public function parteInteressada($order)
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

    public function get()
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
}
