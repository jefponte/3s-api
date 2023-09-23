<?php

namespace app3s\controller;

use app3s\util\Sessao;

class MainIndex
{

  public function main()
  {
    $sessao = new Sessao();
    $user = request()->user();


    if ($user === null) {
      return redirect('/login');
    }
    if ($sessao->getNivelAcesso() === Sessao::NIVEL_DESLOGADO) {
      $sessao->criaSessao($user->id, $user->role, $user->login, $user->name, $user->email);
      $sessao->setIDUnidade($user->division_sig_id);
      $sessao->setUnidade($user->division_sig);
      return redirect('/?demanda=1');
    }

    if (isset($_GET["sair"])) {
      $sessao->mataSessao();
      auth()->logout();
      return redirect('/');
    }

    if (isset($_GET['ajax'])) {
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
      exit(0);
    }
    if (isset($_REQUEST['api'])) {
      $controller = new OcorrenciaController();
      $controller->mainApiMessage();
      exit(0);
    }
    $this->pagina();
  }

  public function pagina()
  {
    echo view('partials.header');
    $sessao = new Sessao();
    $primeiroNome = $sessao->getNome();
    $arr = explode(" ", $sessao->getNome());
    if (isset($arr[0])) {
      $primeiroNome = $arr[0];
    }
    $primeiroNome = ucfirst(strtolower($primeiroNome));


    if ($sessao->getNivelAcesso() == Sessao::NIVEL_COMUM) {
      echo view('client.partials.navbar', ['originalLevel' => $sessao->getNivelOriginal(), 'userFirstName' => $primeiroNome, 'divisionSig' => $sessao->getUnidade()]);
    }
    if ($sessao->getNivelAcesso() == Sessao::NIVEL_TECNICO) {
      echo view('provider.partials.navbar', ['originalLevel' => $sessao->getNivelOriginal(), 'userFirstName' => $primeiroNome, 'divisionSig' => $sessao->getUnidade()]);
    }
    if ($sessao->getNivelAcesso() == Sessao::NIVEL_ADM) {
      echo view('admin.partials.navbar', ['userFirstName' => $primeiroNome, 'divisionSig' => $sessao->getUnidade()]);
    }

    $this->mainContent();
    echo view('partials.footer');
  }
  public function mainContent()
  {
    $sessao = new Sessao();
    if ($sessao->getNivelAcesso() == Sessao::NIVEL_DESLOGADO) {
      echo view('partials.form-login');
      return;
    }

    switch ($sessao->getNivelAcesso()) {
      case Sessao::NIVEL_TECNICO:
        $this->content();
        break;
      case Sessao::NIVEL_ADM:
        $this->content();
        break;
      case Sessao::NIVEL_COMUM:
        $this->content();
        break;
      case Sessao::NIVEL_DISABLED:
        echo view('partials.diabled');
        break;
      default:
        echo view('partials.form-login');
        break;
    }
  }

  public function content()
  {
    if (isset($_GET['page'])) {
      switch ($_GET['page']) {
        case 'ocorrencia':
          $controller = new OcorrenciaController();
          $controller->main();
          break;
        default:
          echo '<p>Página solicitada não encontrada.</p>';
          break;
      }
    } else {
      $controller = new OcorrenciaController();
      $controller->main();
    }
  }
}
