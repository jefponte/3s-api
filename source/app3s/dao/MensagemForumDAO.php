<?php

/**
 * Classe feita para manipulação do objeto MensagemForum
 * feita automaticamente com programa gerador de software inventado por
 * @author Jefferson Uchôa Ponte
 */

namespace app3s\dao;
use PDO;
use PDOException;
use app3s\model\MensagemForum;
use app3s\model\Ocorrencia;

class MensagemForumDAO extends DAO {


	/**
	 * @deprecated 1.0.17
	 */
    public function insert(MensagemForum $mensagemForum, $ocorrencia){
        $sql = "INSERT INTO mensagem_forum(tipo, mensagem, id_usuario, data_envio, id_ocorrencia) VALUES (:tipo, :mensagem, :usuario, :dataEnvio, :idOcorrencia);";
		$tipo = $mensagemForum->getTipo();
		$mensagem = $mensagemForum->getMensagem();
		$usuario = $mensagemForum->getUsuario()->getId();
		$dataEnvio = $mensagemForum->getDataEnvio();
        $idOcorrencia = $ocorrencia->id;
		try {
			$db = $this->getConnection();
			$stmt = $db->prepare($sql);
			$stmt->bindParam(":tipo", $tipo, PDO::PARAM_INT);
			$stmt->bindParam(":mensagem", $mensagem, PDO::PARAM_STR);
			$stmt->bindParam(":usuario", $usuario, PDO::PARAM_INT);
			$stmt->bindParam(":dataEnvio", $dataEnvio, PDO::PARAM_STR);
			$stmt->bindParam(":idOcorrencia", $idOcorrencia, PDO::PARAM_INT);
			return $stmt->execute();
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}

    }


}