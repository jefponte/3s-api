<?php

namespace app3s\controller;



class MainAjax
{

    public function main()
    {
        switch ($_GET['ajax']) {
            case 'ocorrencia':
                $controller = new OcorrenciaController();
                $controller->mainAjax();
                break;
            case 'mensagem_forum':
                $controller = new MensagemForumController();
                $controller->addAjax();
                break;
            case 'status_ocorrencia':
                $controller = new StatusOcorrenciaController();
                $controller->mainAjax();
                break;
            case 'pedir_ajuda':
                $controller = new OcorrenciaController();
                $controller->ajaxPedirAjuda();
                break;
            case 'mudar_nivel':
                $controller = new UsuarioController();
                $controller->mudarNivel();
                break;
            default:
                echo ':falha';
                break;
        }
    }
}
