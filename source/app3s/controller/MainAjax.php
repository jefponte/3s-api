<?php

namespace app3s\controller;



class MainAjax
{

    public function main()
    {
        $controller = new OcorrenciaController();
        switch ($_GET['ajax']) {
            case 'mensagem_forum':
                $controller->addMensagemAjax();
                break;
            case 'status_ocorrencia':
                $controller->mainAjaxStatus();
                break;

            case 'mudar_nivel':
                $controller->mudarNivel();
                break;
            default:
                echo ':falha';
                break;
        }
    }
}
