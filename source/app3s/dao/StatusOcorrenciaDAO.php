<?php

/**
 * Classe feita para manipulação do objeto StatusOcorrencia
 * feita automaticamente com programa gerador de software inventado por
 * @author Jefferson Uchôa Ponte
 */

namespace app3s\dao;

use app3s\model\Ocorrencia;
use PDO;
use PDOException;
use app3s\model\StatusOcorrencia;

class StatusOcorrenciaDAO extends DAO
{

    public function insert(StatusOcorrencia $statusOcorrencia)
    {
        $sql = "INSERT INTO status_ocorrencia(id_ocorrencia, id_status, mensagem, id_usuario, data_mudanca) VALUES (:ocorrencia, :status, :mensagem, :usuario, :dataMudanca);";
        $ocorrencia = $statusOcorrencia->getOcorrencia()->getId();
        $status = $statusOcorrencia->getStatus()->getId();
        $mensagem = $statusOcorrencia->getMensagem();
        $usuario = $statusOcorrencia->getUsuario()->getId();
        $dataMudanca = $statusOcorrencia->getDataMudanca();
        try {
            $db = $this->getConnection();
            $stmt = $db->prepare($sql);
            $stmt->bindParam(":ocorrencia", $ocorrencia, PDO::PARAM_INT);
            $stmt->bindParam(":status", $status, PDO::PARAM_INT);
            $stmt->bindParam(":mensagem", $mensagem, PDO::PARAM_STR);
            $stmt->bindParam(":usuario", $usuario, PDO::PARAM_INT);
            $stmt->bindParam(":dataMudanca", $dataMudanca, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            echo '{"error":{"text":' . $e->getMessage() . '}}';
        }
    }

}
