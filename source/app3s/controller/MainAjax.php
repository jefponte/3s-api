<?php

namespace app3s\controller;



class MainAjax
{

    public function main()
    {
        switch ($_GET['ajax']) {
            case 'mensagem_forum':
                $controller = new MensagemForumController();
                $controller->addAjax();
                break;
            case 'status_ocorrencia':
                $controller = new OcorrenciaController();
                $controller->mainAjaxStatus();
                break;

            case 'mudar_nivel':
                $controller = new OcorrenciaController();
                $controller->mudarNivel();
                break;
            default:
                echo ':falha';
                break;
        }
    }
}
